const CACHE_NAME = 'beauty-manager-v7';
const DB_NAME = 'BeautyManagerDB';
const DB_VERSION = 1;

const PRECACHE_URLS = [
    '/',
    '/appointments',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

let currentCsrfToken = null;
let syncInFlight = null;

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names.map((name) => name !== CACHE_NAME ? caches.delete(name) : null)
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        event.respondWith(fetch(request).catch(() => new Response(null, { status: 503 })));
        return;
    }

    const url = new URL(request.url);

    if (url.pathname.startsWith('/appointments/events') ||
        url.pathname.startsWith('/appointments/check-overlap') ||
        url.pathname.startsWith('/appointments/available-slots') ||
        url.pathname.startsWith('/push-')) {
        event.respondWith(fetch(request).catch(() => new Response(null, { status: 503 })));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            }).catch(() =>
                caches.match(request).then((cached) => cached || caches.match('/appointments'))
            )
        );
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                if (response.status === 200 && url.origin === self.location.origin) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            }).catch(() => {
                const contentType = request.destination === 'script'
                    ? 'application/javascript'
                    : request.destination === 'style'
                        ? 'text/css'
                        : 'text/plain';
                return new Response('', { status: 503, headers: { 'Content-Type': contentType } });
            });
        })
    );
});

self.addEventListener('push', function(event) {
    let data = { title: 'Beauty Manager', body: 'Новые уведомления', icon: '/icons/icon-192.png' };
    if (event.data) {
        try { data = event.data.json(); }
        catch (e) { data = { title: 'Beauty Manager', body: event.data.text() }; }
    }
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon || '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            vibrate: [100, 50, 100],
            data: { url: data.url || '/appointments' }
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/appointments'));
});

self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-appointments') {
        event.waitUntil(syncAppointments());
    }
});

self.addEventListener('message', function(event) {
    if (!event.data) return;
    if (event.data.csrf) {
        currentCsrfToken = event.data.csrf;
    }
    if (event.data.action === 'sync-now') {
        event.waitUntil(syncAppointments());
    }
});

async function syncAppointments() {
    if (syncInFlight) return syncInFlight;
    syncInFlight = doSyncAppointments();
    try {
        await syncInFlight;
    } finally {
        syncInFlight = null;
    }
}

async function doSyncAppointments() {
    if (!currentCsrfToken) {
        console.warn('Sync: нет CSRF-токена, пропускаем');
        const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
        clients.forEach(client => client.postMessage({ type: 'need-csrf' }));
        return;
    }

    const db = await openDatabase();
    const pending = await getPendingAppointments(db);
    if (!pending.length) return;

    console.log('Sync: найдено записей в очереди:', pending.length);

    for (const appointment of pending) {
        const payload = { ...appointment };
        delete payload.id;
        delete payload._token;

        try {
            const response = await fetch('/appointments/sync', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': currentCsrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            if (response.ok) {
                await deletePendingAppointment(db, appointment.id);
                console.log('Sync: отправлено', appointment.id);
            } else if (response.status === 419) {
                console.warn('Sync: CSRF истёк (419). Сбрасываем токен');
                currentCsrfToken = null;
                const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
                clients.forEach(client => client.postMessage({ type: 'need-csrf' }));
                break;
            } else if ([403, 404, 422].includes(response.status)) {
                console.warn('Sync: ошибка', response.status, '— удаляем из очереди');
                await deletePendingAppointment(db, appointment.id);
            } else {
                console.warn('Sync: сервер вернул', response.status, '— оставляем в очереди');
            }
        } catch (error) {
            console.error('Sync: сетевая ошибка', error);
            break;
        }
    }
}

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = function(event) {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pendingAppointments')) {
                db.createObjectStore('pendingAppointments', { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = (event) => resolve(event.target.result);
        request.onerror = (event) => reject(event.target.error);
    });
}

function getPendingAppointments(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('pendingAppointments', 'readonly');
        const request = tx.objectStore('pendingAppointments').getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

function deletePendingAppointment(db, id) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('pendingAppointments', 'readwrite');
        tx.objectStore('pendingAppointments').delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}
