@php
    // Scanned on a phone this is a page of its own, presented as a modal. The
    // registrar's scanner loads the same URL with ?embed=1 inside the portal's
    // document viewer, which brings its own backdrop and close button — so the
    // embedded copy renders the card alone.
    $embedded = request()->boolean('embed');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Clearance Verification | MCC</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { min-height: 100vh; margin: 0; background: #eef5fb; color: #172033; }

        /* The result is presented as a modal over a dimmed backdrop, matching the
           overlays used across the portals. This page is reached by scanning the
           QR, so the backdrop is decoration rather than a page to return to —
           closing therefore leads somewhere real instead of a blank screen. */
        .verify-overlay { position: fixed; inset: 0; display: grid; place-items: center; padding: 1rem; background: rgba(19, 52, 79, .5); backdrop-filter: blur(9px); -webkit-backdrop-filter: blur(9px); }

        .verify-modal { position: relative; width: min(680px, 100%); max-height: calc(100vh - 2rem); overflow-y: auto; box-sizing: border-box; padding: 2rem; border: 1px solid rgba(255, 255, 255, .9); border-radius: 20px; background: #fff; box-shadow: 0 28px 70px rgba(15, 45, 75, .28); animation: verifyPop .22s ease-out; text-align: center; }
        @keyframes verifyPop { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }

        .verify-close { position: absolute; top: 14px; right: 14px; display: grid; width: 38px; height: 38px; place-items: center; border: 0; border-radius: 50%; color: #5d7288; background: #eef4f9; font-size: 1rem; cursor: pointer; transition: background .15s ease, color .15s ease; }
        .verify-close:hover { color: #17375d; background: #dde8f2; }
        .verify-close:focus-visible { outline: 0; box-shadow: 0 0 0 4px rgba(32, 143, 218, .22); }
        body:not(.is-embedded) .verify-modal { padding-top: 4.25rem; }

        .verify-content { width: min(100%, 600px); margin: 0 auto; }
        .verify-letterhead { display: grid; grid-template-columns: 82px minmax(0, 1fr) 82px; align-items: center; gap: 1rem; padding-bottom: 1.25rem; border-bottom: 1px solid #dce8f1; }
        .verify-logo { display: grid; width: 82px; height: 82px; place-items: center; }
        .verify-logo img { display: block; width: 76px; height: 76px; object-fit: contain; }
        .verify-heading { min-width: 0; text-align: center; }
        .school { margin: 0; color: #172f50; font-size: 1.05rem; font-weight: 800; letter-spacing: .025em; text-transform: uppercase; }
        .school-address { margin: .2rem 0 0; color: #607086; font-size: .78rem; }
        h1 { margin: .55rem 0 0; color: #172033; font-size: 1.55rem; line-height: 1.15; }
        .verification-subtitle { margin: .3rem 0 0; color: #718399; font-size: .75rem; }
        .status-wrap { margin-top: 1.25rem; text-align: center; }
        .status { display: inline-block; padding: .45rem .8rem; border-radius: 999px; font-weight: 750; background: {{ $overallStatus === 'Cleared' ? '#dff7e8' : '#fff2cc' }}; color: {{ $overallStatus === 'Cleared' ? '#17643b' : '#795600' }}; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin: 1.25rem auto 0; }
        .verify-field { padding: .85rem 1rem; border: 1px solid #dce8f1; border-radius: 12px; background: #f7fbfe; text-align: center; }
        dt { color: #607086; font-size: .75rem; font-weight: 650; } dd { margin: .3rem 0 0; color: #172033; font-weight: 750; overflow-wrap: anywhere; }
        .note { max-width: 570px; margin: 1.25rem auto 0; color: #607086; line-height: 1.55; text-align: center; }
        @media (max-width: 540px) { .verify-modal { padding: 1.5rem 1.25rem; } .verify-letterhead { grid-template-columns: 58px minmax(0, 1fr) 58px; gap: .55rem; } .verify-logo { width: 58px; height: 58px; } .verify-logo img { width: 54px; height: 54px; } .school { font-size: .82rem; } .school-address,.verification-subtitle { font-size: .68rem; } h1 { font-size: 1.25rem; } dl { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { .verify-modal { animation: none; } }

        /* Embedded: the host modal owns the backdrop, the framing and the close
           button, so only the card itself is drawn. */
        body.is-embedded { background: #fff; }
        body.is-embedded .verify-overlay { position: static; min-height: 100vh; padding: 0; background: none; backdrop-filter: none; -webkit-backdrop-filter: none; }
        body.is-embedded .verify-modal { width: min(720px, 100%); max-height: none; border: 0; border-radius: 0; box-shadow: none; animation: none; }
        @media print { body,.verify-overlay { background:#fff !important; } .verify-overlay { position:static; display:block; padding:0; } .verify-modal { width:100%; max-height:none; border:0; box-shadow:none; } .verify-close { display:none; } }
    </style>
</head>
<body class="{{ $embedded ? 'is-embedded' : '' }}">
@unless($embedded)@include('partials.app-shell-body')@endunless
<div class="verify-overlay">
    <main class="verify-modal" role="dialog" aria-modal="true" aria-labelledby="verifyTitle">
        @unless($embedded)
        <button class="verify-close" type="button" data-verify-close aria-label="Close verification"><i class="bi bi-x-lg"></i></button>
        @endunless

        <div class="verify-content">
            <header class="verify-letterhead">
                <span class="verify-logo">@if($collegeLogo)<img src="{{ $collegeLogo }}" alt="Madridejos Community College logo">@endif</span>
                <div class="verify-heading">
                    <p class="school">Madridejos Community College</p>
                    <p class="school-address">Bunakan, Madridejos, Cebu</p>
                    <h1 id="verifyTitle">Clearance verification</h1>
                    <p class="verification-subtitle">Registrar's Office &nbsp;|&nbsp; Official record validation</p>
                </div>
                <span class="verify-logo">@if($municipalityLogo)<img src="{{ $municipalityLogo }}" alt="Municipality of Madridejos seal">@endif</span>
            </header>
            <div class="status-wrap"><span class="status">{{ $overallStatus }}</span></div>
            <dl>
                <div class="verify-field"><dt>Student</dt><dd>{{ $studentName }}</dd></div>
                <div class="verify-field"><dt>Student ID</dt><dd>{{ $maskedStudentId }}</dd></div>
                <div class="verify-field"><dt>Program</dt><dd>{{ $student->program }}</dd></div>
                <div class="verify-field"><dt>Token issued</dt><dd>{{ $token->issued_at?->format('M d, Y') }}</dd></div>
            </dl>
            <p class="note">This page confirms only the current validity of the MCC e-Clearance record. It does not expose requirements, remarks, contact details, or authentication data.</p>
        </div>
    </main>
</div>

@unless($embedded)
<script>
(() => {
    // Closing a page opened by a QR scanner has nowhere to go back to.
    // window.close() only works when a script opened the tab, so the landing
    // page is the fallback rather than leaving a dismissed, empty screen.
    const close = () => {
        window.close();
        window.setTimeout(() => {
            if (! window.closed) { window.location.href = @json(route('landing')); }
        }, 120);
    };

    document.querySelector('[data-verify-close]')?.addEventListener('click', close);
    // The backdrop is deliberately not a dismiss target: a mis-tap would throw
    // away the result the person just scanned for.
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); } });
})();
</script>
@endunless
</body>
</html>
