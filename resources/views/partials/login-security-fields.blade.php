<input type="hidden" name="client_latitude" data-client-latitude>
<input type="hidden" name="client_longitude" data-client-longitude>
<input type="hidden" name="client_accuracy" data-client-accuracy>
@if(($includeRecaptcha ?? true) && config('services.recaptcha.enabled'))
    <input type="hidden" name="recaptcha_token" data-recaptcha-token>
@endif
