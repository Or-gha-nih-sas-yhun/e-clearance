(() => {
    const forms = document.querySelectorAll('form[data-secure-login]');
    const locationForms = document.querySelectorAll('form[data-client-location]');
    if (!forms.length && !locationForms.length) return;

    const rememberLocation = position => {
        locationForms.forEach(form => {
            form.querySelector('[data-client-latitude]').value = position.coords.latitude.toFixed(6);
            form.querySelector('[data-client-longitude]').value = position.coords.longitude.toFixed(6);
            form.querySelector('[data-client-accuracy]').value = Math.round(position.coords.accuracy);
        });
    };

    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(rememberLocation, () => {}, {
            enableHighAccuracy: false,
            timeout: 5000,
            maximumAge: 300000,
        });
    }

    forms.forEach(form => {
        const siteKey = form.dataset.recaptchaSiteKey || '';
        if (!siteKey) return;

        form.addEventListener('submit', event => {
            if (form.dataset.securitySubmitting === 'true') return;
            event.preventDefault();

            if (!window.grecaptcha) {
                const attempts = Number(form.dataset.recaptchaWaitAttempts || 0) + 1;
                form.dataset.recaptchaWaitAttempts = String(attempts);
                if (attempts >= 10) {
                    form.dataset.securitySubmitting = 'true';
                    form.requestSubmit();
                } else {
                    window.setTimeout(() => form.requestSubmit(), 400);
                }
                return;
            }

            window.grecaptcha.ready(() => {
                window.grecaptcha.execute(siteKey, { action: 'login' }).then(token => {
                    form.querySelector('[data-recaptcha-token]').value = token;
                    form.dataset.securitySubmitting = 'true';
                    form.requestSubmit();
                }).catch(() => {
                    form.querySelector('[data-recaptcha-token]').value = '';
                    form.dataset.securitySubmitting = 'true';
                    form.requestSubmit();
                });
            });
        });
    });
})();
