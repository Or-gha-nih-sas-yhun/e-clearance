(() => {
    const locationForms = document.querySelectorAll('form[data-client-location]');
    if (!locationForms.length) return;

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
})();
