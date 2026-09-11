/*
 * Loading splash and pull-to-refresh.
 *
 * Both live on the website, which is also how the Android app gets them: the
 * app is a WebView over these pages, so there is one implementation rather than
 * one per platform.
 */
(() => {
    'use strict';

    const splash = document.querySelector('[data-app-splash]');
    const pull = document.querySelector('[data-app-pull]');

    /* ------------------------------------------------------------------ *
     * Splash
     * ------------------------------------------------------------------ */

    function hideSplash() {
        // Kept in the tree rather than removed, so leaving the page can bring it
        // straight back. `.is-done` makes it invisible and click-through.
        splash?.classList.add('is-done');
    }

    let navigationTimer = null;

    /**
     * Bring the splash back while the browser fetches the next page.
     *
     * A page that never navigates -- a form the page handles itself, a click a
     * script cancels -- would otherwise leave the screen covered for good, so
     * the splash takes itself away again if nothing has happened shortly after.
     */
    function showSplashFor(message) {
        if (!splash) { return; }

        const note = splash.querySelector('[data-app-splash-note]');
        if (note && message) { note.textContent = message; }
        splash.classList.remove('is-done');

        window.clearTimeout(navigationTimer);
        navigationTimer = window.setTimeout(hideSplash, 2500);
    }

    if (splash) {
        if (document.readyState === 'complete') {
            hideSplash();
        } else {
            window.addEventListener('load', hideSplash, { once: true });
        }
        // A stalled asset must never leave the screen covered.
        window.setTimeout(hideSplash, 6000);
    }

    // Coming back via the back button restores a cached page with the splash
    // still attached, so it has to be cleared again.
    window.addEventListener('pageshow', event => { if (event.persisted) { hideSplash(); } });

    /* ------------------------------------------------------------------ *
     * Pull to refresh
     * ------------------------------------------------------------------ */

    const THRESHOLD = 70;      // drag needed before a release refreshes
    const MAX_PULL = 110;      // how far the indicator can travel
    const RESISTANCE = 0.5;    // drag feels weighted rather than 1:1

    let startY = 0;
    let distance = 0;
    let tracking = false;
    let scroller = null;

    /** The element that actually scrolls under this touch. */
    function scrollerFor(target) {
        let node = target instanceof Element ? target : null;

        while (node && node !== document.body) {
            const style = window.getComputedStyle(node);
            const scrolls = /(auto|scroll|overlay)/.test(style.overflowY);

            if (scrolls && node.scrollHeight > node.clientHeight) {
                return node;
            }
            node = node.parentElement;
        }

        // The portals set `body { overflow: hidden }` and scroll `.main`, so
        // falling back to the document only suits pages that scroll normally.
        return document.scrollingElement || document.documentElement;
    }

    function setPull(px, settling) {
        if (!pull) { return; }
        pull.classList.toggle('is-settling', Boolean(settling));
        pull.style.transform = `translate(-50%, ${Math.min(px, MAX_PULL) - 50}px)`;
        pull.style.opacity = px > 8 ? String(Math.min(px / THRESHOLD, 1)) : '0';
        pull.classList.toggle('is-armed', px >= THRESHOLD);
    }

    function resetPull() {
        tracking = false;
        distance = 0;
        setPull(0, true);
        if (pull) { pull.classList.remove('is-armed'); }
    }

    if (pull && 'ontouchstart' in window) {
        document.addEventListener('touchstart', event => {
            if (event.touches.length !== 1) { return; }

            scroller = scrollerFor(event.target);
            // Only from a resting position at the very top.
            if (scroller.scrollTop > 0) { tracking = false; return; }

            startY = event.touches[0].clientY;
            distance = 0;
            tracking = true;
        }, { passive: true });

        document.addEventListener('touchmove', event => {
            if (!tracking || event.touches.length !== 1) { return; }

            const delta = event.touches[0].clientY - startY;

            // An upward drag, or a scroller that has moved off the top, is an
            // ordinary scroll and must be left alone.
            if (delta <= 0 || (scroller && scroller.scrollTop > 0)) {
                if (distance > 0) { resetPull(); }
                tracking = false;
                return;
            }

            distance = delta * RESISTANCE;
            setPull(distance, false);

            // Only swallowed once this is clearly a pull, so short drags still
            // scroll and the browser's own overscroll does not fight it.
            if (distance > 6 && event.cancelable) { event.preventDefault(); }
        }, { passive: false });

        document.addEventListener('touchend', () => {
            if (!tracking) { return; }

            if (distance >= THRESHOLD) {
                pull.classList.add('is-refreshing');
                setPull(THRESHOLD, true);
                showSplashFor('Refreshing…');
                window.location.reload();
                return;
            }

            resetPull();
        }, { passive: true });

        document.addEventListener('touchcancel', resetPull, { passive: true });
    }

    /* ------------------------------------------------------------------ *
     * Show the splash again when leaving for another page
     * ------------------------------------------------------------------ */

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');

        if (!link || link.target === '_blank' || link.hasAttribute('download')) { return; }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) { return; }

        // Something else already cancelled this click, so nothing will load.
        if (event.defaultPrevented || link.closest('[data-no-splash]')) { return; }

        const href = link.getAttribute('href') || '';
        // In-page anchors and script hrefs do not navigate.
        if (href === '' || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) { return; }

        let destination;
        try { destination = new URL(link.href, window.location.href); } catch { return; }
        if (destination.origin !== window.location.origin) { return; }

        showSplashFor('Loading…');
    });

    document.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'dialog') { return; }

        // The form's own listener runs before this one, so a handler that calls
        // preventDefault() -- the chat composer, every in-page AJAX form -- has
        // already marked the event by now. Those never navigate, so covering the
        // screen would strand the page.
        if (event.defaultPrevented || form.closest('[data-no-splash]')) { return; }

        showSplashFor('Working…');
    });
})();
