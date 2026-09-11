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

        .verify-modal { position: relative; width: min(520px, 100%); max-height: calc(100vh - 2rem); overflow-y: auto; box-sizing: border-box; padding: 2rem; border: 1px solid rgba(255, 255, 255, .9); border-radius: 20px; background: #fff; box-shadow: 0 28px 70px rgba(15, 45, 75, .28); animation: verifyPop .22s ease-out; }
        @keyframes verifyPop { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }

        .verify-close { position: absolute; top: 14px; right: 14px; display: grid; width: 38px; height: 38px; place-items: center; border: 0; border-radius: 50%; color: #5d7288; background: #eef4f9; font-size: 1rem; cursor: pointer; transition: background .15s ease, color .15s ease; }
        .verify-close:hover { color: #17375d; background: #dde8f2; }
        .verify-close:focus-visible { outline: 0; box-shadow: 0 0 0 4px rgba(32, 143, 218, .22); }

        h1 { margin: .35rem 0 1rem; font-size: 1.55rem; padding-right: 2.5rem; }
        .school { margin: 0; color: #607086; }
        .status { display: inline-block; padding: .45rem .8rem; border-radius: 999px; font-weight: 750; background: {{ $overallStatus === 'Cleared' ? '#dff7e8' : '#fff2cc' }}; color: {{ $overallStatus === 'Cleared' ? '#17643b' : '#795600' }}; }
        dl { display: grid; grid-template-columns: 9rem 1fr; gap: .8rem 1rem; margin: 1.5rem 0 0; }
        dt { color: #607086; } dd { margin: 0; font-weight: 650; overflow-wrap: anywhere; }
        .note { color: #607086; line-height: 1.55; }
        @media (max-width: 480px) { .verify-modal { padding: 1.5rem 1.25rem; } dl { grid-template-columns: 1fr; gap: .25rem; } dd { margin-bottom: .65rem; } }
        @media (prefers-reduced-motion: reduce) { .verify-modal { animation: none; } }
    </style>
</head>
<body>
@include('partials.app-shell-body')
<div class="verify-overlay">
    <main class="verify-modal" role="dialog" aria-modal="true" aria-labelledby="verifyTitle">
        <button class="verify-close" type="button" data-verify-close aria-label="Close verification"><i class="bi bi-x-lg"></i></button>

        <p class="school">Madridejos Community College</p>
        <h1 id="verifyTitle">Clearance verification</h1>
        <span class="status">{{ $overallStatus }}</span>
        <dl>
            <dt>Student</dt><dd>{{ $studentName }}</dd>
            <dt>Student ID</dt><dd>{{ $maskedStudentId }}</dd>
            <dt>Program</dt><dd>{{ $student->program }}</dd>
            <dt>Token issued</dt><dd>{{ $token->issued_at?->format('M d, Y') }}</dd>
        </dl>
        <p class="note">This page confirms only the current validity of the MCC e-Clearance record. It does not expose requirements, remarks, contact details, or authentication data.</p>
    </main>
</div>

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
</body>
</html>
