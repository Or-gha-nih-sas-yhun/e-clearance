<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Clearance Verification | MCC</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #eef5fb; color: #172033; }
        main { width: min(520px, calc(100% - 2rem)); padding: 2rem; box-sizing: border-box; border: 1px solid #d8e4ef; border-radius: 20px; background: #fff; box-shadow: 0 20px 55px rgba(26, 58, 91, .12); }
        .status { display: inline-block; padding: .45rem .8rem; border-radius: 999px; font-weight: 750; background: {{ $overallStatus === 'Cleared' ? '#dff7e8' : '#fff2cc' }}; color: {{ $overallStatus === 'Cleared' ? '#17643b' : '#795600' }}; }
        dl { display: grid; grid-template-columns: 9rem 1fr; gap: .8rem 1rem; margin: 1.5rem 0 0; }
        dt { color: #607086; } dd { margin: 0; font-weight: 650; overflow-wrap: anywhere; }
        p { color: #607086; line-height: 1.55; }
        @media (max-width: 480px) { dl { grid-template-columns: 1fr; gap: .25rem; } dd { margin-bottom: .65rem; } }
    </style>
</head>
<body>
@include('partials.app-shell-body')
<main>
    <p>Mayor Carlos P. Garcia College</p>
    <h1>Clearance verification</h1>
    <span class="status">{{ $overallStatus }}</span>
    <dl>
        <dt>Student</dt><dd>{{ $studentName }}</dd>
        <dt>Student ID</dt><dd>{{ $maskedStudentId }}</dd>
        <dt>Program</dt><dd>{{ $student->program }}</dd>
        <dt>Token issued</dt><dd>{{ $token->issued_at?->format('M d, Y') }}</dd>
    </dl>
    <p>This page confirms only the current validity of the MCC e-Clearance record. It does not expose requirements, remarks, signatures, contact details, or authentication data.</p>
</main>
</body>
</html>
