(() => {
    'use strict';

    const script = document.currentScript;
    const inactivityLimit = Number(script?.dataset.inactivityTimeout || 60000);
    const warningLead = Math.min(15000, Math.max(5000, Math.floor(inactivityLimit / 4)));
    const activityEvents = ['pointerdown', 'keydown', 'input', 'scroll', 'touchstart'];

    const visible = element => Boolean(
        element
        && !element.disabled
        && element.getAttribute('aria-hidden') !== 'true'
        && element.getClientRects().length
    );

    const editable = target => target instanceof HTMLElement && Boolean(
        target.closest('input, textarea, select, [contenteditable="true"]')
    );

    const searchFields = () => Array.from(document.querySelectorAll(
        'input[type="search"], input[name="search"], [data-messenger-search]'
    )).filter(visible);

    const filterFields = () => Array.from(document.querySelectorAll(
        '.filter-bar input, .filter-bar select, .evaluation-filters input, .evaluation-filters select, [data-filter]'
    )).filter(visible);

    const focusWithCue = field => {
        if (!field) return;
        field.focus({ preventScroll: false });
        if (typeof field.select === 'function' && field.value) field.select();
        field.classList.add('portal-shortcut-target');
        window.setTimeout(() => field.classList.remove('portal-shortcut-target'), 650);
    };

    const prepareKeyboardSearch = () => {
        searchFields().forEach(field => {
            field.setAttribute('aria-keyshortcuts', 'Control+K Meta+K /');
            if (!field.title) field.title = 'Search shortcut: Ctrl/Cmd+K or /';
        });

        filterFields().forEach(field => {
            if (!field.hasAttribute('aria-keyshortcuts')) field.setAttribute('aria-keyshortcuts', 'Alt+F');
            if (!field.title) field.title = 'Filter shortcut: Alt+F';
        });
    };

    document.addEventListener('keydown', event => {
        const searchShortcut = (event.ctrlKey || event.metaKey)
            && !event.altKey
            && event.key.toLowerCase() === 'k';
        const slashShortcut = event.key === '/'
            && !event.ctrlKey
            && !event.metaKey
            && !event.altKey
            && !editable(event.target);
        const filterShortcut = event.altKey
            && !event.ctrlKey
            && !event.metaKey
            && event.key.toLowerCase() === 'f';

        if (searchShortcut || slashShortcut) {
            const field = searchFields()[0];
            if (!field) return;
            event.preventDefault();
            focusWithCue(field);
            return;
        }

        if (filterShortcut) {
            const field = filterFields()[0] || searchFields()[0];
            if (!field) return;
            event.preventDefault();
            focusWithCue(field);
            return;
        }

        if (event.key === 'Escape' && searchFields().includes(document.activeElement)) {
            const field = document.activeElement;
            if (field.value) {
                field.value = '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
                event.preventDefault();
            }
        }
    });

    const initializeInactivityLogout = () => {
        const logoutForm = Array.from(document.forms).find(form => {
            if (form.method.toLowerCase() !== 'post') return false;
            try {
                return /\/logout\/?$/.test(new URL(form.action, window.location.href).pathname);
            } catch (error) {
                return false;
            }
        });

        if (!logoutForm || !Number.isFinite(inactivityLimit) || inactivityLimit < 1000) return;

        const logoutPath = new URL(logoutForm.action, window.location.href).pathname.replace(/\/$/, '');
        const activityKey = `clearancems:last-activity:${logoutPath}`;
        const lockKey = `clearancems:idle-logout:${logoutPath}`;
        let lastActivity = Date.now();
        let lastWrite = 0;
        let loggingOut = false;
        let inactivityTimer = null;
        let loginUrl = null;
        let logoutCompleted = false;

        const warning = document.createElement('aside');
        warning.className = 'portal-inactivity-warning';
        warning.setAttribute('role', 'alert');
        warning.setAttribute('aria-live', 'assertive');
        warning.innerHTML = `
            <span class="portal-inactivity-warning-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            <span class="portal-inactivity-warning-copy">
                <strong>Still working?</strong>
                <span data-inactivity-message>Your session will close soon.</span>
                <button type="button" data-stay-signed-in>Stay Signed In</button>
            </span>
        `;
        document.body.appendChild(warning);

        const timeoutOverlay = document.createElement('div');
        timeoutOverlay.className = 'portal-inactivity-modal-overlay';
        timeoutOverlay.setAttribute('aria-hidden', 'true');
        timeoutOverlay.innerHTML = `
            <section class="portal-inactivity-modal" role="alertdialog" aria-modal="true" aria-labelledby="portalInactivityTitle" aria-describedby="portalInactivityMessage" tabindex="-1">
                <div class="portal-inactivity-modal-glow" aria-hidden="true"></div>
                <span class="portal-inactivity-modal-icon" data-timeout-icon aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
                <span class="portal-inactivity-modal-eyebrow">Session security</span>
                <h2 id="portalInactivityTitle">You've been inactive for at least 1 minute</h2>
                <p id="portalInactivityMessage" data-timeout-message>For your security, we're ending your session automatically. This page will stay in place so you can clearly see why you were signed out.</p>
                <div class="portal-inactivity-modal-status" data-timeout-status data-state="pending">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <span>Ending your session securely&hellip;</span>
                </div>
                <button type="button" data-timeout-action disabled>
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <span>Ending Session&hellip;</span>
                </button>
            </section>
        `;
        document.body.appendChild(timeoutOverlay);

        const message = warning.querySelector('[data-inactivity-message]');
        const timeoutModal = timeoutOverlay.querySelector('.portal-inactivity-modal');
        const timeoutMessage = timeoutOverlay.querySelector('[data-timeout-message]');
        const timeoutStatus = timeoutOverlay.querySelector('[data-timeout-status]');
        const timeoutAction = timeoutOverlay.querySelector('[data-timeout-action]');
        const hideWarning = () => warning.classList.remove('show');

        const scheduleInactivityCheck = () => {
            window.clearTimeout(inactivityTimer);
            if (loggingOut) return;

            const remaining = inactivityLimit - (Date.now() - lastActivity);
            const delay = remaining > warningLead
                ? remaining - warningLead
                : Math.min(1000, Math.max(remaining, 0));
            inactivityTimer = window.setTimeout(checkInactivity, Math.max(1, delay));
        };

        const recordActivity = (force = false) => {
            if (loggingOut) return;
            const now = Date.now();
            lastActivity = now;
            hideWarning();
            if (force || now - lastWrite >= 1000) {
                lastWrite = now;
                try {
                    localStorage.setItem(activityKey, String(now));
                    localStorage.removeItem(lockKey);
                } catch (error) { /* storage is optional */ }
            }
            scheduleInactivityCheck();
        };

        const showTimeoutModal = () => {
            hideWarning();
            document.body.classList.add('portal-inactivity-locked');
            timeoutOverlay.classList.add('show');
            timeoutOverlay.setAttribute('aria-hidden', 'false');
            window.requestAnimationFrame(() => timeoutModal.focus({ preventScroll: true }));
        };

        const setLogoutCompleted = destination => {
            logoutCompleted = true;
            loginUrl = destination;
            timeoutMessage.textContent = 'For your security, your session was ended automatically. This page stayed in place so you could see why you were signed out.';
            timeoutStatus.dataset.state = 'complete';
            timeoutStatus.innerHTML = '<i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>You are now securely signed out.</span>';
            timeoutAction.disabled = false;
            timeoutAction.innerHTML = '<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Go to Login</span>';
            timeoutAction.focus({ preventScroll: true });
        };

        const setLogoutFailed = () => {
            timeoutMessage.textContent = 'We reached the one-minute inactivity limit, but the secure sign-out request could not finish. Use the button below to complete it.';
            timeoutStatus.dataset.state = 'error';
            timeoutStatus.innerHTML = '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Sign-out needs one more step.</span>';
            timeoutAction.disabled = false;
            timeoutAction.innerHTML = '<i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Finish Logout</span>';
            timeoutAction.focus({ preventScroll: true });
        };

        const submitLogout = async () => {
            if (loggingOut) return;
            loggingOut = true;
            window.clearTimeout(inactivityTimer);
            logoutForm.dataset.logoutConfirmed = 'true';
            logoutForm.dataset.inactivityLogout = 'true';
            showTimeoutModal();

            try {
                localStorage.setItem(lockKey, String(Date.now()));
                localStorage.removeItem(activityKey);
            } catch (error) { /* storage is optional */ }

            try {
                const response = await fetch(logoutForm.action, {
                    method: 'POST',
                    body: new FormData(logoutForm),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) throw new Error(`Logout failed with status ${response.status}`);

                const data = await response.json();
                if (!data.redirect) throw new Error('Logout response did not include a login destination');
                setLogoutCompleted(data.redirect);
            } catch (error) {
                setLogoutFailed();
            }
        };

        const checkInactivity = () => {
            if (loggingOut) return;
            let sharedActivity = lastActivity;
            try { sharedActivity = Math.max(sharedActivity, Number(localStorage.getItem(activityKey) || 0)); } catch (error) { /* use this tab */ }
            lastActivity = sharedActivity;

            const remaining = inactivityLimit - (Date.now() - lastActivity);
            if (remaining <= 0) {
                submitLogout();
                return;
            }

            if (remaining <= warningLead) {
                const seconds = Math.max(1, Math.ceil(remaining / 1000));
                message.textContent = `You will be logged out in ${seconds} second${seconds === 1 ? '' : 's'} because of inactivity.`;
                warning.classList.add('show');
            } else {
                hideWarning();
            }

            scheduleInactivityCheck();
        };

        activityEvents.forEach(eventName => {
            document.addEventListener(eventName, recordActivity, { capture: true, passive: true });
        });
        warning.querySelector('[data-stay-signed-in]').addEventListener('click', () => recordActivity(true));
        timeoutAction.addEventListener('click', () => {
            if (logoutCompleted && loginUrl) {
                window.location.assign(loginUrl);
                return;
            }

            HTMLFormElement.prototype.submit.call(logoutForm);
        });
        window.addEventListener('storage', event => {
            if (event.key === activityKey && Number(event.newValue) > lastActivity) {
                lastActivity = Number(event.newValue);
                hideWarning();
                scheduleInactivityCheck();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) checkInactivity();
        });

        recordActivity(true);
    };

    const initialize = () => {
        prepareKeyboardSearch();
        initializeInactivityLogout();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
