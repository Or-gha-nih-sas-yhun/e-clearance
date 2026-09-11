@php
    // The splash is for arriving at the system — signing in, a scanned
    // verification link, the landing page. Once someone is working inside a
    // portal it would interrupt every page they open, so it is not rendered
    // at all for a signed-in session. Pull-to-refresh stays everywhere.
    $portalSignedIn = collect(['student', 'instructor', 'office', 'treasurer', 'registrar', 'admin'])
        ->contains(fn (string $guard) => auth()->guard($guard)->check());
@endphp

{{-- Loading splash and pull-to-refresh.

     Both are plain web features, which is how the Android app gets them too —
     it is a WebView over these pages, so there is one implementation rather
     than one per platform. Not included on the printed clearance form, which
     Dompdf also renders. --}}
@unless($portalSignedIn)
<div class="app-splash" data-app-splash role="status" aria-live="polite">
    <div class="app-splash-inner">
        <img class="app-splash-logo" src="{{ asset('images/icon-192.png') }}" alt="">
        <span class="app-splash-name">MCC e-Clearance</span>
        <span class="app-splash-note" data-app-splash-note>Loading…</span>
        <span class="app-splash-bar"><span></span></span>
    </div>
</div>
@endunless

<div class="app-pull" data-app-pull aria-hidden="true"><i class="bi bi-arrow-down"></i></div>

<script src="{{ asset('js/app-shell.js') }}" defer></script>
