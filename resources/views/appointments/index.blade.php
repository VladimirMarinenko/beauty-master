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

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <style>
        #calendar { font-family: 'Figtree', sans-serif; }

        /* === Тулбар === */
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
        .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* === Кнопки === */
        .fc .fc-button {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            font-size: 0.875rem;
            padding: 0.4rem 0.6rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
            text-transform: none;
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
        .fc .fc-button-group { gap: 2px; }
        .fc .fc-button-group > .fc-button { border-radius: 0.375rem !important; }

        /* === Шапка дней недели === */
        .fc .fc-col-header-cell {
            background: #f9fafb;
            border: none !important;
            padding: 8px 0;
        }
        .fc .fc-col-header-cell-cushion {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            text-decoration: none;
        }

        /* === Ячейки месяца === */
        .fc .fc-daygrid-day {
            border-color: #e5e7eb !important;
            transition: background 0.15s;
            cursor: pointer;
        }
        .fc .fc-daygrid-day:hover {
            background-color: #f9fafb;
        }
        .fc .fc-daygrid-day-frame {
            min-height: 68px;
            padding: 4px;
        }
        .fc .fc-daygrid-day-number {
            color: #1f2937;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
            text-decoration: none;
        }
        .fc .fc-day-today {
            background-color: rgba(79, 70, 229, 0.05) !important;
        }
        .fc .fc-day-today .fc-daygrid-day-number {
            background-color: #4f46e5;
            color: #ffffff !important;
        }
        .fc .fc-day-other .fc-daygrid-day-number {
            color: #c4c4c4;
            font-weight: 400;
        }

        .fc-dayGridMonth-view .fc-daygrid-day-events {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            padding: 2px 6px 0 6px;
            margin-top: 0;
            align-content: flex-start;
            pointer-events: none;
        }
        .fc-dayGridMonth-view .fc-daygrid-event-harness {
            margin-top: 0 !important;
            display: inline-flex;
        }
        .fc-dayGridMonth-view .fc-daygrid-event {
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
            display: inline-flex !important;
            min-width: 0;
            width: auto;
            pointer-events: none;
            cursor: default;
        }
        .fc-dayGridMonth-view .fc-daygrid-event .fc-event-time,
        .fc-dayGridMonth-view .fc-daygrid-event .fc-event-title {
            display: none !important;
        }
        .fc-dayGridMonth-view .fc-daygrid-event-dot {
            display: block !important;
            width: 8px !important;
            height: 8px !important;
            border-width: 4px !important;
            border-style: solid;
            margin: 0 !important;
            border-radius: 50%;
            box-sizing: content-box;
        }
        .fc-dayGridMonth-view .fc-daygrid-more-link {
            font-size: 0.7rem;
            font-weight: 700;
            color: #4f46e5;
            text-decoration: none;
            padding: 0 4px;
            line-height: 1;
        }

        .fc .fc-timegrid-slot {
            height: 32px;
        }
        .fc .fc-timegrid-slot-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .fc .fc-timegrid-event {
            border-radius: 8px;
            padding: 3px 6px;
            border-left-width: 4px;
            cursor: pointer;
        }
        .fc .fc-timegrid-event .fc-event-title {
            font-weight: 600;
            font-size: 0.8rem;
            white-space: normal;
            line-height: 1.2;
        }
        .fc .fc-timegrid-event .fc-event-time {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        .fc .fc-timegrid-now-indicator-line {
            border-color: #ef4444;
        }
        .fc .fc-timegrid-now-indicator-arrow {
            border-color: #ef4444;
        }

        .fc .fc-list-event { cursor: pointer; }
        .fc .fc-list-event:hover { background-color: #f9fafb; }
        .fc .fc-list-day-cushion {
            background-color: #f3f4f6;
            padding: 0.5rem;
        }
        .fc .fc-list-event-time {
            font-weight: 600;
            color: #4f46e5;
        }
        .fc .fc-list-event-title { font-weight: 500; }
        .fc-list-day-side-text { display: none; }


        @media (max-width: 640px) {
            .fc .fc-toolbar-title { font-size: 0.95rem; }
            .fc .fc-button {
                padding: 0.35rem 0.55rem;
                font-size: 0.75rem;
            }
            .fc .fc-daygrid-day-frame {
                min-height: 52px;
                padding: 2px;
            }
            .fc .fc-daygrid-day-number {
                font-size: 0.75rem;
                padding: 1px 5px;
            }
            .fc-dayGridMonth-view .fc-daygrid-event-dot {
                width: 6px !important;
                height: 6px !important;
                border-width: 3px !important;
            }
            .fc .fc-timegrid-slot { height: 28px; }
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
                selectable: true,
                selectMirror: true,
                dayMaxEvents: 12,
                nowIndicator: true,

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek'
                },
                buttonText: {
                    today: 'Сегодня',
                    month: 'Месяц',
                    listWeek: 'Список'
                },
                views: {
                    listWeek: {
                        listDayFormat: { weekday: 'long', day: 'numeric', month: 'numeric' },
                        listDaySideFormat: { hour: '2-digit', minute: '2-digit' }
                    },
                    timeGridDay: {
                        slotMinTime: '08:00:00',
                        slotMaxTime: '22:00:00',
                        slotDuration: '00:15:00',
                        snapDuration: '00:05:00',
                        allDaySlot: false,
                        nowIndicator: true
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

                dateClick: function(info) {
                    if (info.view.type === 'dayGridMonth') {
                        calendar.changeView('timeGridDay', info.dateStr);
                    }
                },

                select: function(info) {
                    if (info.view.type !== 'timeGridDay') return;

                    var start = info.startStr.slice(0, 16);
                    var end = info.endStr.slice(0, 16);

                    window.location.href = '/appointments/create?start=' +
                        encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
                },

                eventClick: function(info) {
                    if (info.view.type === 'dayGridMonth') {
                        return;
                    }
                    window.location.href = '/appointments/' + info.event.id + '/edit';
                },

                eventDidMount: function(info) {
                    var props = info.event.extendedProps;
                    var lines = [];
                    if (props.client_phone) lines.push('Тел: ' + props.client_phone);
                    if (props.total_price) lines.push('Стоимость: ' + parseFloat(props.total_price).toFixed(0) + ' ₽');
                    if (props.total_duration) lines.push('Длительность: ' + props.total_duration + ' мин');
                    info.el.title = lines.join('\n');
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
