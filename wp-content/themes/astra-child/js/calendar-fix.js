(function() {
    console.error('VQH calendar-fix loaded:', document.currentScript && document.currentScript.src || window.location.href);
    document.documentElement.setAttribute('data-vqh-calendar-fix-loaded', 'true');

    function resolveHref(href) {
        if (!href) {
            return null;
        }
        try {
            return new URL(href, window.location.href).href;
        } catch (error) {
            return href;
        }
    }

    function handleCalendarNavClick(event) {
        var link = event.target.closest('.vqh-cal-nav');
        if (!link) {
            return;
        }
        try {
            event.preventDefault();
        } catch (e) {}
        try {
            event.stopImmediatePropagation();
        } catch (e) {}

        var href = resolveHref(link.getAttribute('href'));
        console.error('VQH cal nav click, href=', href, 'element=', link);
        console.trace('VQH cal nav stacktrace');

        if (href) {
            // small delay to make the log visible before navigation
            setTimeout(function() { window.location.assign(href); }, 10);
        }
    }

    var eventOptions = { capture: true, passive: false };
    document.addEventListener('click', handleCalendarNavClick, eventOptions);
    document.addEventListener('pointerdown', handleCalendarNavClick, eventOptions);
    document.addEventListener('mousedown', handleCalendarNavClick, eventOptions);
    document.addEventListener('touchstart', handleCalendarNavClick, eventOptions);
    document.addEventListener('touchend', handleCalendarNavClick, eventOptions);
})();