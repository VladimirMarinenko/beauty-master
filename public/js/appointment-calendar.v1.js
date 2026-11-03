(function (global) {
    'use strict';

    function normalizeDateTime(value) {
        return String(value).slice(0, 16).replace('T', ' ');
    }

    function formatLocalDateTime(date) {
        const pad = value => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ` +
            `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function buildCalendarLocalDate(date) {
        return formatLocalDateTime(date).slice(0, 10);
    }

    function halfHourDateTime(value, roundUp) {
        // Preserve the wall-clock date from FullCalendar, never convert its ISO
        // offset through UTC. A tap belongs to the containing half-hour cell.
        const local = normalizeDateTime(value);
        const date = new Date(local.replace(' ', 'T') + ':00');
        date.setMinutes((roundUp ? Math.ceil : Math.floor)(date.getMinutes() / 30) * 30, 0, 0);
        return formatLocalDateTime(date);
    }

    function buildAppointmentCreateUrl(start, end, returnContext) {
        const params = new URLSearchParams({
            start: halfHourDateTime(start, false),
            end: halfHourDateTime(end, true),
        });
        return appendCalendarReturnContext('/appointments/create?' + params.toString(), returnContext);
    }

    function buildAppointmentCreateUrlForSlot(date, durationMinutes, returnContext) {
        const start = new Date(date.getTime());
        start.setMinutes(Math.floor(start.getMinutes() / 30) * 30, 0, 0);
        const duration = Math.max(30, Math.ceil((durationMinutes || 30) / 30) * 30);
        const end = new Date(start.getTime() + duration * 60 * 1000);
        return buildAppointmentCreateUrl(formatLocalDateTime(start), formatLocalDateTime(end), returnContext);
    }

    function validCalendarContext(context) {
        if (!context || !['dayGridMonth', 'listWeek', 'timeGridDay'].includes(context.view)) return false;
        if (typeof context.date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(context.date)) return false;
        const date = new Date(context.date + 'T12:00:00');
        return !Number.isNaN(date.getTime()) && buildCalendarLocalDate(date) === context.date;
    }

    function appendCalendarReturnContext(url, context) {
        if (!validCalendarContext(context)) return url;
        const [path, query = ''] = url.split('?');
        const params = new URLSearchParams(query);
        params.set('return_date', context.date);
        params.set('return_view', context.view);
        return path + '?' + params.toString();
    }

    function clearCalendarSelection(calendar) {
        if (calendar && typeof calendar.unselect === 'function') calendar.unselect();
    }

    function createCalendarSelectionGuard() {
        let suppressSelection = false;
        let releaseTimer = null;
        return {
            suppressForCurrentGesture() {
                suppressSelection = true;
                if (releaseTimer !== null) clearTimeout(releaseTimer);
                releaseTimer = null;
            },
            releaseAfterCurrentGesture() {
                if (releaseTimer !== null) clearTimeout(releaseTimer);
                releaseTimer = setTimeout(() => {
                    suppressSelection = false;
                    releaseTimer = null;
                }, 0);
            },
            isSuppressed() { return suppressSelection; },
        };
    }

    global.validCalendarContext = validCalendarContext;
    global.appendCalendarReturnContext = appendCalendarReturnContext;
    global.clearCalendarSelection = clearCalendarSelection;
    global.createCalendarSelectionGuard = createCalendarSelectionGuard;
    global.buildCalendarLocalDate = buildCalendarLocalDate;
    global.buildAppointmentCreateUrl = buildAppointmentCreateUrl;
    global.buildAppointmentCreateUrlForSlot = buildAppointmentCreateUrlForSlot;
})(typeof window !== 'undefined' ? window : globalThis);
