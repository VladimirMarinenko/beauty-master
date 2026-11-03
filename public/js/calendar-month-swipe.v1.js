(function (global) {
    'use strict';

    /**
     * Enables horizontal month paging without taking vertical scrolling or taps
     * away from FullCalendar. Intended for dayGridMonth and timeGridDay views.
     */
    function enableCalendarMonthSwipe(element, calendar, options) {
        if (!element || !calendar) return function () {};

        const settings = options || {};
        const minimumSwipe = Number.isFinite(settings.minimumSwipe) ? settings.minimumSwipe : 48;
        const axisLock = Number.isFinite(settings.axisLock) ? settings.axisLock : 8;
        let gesture = null;

        function reset() {
            if (gesture && gesture.axis === 'horizontal' && typeof settings.onHorizontalEnd === 'function') {
                settings.onHorizontalEnd();
            }
            gesture = null;
        }

        function onTouchStart(event) {
            gesture = null;
            if (!['dayGridMonth', 'timeGridDay'].includes(calendar.view.type) || event.touches.length !== 1) return;

            const target = event.target;
            if (target && typeof target.closest === 'function' &&
                target.closest('button, a, input, select, textarea, [contenteditable="true"]')) return;

            const touch = event.touches[0];
            gesture = {
                x: touch.clientX,
                y: touch.clientY,
                axis: null,
                deltaX: 0,
            };
        }

        function onTouchMove(event) {
            if (!gesture) return;
            if (event.touches.length !== 1) {
                reset();
                return;
            }

            const touch = event.touches[0];
            const deltaX = touch.clientX - gesture.x;
            const deltaY = touch.clientY - gesture.y;

            if (!gesture.axis) {
                if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) < axisLock) return;
                // A tie is treated as vertical so that diagonal scrolls are not hijacked.
                gesture.axis = Math.abs(deltaX) > Math.abs(deltaY) ? 'horizontal' : 'vertical';
                if (gesture.axis === 'horizontal' && typeof settings.onHorizontalStart === 'function') {
                    settings.onHorizontalStart();
                }
            }

            if (gesture.axis !== 'horizontal') return;

            gesture.deltaX = deltaX;
            if (event.cancelable) event.preventDefault();
            // Let FullCalendar receive touchmove/touchend so it can finish and
            // clear any in-flight drag/selection state.
        }

        function onTouchEnd(event) {
            if (!gesture) return;
            const completedGesture = gesture;
            reset();

            if (completedGesture.axis !== 'horizontal') return;

            if (event.cancelable) event.preventDefault();

            const width = element.clientWidth || 0;
            const threshold = Math.max(minimumSwipe, width * 0.15);
            if (Math.abs(completedGesture.deltaX) < threshold) return;

            if (completedGesture.deltaX < 0) calendar.next();
            else calendar.prev();
        }

        element.addEventListener('touchstart', onTouchStart, { passive: true });
        element.addEventListener('touchmove', onTouchMove, { capture: true, passive: false });
        element.addEventListener('touchend', onTouchEnd, { capture: true, passive: false });
        element.addEventListener('touchcancel', reset, { capture: true });

        return function () {
            element.removeEventListener('touchstart', onTouchStart);
            element.removeEventListener('touchmove', onTouchMove, true);
            element.removeEventListener('touchend', onTouchEnd, true);
            element.removeEventListener('touchcancel', reset, true);
            reset();
        };
    }

    global.enableCalendarMonthSwipe = enableCalendarMonthSwipe;
})(typeof window !== 'undefined' ? window : globalThis);
