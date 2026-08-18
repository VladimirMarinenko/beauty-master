const CACHE_NAME = 'beauty-manager-v2';
const DB_NAME = 'BeautyManagerDB';
const DB_VERSION = 1;

// Статические ресурсы для обязательного кэширования
const PRECACHE_URLS = [
    '/',
    '/appointments',
    '/appointments/create',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

// Внешние ресурсы (CDN)
const EXTERNAL_URLS = [
    'https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.css',
    'https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.js',
    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
    'https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap'
];

// ============================================
// Установка Service Worker
// ============================================
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Кэширование статических и внешних ресурсов');
                return Promise.all([
                    cache.addAll(PRECACHE_URLS),
                    ...EXTERNAL_URLS.map(url => cache.add(url).catch(err => {
                        console.warn('Не удалось закэшировать внешний ресурс:', url, err);
                    }))
                ]);
            })
            .then(() => self.skipWaiting())
    );
});

// ============================================
// Активация Service Worker
// ============================================
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ============================================
// Перехват запросов
// ============================================
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Для POST-запросов просто пробуем выполнить, но не кэшируем
    if (request.method !== 'GET') {
        event.respondWith(
            fetch(request).catch(() => new Response(null, { status: 503 }))
        );
        return;
    }

    const url = new URL(request.url);

    // Не кэшируем API-запросы (события, проверки, доступные слоты)
    if (url.pathname.startsWith('/appointments/events') ||
        url.pathname.startsWith('/appointments/check-overlap') ||
        url.pathname.startsWith('/appointments/available-slots')) {
        event.respondWith(
            fetch(request).catch(() => new Response(null, { status: 503 }))
        );
        return;
    }

    // Для навигационных запросов (страницы)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, clone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(request).then((cached) => {
                        return cached || caches.match('/appointments');
                    });
                })
        );
        return;
    }

    // Для остальных GET-запросов (кэш -> сеть)
    event.respondWith(
        caches.match(request)
            .then((cached) => {
                if (cached) return cached;

                return fetch(request)
                    .then((response) => {
                        if (response.status === 200) {
                            const clone = response.clone();
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, clone);
                            });
                        }
                        return response;
                    })
                    .catch(() => {
                        // Если ресурс не загрузился (например, шрифты), возвращаем пустой ответ
                        return new Response('', { status: 200, headers: { 'Content-Type': 'text/css' } });
                    });
            })
    );
});

// ============================================
// Push-уведомления
// ============================================
self.addEventListener('push', function(event) {
    let data = { title: 'Beauty Manager', body: 'Новые уведомления', icon: '/icons/icon-192.png' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { title: 'Beauty Manager', body: event.data.text() };
        }
    }

    const options = {
        body: data.body,
        icon: data.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        vibrate: [100, 50, 100],
        data: { url: data.url || '/appointments' }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/appointments')
    );
});

// ============================================
// Background Sync
// ============================================
self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-appointments') {
        event.waitUntil(syncAppointments());
    }
});

async function syncAppointments() {
    const db = await openDatabase();
    const pending = await getPendingAppointments(db);
    for (const appointment of pending) {
        try {
            const response = await fetch('/appointments/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': appointment._token || '',
                },
                body: JSON.stringify(appointment)
            });
            if (response.ok) {
                await deletePendingAppointment(db, appointment.id);
            }
        } catch (error) {
            console.error('Sync failed', error);
        }
    }
}

// ============================================
// IndexedDB helpers
// ============================================
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = function(event) {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pendingAppointments')) {
                db.createObjectStore('pendingAppointments', { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = function(event) { resolve(event.target.result); };
        request.onerror = function(event) { reject(event.target.error); };
    });
}

function getPendingAppointments(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('pendingAppointments', 'readonly');
        const store = tx.objectStore('pendingAppointments');
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
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
