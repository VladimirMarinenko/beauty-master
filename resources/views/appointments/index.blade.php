<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Календарь записей') }}
        </h2>
    </x-slot>

    <div class="py-4 px-3">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="p-3">
                <a href="{{ route('appointments.create') }}"
                   class="block w-full text-center px-4 py-3 bg-indigo-600 rounded-md font-semibold text-sm text-white uppercase tracking-wider hover:bg-indigo-700 active:bg-indigo-800 mb-3">
                    + Новая запись
                </a>

                @if(session('success'))
                    <div class="mb-3 text-green-600 text-sm">{{ session('success') }}</div>
                @endif

                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <!-- Подключаем FullCalendar через CDN -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <style>
        /* Стилизация кнопок FullCalendar */
        .fc .fc-button {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            font-size: 0.875rem;
            padding: 0.4rem 0.6rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .fc .fc-button:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }
        .fc .fc-button-active {
            background-color: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
        }
        .fc .fc-button-group {
            gap: 2px;
        }
        .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: space-between;
        }
        .fc .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        /* Улучшение отображения списка событий на мобильном */
        .fc .fc-list-event {
            cursor: pointer;
        }
        .fc .fc-list-event:hover {
            background-color: #f9fafb;
        }
        .fc .fc-list-day-cushion {
            background-color: #f3f4f6;
            padding: 0.5rem;
        }
        .fc .fc-list-event-time {
            font-weight: 600;
            color: #4f46e5;
        }
        .fc .fc-list-event-title {
            font-weight: 500;
        }
        .fc-list-day-side-text {
            display: none;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'listWeek',
                locale: 'ru',
                firstDay: 1,
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Сегодня',
                    month: 'Месяц',
                    week: 'Неделя',
                    day: 'День',
                    listWeek: 'Список'
                },
                views: {
                    listWeek: {
                        listDayFormat: { weekday: 'long', day: 'numeric', month: 'numeric' },
                        listDaySideFormat: { hour: '2-digit', minute: '2-digit' }
                    },
                    timeGridWeek: {
                        slotMinTime: '06:00:00',
                        slotMaxTime: '22:00:00',
                        slotDuration: '00:15:00',
                        snapDuration: '00:15:00'
                    },
                    timeGridDay: {
                        slotMinTime: '06:00:00',
                        slotMaxTime: '22:00:00',
                        slotDuration: '00:15:00',
                        snapDuration: '00:15:00'
                    }
                },

                events: function(fetchInfo, successCallback, failureCallback) {
                    fetch('/appointments/events')
                        .then(response => response.json())
                        .then(data => {
                            localStorage.setItem('cached_events', JSON.stringify(data));
                            successCallback(data);
                        })
                        .catch(() => {
                            const cached = localStorage.getItem('cached_events');
                            if (cached) successCallback(JSON.parse(cached));
                            else failureCallback(new Error('No cached events'));
                        });
                },

                eventClick: function(info) {
                    var appointmentId = info.event.id;
                    window.location.href = '/appointments/' + appointmentId + '/edit';
                },
                dateClick: function(info) {
                    // Округляем время вниз до ближайших 15 минут
                    var date = new Date(info.date);
                    var minutes = Math.floor(date.getMinutes() / 15) * 15;
                    date.setMinutes(minutes);
                    date.setSeconds(0);
                    var start = date.toISOString().slice(0, 16);
                    var endDate = new Date(date.getTime() + 15 * 60000);
                    var end = endDate.toISOString().slice(0, 16);
                    window.location.href = '/appointments/create?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
                },
                eventDidMount: function(info) {
                    var props = info.event.extendedProps;
                    info.el.title = props.client_phone ? 'Тел: ' + props.client_phone : '';
                }
            });

            calendar.render();
        });

        window.addEventListener('load', function() {
            if (navigator.onLine && 'serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => reg.active.postMessage({ action: 'sync-now' }));
            }
        });

        window.addEventListener('online', function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => reg.active.postMessage({ action: 'sync-now' }));
            }
        });


    </script>
</x-app-layout>
