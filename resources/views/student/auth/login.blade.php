<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#075bea">
    <title>Student Login | MCC Clearance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/auth-form-validation.css') }}" rel="stylesheet">
    <style>
        :root {
            --blue: #075bea;
            --blue-dark: #07215c;
            --ink: #071b50;
            --muted: #49638c;
            --panel: rgba(247, 251, 255, .89);
        }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            margin: 0;
            height: 100vh;
            height: 100dvh;
            color: var(--ink);
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #b9ddfa url("{{ asset('images/mcc-campus.jpg') }}") center / cover no-repeat fixed;
        }

        button, input { font: inherit; }

        .page {
            position: relative;
            isolation: isolate;
            display: grid;
            grid-template-columns: minmax(340px, 1.1fr) minmax(440px, .9fr);
            gap: clamp(38px, 6vw, 100px);
            align-items: center;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            min-height: 0;
            padding: clamp(20px, 3.5vw, 54px);
            overflow: hidden;
        }

        .page::before {
            position: absolute;
            z-index: -2;
            inset: 0;
            content: "";
            background:
                linear-gradient(90deg, rgba(224, 243, 255, .97) 0%, rgba(219, 240, 255, .88) 37%, rgba(207, 235, 255, .58) 61%, rgba(210, 237, 255, .35) 100%);
        }

        .page::after {
            position: absolute;
            z-index: -1;
            top: -44vw;
            right: -23vw;
            width: 68vw;
            height: 68vw;
            content: "";
            border: 2px solid rgba(255, 255, 255, .65);
            border-radius: 50%;
            box-shadow: 0 0 0 28px rgba(255, 255, 255, .09), 0 0 0 31px rgba(255, 255, 255, .28);
        }

        .intro { max-width: 680px; }

        .brand {
            display: flex;
            align-items: center;
            gap: 22px;
            margin-bottom: clamp(20px, 3vh, 38px);
        }

        .brand-logo {
            width: clamp(88px, 8vw, 126px);
            height: clamp(88px, 8vw, 126px);
            flex: 0 0 auto;
            object-fit: contain;
            padding: 8px;
            border: 3px solid rgba(255, 255, 255, .92);
            border-radius: 50%;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 7px 18px rgba(5, 57, 137, .18);
        }

        .brand-title { margin: 0; font-size: clamp(1.55rem, 2vw, 2.25rem); line-height: 1.18; font-weight: 800; }
        .brand-title span { display: block; margin-top: 4px; color: var(--blue); }
        .tagline { margin: 10px 0 0; color: #244979; font-size: clamp(1rem, 1.1vw, 1.15rem); }

        .intro h2 {
            max-width: 610px;
            margin: 0 0 14px;
            font-size: clamp(1.85rem, 2.7vw, 2.9rem);
            line-height: 1.12;
            letter-spacing: -.045em;
            font-weight: 800;
        }

        .intro-copy { max-width: 520px; margin: 0 0 20px; color: #183c6e; font-size: clamp(1rem, 1.12vw, 1.16rem); line-height: 1.5; }

        .benefits { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-width: 620px; }
        .benefit { display: flex; align-items: center; gap: 13px; padding: 14px 16px; border: 1px solid rgba(255,255,255,.72); border-radius: 17px; background: rgba(246, 251, 255, .68); box-shadow: 0 8px 24px rgba(41, 106, 167, .09); backdrop-filter: blur(10px); }
        .benefit-icon { display: grid; width: 42px; height: 42px; flex: 0 0 auto; place-items: center; color: #fff; border-radius: 13px; background: linear-gradient(145deg, #35a9ff, #075bea); box-shadow: 0 7px 17px rgba(7, 91, 234, .24); font-size: 1.25rem; }
        .benefit strong { display: block; font-size: 1rem; }
        .benefit small { display: block; margin-top: 2px; color: var(--muted); font-size: .82rem; line-height: 1.35; }

        .login-wrap { width: min(100%, 610px); justify-self: end; }

        .login-card {
            position: relative;
            display: flex;
            min-height: min(68vh, 650px);
            padding: clamp(28px, 3.2vw, 48px);
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.9);
            border-radius: 36px;
            background: var(--panel);
            box-shadow: 0 28px 75px rgba(20, 74, 134, .25), inset 0 1px rgba(255,255,255,.9);
            backdrop-filter: blur(22px);
        }

        .login-card::before { position: absolute; top: -110px; right: -80px; width: 280px; height: 280px; content: ""; border: 1px solid rgba(59,143,233,.12); border-radius: 50%; box-shadow: 0 0 0 18px rgba(59,143,233,.035), 0 0 0 36px rgba(59,143,233,.03); }
        .login-heading { position: relative; text-align: center; }
        .user-badge { display: grid; width: 82px; height: 82px; margin: 0 auto 16px; place-items: center; color: #fff; border: 10px solid rgba(255,255,255,.58); border-radius: 50%; background: linear-gradient(145deg, #38adff, #075bea); box-shadow: 0 14px 30px rgba(32,123,221,.26); font-size: 2rem; }
        .login-heading h1 { margin: 0; font-size: clamp(1.75rem, 2.3vw, 2.25rem); letter-spacing: -.04em; }
        .login-heading p { margin: 8px 0 22px; color: var(--muted); font-size: 1.04rem; }

        .alert { position:relative; display: flex; gap: 9px; align-items: flex-start; margin-bottom: 18px; padding: 12px 14px; color: #a61b2b; border: 1px solid #ffc5cc; border-radius: 13px; background: #fff1f3; font-size: .86rem; }
        .alert.success { color:#12613a; border-color:#b9ebcf; background:#edfff4; }
        .alert.info { color:#14547d; border-color:#b9ddf5; background:#edf8ff; }
        .field { position: relative; margin-bottom: 16px; }
        .field > i { position: absolute; z-index: 1; top: 50%; left: 20px; color: var(--blue); font-size: 1.2rem; transform: translateY(-50%); pointer-events: none; }
        .field input { width: 100%; height: 58px; padding: 0 55px; color: var(--ink); border: 1px solid #ccdbed; border-radius: 16px; outline: none; background: rgba(255,255,255,.82); box-shadow: 0 8px 18px rgba(41,98,159,.06); font-size: 1.05rem; transition: border-color .2s, box-shadow .2s, background .2s; }
        .field input::placeholder { color: #6c80a2; }
        .field input:focus { border-color: #4791ff; background: #fff; box-shadow: 0 0 0 4px rgba(7,91,234,.1), 0 9px 20px rgba(41,98,159,.08); }
        .password-toggle { position: absolute; top: 50%; right: 10px; display: grid; width: 44px; height: 44px; padding: 0; place-items: center; color: #637a9c; border: 0; border-radius: 11px; background: transparent; transform: translateY(-50%); cursor: pointer; }
        .password-toggle:hover, .password-toggle:focus-visible { color: var(--blue); background: #edf5ff; outline: none; }

        .form-options { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin: 14px 2px 20px; font-size: 1rem; }
        .remember { display: flex; align-items: center; gap: 9px; cursor: pointer; }
        .remember input { width: 18px; height: 18px; margin: 0; accent-color: var(--blue); }
        .forgot { padding:0; color: var(--blue); border:0; outline:0; background:transparent; text-decoration: none; font-weight: 500; cursor:pointer; }
        .forgot:hover { text-decoration: underline; }

        .login-button { display: flex; width: 100%; min-height: 56px; align-items: center; justify-content: center; gap: 11px; color: #fff; border: 0; border-radius: 15px; background: linear-gradient(135deg, #079cff, #075bea 64%, #1546dc); box-shadow: 0 12px 24px rgba(7,91,234,.26); font-size: 1rem; font-weight: 700; cursor: pointer; transition: transform .2s, box-shadow .2s; }
        .captcha-box { margin: 2px 0 14px; padding: 12px; border: 1px solid #c9dcef; border-radius: 15px; background: rgba(239,247,255,.76); }
        .captcha-label { display: block; margin: 0 0 9px; color: #34506f; font-size: .78rem; font-weight: 700; }
        .captcha-row { display: grid; grid-template-columns: 190px 42px 1fr; gap: 8px; align-items: center; }
        .captcha-image { width: 190px; height: 64px; border: 1px solid #b9cee5; border-radius: 11px; background: #eef7ff; }
        .captcha-refresh { display: grid; width: 42px; height: 42px; padding: 0; place-items: center; color: #075bea; border: 1px solid #bdd3ea; border-radius: 11px; background: #fff; cursor: pointer; }
        .captcha-answer { width: 100%; height: 48px; padding: 0 12px; text-align: center; text-transform: uppercase; letter-spacing: .16em; border: 1px solid #ccdbed; border-radius: 11px; outline: none; background: #fff; font-weight: 800; }
        .login-button:hover { transform: translateY(-2px); box-shadow: 0 16px 28px rgba(7,91,234,.31); }
        .login-button:active { transform: translateY(0); }
        .landing-button { display:flex; width:100%; min-height:48px; margin-top:11px; align-items:center; justify-content:center; gap:8px; color:#254f83; border:1px solid rgba(86,135,192,.28); border-radius:14px; background:rgba(246,251,255,.68); font-size:.94rem; font-weight:700; text-decoration:none; transition:transform .2s, border-color .2s, background .2s, box-shadow .2s; }
        .landing-button:hover { color:var(--blue); border-color:rgba(7,91,234,.32); background:#fff; box-shadow:0 8px 18px rgba(41,98,159,.09); transform:translateY(-1px); }
        .landing-button:focus-visible { outline:3px solid rgba(7,91,234,.22); outline-offset:2px; }
        .auth-panel[hidden] { display:none; }
        .auth-panel.panel-enter { animation:panelEnter .32s cubic-bezier(.22,1,.36,1); }
        @keyframes panelEnter { from { opacity:0; transform:translateX(14px); } to { opacity:1; transform:translateX(0); } }
        .recovery-steps { display:grid; grid-template-columns:repeat(3,1fr); gap:7px; margin:0 0 18px; }
        .recovery-step { height:5px; overflow:hidden; border-radius:999px; background:#d9e6f4; }
        .recovery-step.active { background:linear-gradient(90deg,#32a9ff,#075bea); box-shadow:0 3px 9px rgba(7,91,234,.18); }
        .recovery-note { margin:-4px 0 17px; color:var(--muted); text-align:center; font-size:.86rem; line-height:1.5; }
        .recovery-note strong { color:#193f70; }
        .code-field input { padding-right:20px; text-align:center; letter-spacing:.42em; font-size:1.35rem; font-weight:800; }
        .code-field > i { display:none; }
        .password-hint { margin:-7px 4px 15px; color:#607795; font-size:.77rem; line-height:1.45; }
        .recovery-footer { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:8px 14px; margin-top:15px; }
        .recovery-footer form { margin:0; }
        .text-action { padding:5px; color:var(--blue); border:0; outline:0; background:transparent; font-size:.86rem; font-weight:700; cursor:pointer; }
        .text-action:hover { text-decoration:underline; }
        .text-action.muted { color:#60748e; }
        .register-prompt { margin:14px 0 0; color:var(--muted); text-align:center; font-size:.86rem; }
        .register-prompt .text-action { padding:0 0 0 3px; }
        .text-action.muted { color:#60748e; }
        .back-button { display:flex; width:100%; min-height:48px; margin-top:11px; align-items:center; justify-content:center; gap:8px; color:#34506f; border:0; border-radius:14px; background:rgba(230,240,249,.76); font-weight:700; cursor:pointer; }
        .back-button:hover { color:var(--blue); background:#e6f3ff; }
        .help { margin: 20px 0 0; color: var(--muted); text-align: center; font-size: .96rem; }
        .help a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .copyright { margin: 14px 0 0; color: #31557f; text-align: center; font-size: .86rem; }

        .quote-card {
            display: flex;
            width: min(100%, 435px);
            margin-top: 22px;
            padding: 13px 22px;
            align-items: center;
            gap: 14px;
            color: #173866;
            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 999px;
            background: rgba(244, 250, 255, .76);
            box-shadow: 0 9px 22px rgba(41, 106, 167, .1);
            backdrop-filter: blur(12px);
            font-size: .86rem;
            line-height: 1.45;
        }

        .quote-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            color: #fff;
            border-radius: 50%;
            background: linear-gradient(145deg, #3eb1ff, #075bea);
        }

        .divider {
            display: flex;
            margin: 18px 0 14px;
            align-items: center;
            gap: 12px;
            color: #506b91;
            font-size: .78rem;
            white-space: nowrap;
        }

        .divider::before, .divider::after { width: 100%; height: 1px; content: ""; background: #bfd2e8; }
        .social-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .social-button { display: flex; min-height: 52px; align-items: center; justify-content: center; gap: 10px; color: var(--ink); border: 1px solid #c4d7ed; border-radius: 13px; background: rgba(255,255,255,.72); font-weight: 700; cursor: not-allowed; }
        .social-mark { font-size: 1.25rem; font-weight: 800; }
        .social-mark.google { color: #4285f4; }
        .social-mark.microsoft { display: grid; grid-template-columns: 7px 7px; grid-template-rows: 7px 7px; gap: 2px; }
        .social-mark.microsoft i:nth-child(1) { background: #f35325; }
        .social-mark.microsoft i:nth-child(2) { background: #81bc06; }
        .social-mark.microsoft i:nth-child(3) { background: #05a6f0; }
        .social-mark.microsoft i:nth-child(4) { background: #ffba08; }

        @media (max-width: 940px) {
            body { background-attachment: scroll; }
            .page { display: flex; flex-direction: column; justify-content: center; gap: 18px; padding: 18px 20px; }
            .intro { max-width: 610px; margin: 0 auto; text-align: center; }
            .brand { justify-content: center; margin-bottom: 0; text-align: left; }
            .intro h2, .intro-copy, .benefits { display: none; }
            .login-wrap { width: min(100%, 520px); }
            .login-card { min-height: min(68vh, 570px); }
        }

        @media (max-width: 560px) {
            .page { gap: 12px; padding: 12px 14px; }
            .brand { gap: 12px; }
            .brand-logo { width: 76px; height: 76px; padding: 5px; }
            .brand-title { font-size: 1.48rem; }
            .tagline { margin-top: 5px; font-size: .86rem; }
            .intro h2 { font-size: 2rem; }
            .intro-copy { font-size: .92rem; }
            .benefits { grid-template-columns: 1fr; text-align: left; }
            .login-card { min-height: min(70vh, 535px); padding: 20px 18px; border-radius: 25px; }
            .user-badge { width: 64px; height: 64px; margin-bottom: 10px; border-width: 8px; font-size: 1.5rem; }
            .login-heading h1 { font-size: 1.55rem; }
            .login-heading p { margin: 5px 0 15px; font-size: .92rem; }
            .field { margin-bottom: 11px; }
            .field input { height: 52px; }
            .form-options { margin: 11px 2px 15px; }
            .login-button { min-height: 50px; }
            .captcha-row { grid-template-columns: 1fr 42px; }
            .captcha-image { width: 100%; object-fit: cover; }
            .captcha-answer { grid-column: 1 / -1; }
            .help { margin-top: 14px; }
            .copyright { margin-top: 8px; }
            .form-options { align-items: flex-start; }
            .copyright { line-height: 1.5; }
        }

        @media (max-height: 690px) and (min-width: 941px) {
            .brand { margin-bottom: 16px; }
            .brand-logo { width: 82px; height: 82px; }
            .intro h2 { font-size: 2.35rem; }
            .benefit { padding: 9px 12px; }
            .benefit-icon { width: 36px; height: 36px; }
            .login-card { padding: 24px 34px; }
            .user-badge { width: 62px; height: 62px; margin-bottom: 9px; border-width: 8px; font-size: 1.5rem; }
            .login-heading h1 { font-size: 1.8rem; }
            .login-heading p { margin: 5px 0 14px; }
            .field { margin-bottom: 10px; }
            .field input { height: 50px; }
            .form-options { margin: 10px 2px 14px; }
            .login-button { min-height: 50px; }
            .help { margin-top: 12px; }
            .copyright { margin-top: 8px; }
        }

        @media (max-height: 620px) {
            .tagline, .help, .copyright, .quote-card, .divider, .social-row { display: none; }
            .page { gap: 8px; padding-top: 8px; padding-bottom: 8px; }
            .brand-logo { width: 54px; height: 54px; }
            .login-card { min-height: auto; padding-top: 16px; padding-bottom: 16px; }
        }

        /* Reference layout proportions */
        @media (min-width: 941px) {
            .page { grid-template-columns: minmax(500px, 1.16fr) minmax(470px, .84fr); gap: clamp(35px, 4.3vw, 72px); align-items: start; padding: clamp(52px, 7vh, 76px) clamp(42px, 6.2vw, 100px) 28px; }
            .intro { max-width: 670px; margin-top: 0; align-self: center; }
            .brand { margin-bottom: clamp(22px, 3.1vh, 34px); }
            .brand-logo { width: clamp(112px, 8vw, 140px); height: clamp(112px, 8vw, 140px); }
            .brand-title { font-size: clamp(2.05rem, 2.5vw, 2.75rem); }
            .intro h2 { max-width: 500px; font-size: clamp(1.7rem, 2.15vw, 2.3rem); }
            .intro-copy { max-width: 440px; }
            .benefits { display: flex; width: 305px; flex-direction: column; gap: 9px; }
            .benefit { min-height: 64px; padding: 9px 12px; }
            .benefit-icon { width: 44px; height: 44px; }
            .login-wrap { width: min(100%, 525px); margin-top: 0; justify-self: end; align-self: center; }
            .login-card { padding: clamp(26px, 3vh, 40px) clamp(30px, 3vw, 44px); border-radius: 30px; }
        }

        @media (max-width: 940px) {
            .quote-card { display: none; }
            .divider { margin: 13px 0 10px; }
            .social-button { min-height: 45px; }
        }

        @media (max-width: 560px) {
            .divider, .social-row { display: none; }
        }

        @media (max-height: 850px) and (min-width: 941px) {
            .page { padding-top: 28px; padding-bottom: 28px; }
            .brand { margin-bottom: 18px; }
            .brand-logo { width: 104px; height: 104px; }
            .brand-title { font-size: 2rem; }
            .intro h2 { margin-bottom: 10px; font-size: 1.85rem; }
            .intro-copy { margin-bottom: 14px; line-height: 1.4; }
            .benefit { min-height: 53px; padding: 6px 10px; }
            .benefit-icon { width: 38px; height: 38px; }
            .quote-card { margin-top: 13px; padding-top: 9px; padding-bottom: 9px; }
            .login-card { padding-top: 27px; padding-bottom: 27px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="intro" aria-labelledby="system-title">
            <div class="brand">
                <img class="brand-logo" src="{{ asset('images/mcc-logo.png') }}" alt="Madridejos Community College logo">
                <div>
                    <h1 class="brand-title" id="system-title">Madridejos Community College <span>Clearance System</span></h1>
                    <p class="tagline">Streamlined. Secure. Smart.</p>
                </div>
            </div>

            <h2>Manage your clearance with ease and efficiency.</h2>
            <p class="intro-copy">A digital solution for students to process clearance requests faster, track every approval, and stay informed.</p>

            <div class="benefits" aria-label="System benefits">
                <div class="benefit"><span class="benefit-icon"><i class="bi bi-shield-check"></i></span><span><strong>Secure &amp; Reliable</strong><small>Your student data stays protected.</small></span></div>
                <div class="benefit"><span class="benefit-icon"><i class="bi bi-clock"></i></span><span><strong>Efficient Process</strong><small>Save time and reduce paperwork.</small></span></div>
                <div class="benefit"><span class="benefit-icon"><i class="bi bi-bar-chart-line"></i></span><span><strong>Transparent Tracking</strong><small>Follow your clearance in real time.</small></span></div>
                <div class="benefit"><span class="benefit-icon"><i class="bi bi-phone"></i></span><span><strong>Accessible Anywhere</strong><small>Use the system on any device.</small></span></div>
            </div>

            <div class="quote-card">
                <span class="quote-icon"><i class="bi bi-quote" aria-hidden="true"></i></span>
                <span>“Excellence in service, integrity in process.”<br>&mdash; MCC Clearance System</span>
            </div>
        </section>

        <section class="login-wrap" aria-labelledby="login-title">
            <div class="login-card">
                @php
                    $activePanel = in_array($recoveryStep ?? 'login', ['email', 'code', 'reset'], true) ? $recoveryStep : 'login';
                    // Self-registration: 'code' means a registration code is awaiting entry.
                    $registrationState = session('student_registration');
                    if (is_array($registrationState) && ($registrationState['stage'] ?? null) === 'code') {
                        $activePanel = 'register-code';
                    } elseif (old('registration_action') === 'account') {
                        $activePanel = 'register-account';
                    }
                    // Set once the password is accepted from a browser this student has not verified before.
                    $loginChallenge = session('login_challenge_student');
                    if (is_array($loginChallenge)) $activePanel = 'device-otp';
                    $captchaRequired = session('login_security.captcha.student', false);
                    $emailParts = str_contains($recoveryEmail ?? '', '@') ? explode('@', $recoveryEmail, 2) : [];
                    $maskedEmail = count($emailParts) === 2
                        ? substr($emailParts[0], 0, min(2, strlen($emailParts[0]))) . str_repeat('•', max(3, strlen($emailParts[0]) - 2)) . '@' . $emailParts[1]
                        : ($recoveryEmail ?? '');
                    $panelHeadings = [
                        'login' => ['icon' => 'bi-person-lock', 'title' => 'Welcome Back!', 'subtitle' => 'Sign in to continue to your student account.'],
                        'email' => ['icon' => 'bi-envelope-check', 'title' => 'Recover Password', 'subtitle' => 'Verify the email registered to your student account.'],
                        'code' => ['icon' => 'bi-shield-check', 'title' => 'Check Your Email', 'subtitle' => 'Enter the six-digit verification code we sent.'],
                        'reset' => ['icon' => 'bi-key', 'title' => 'Create New Password', 'subtitle' => 'Choose a strong new password for your account.'],
                        'device-otp' => ['icon' => 'bi-envelope-shield', 'title' => 'Verify This Device', 'subtitle' => 'Enter the one-time code sent to your registered email.'],
                        'register-account' => ['icon' => 'bi-person-plus', 'title' => 'Register Account', 'subtitle' => 'Confirm the Microsoft account issued to you by the college.'],
                        'register-code' => ['icon' => 'bi-shield-check', 'title' => 'Check Your Email', 'subtitle' => 'Enter the six-digit registration code we sent.'],
                    ];
                    $activeHeading = $panelHeadings[$activePanel];
                @endphp

                <div class="login-heading">
                    <div class="user-badge" aria-hidden="true"><i id="auth-heading-icon" class="bi {{ $activeHeading['icon'] }}"></i></div>
                    <h1 id="login-title">{{ $activeHeading['title'] }}</h1>
                    <p id="auth-heading-copy">{{ $activeHeading['subtitle'] }}</p>
                </div>

                @if (session('status'))
                    <div class="alert success" role="status"><i class="bi bi-check-circle"></i><span>{{ session('status') }}</span></div>
                @endif

                @if (session('recovery_status'))
                    <div class="alert info" role="status"><i class="bi bi-info-circle"></i><span>{{ session('recovery_status') }}</span></div>
                @endif

                @if (session('registration_status'))
                    <div class="alert info" role="status"><i class="bi bi-info-circle"></i><span>{{ session('registration_status') }}</span></div>
                @endif

                @if ($errors->any())
                    <div class="alert" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ $errors->first() }}</span></div>
                @endif

                <div class="auth-panel" id="login-panel" data-panel="login" @if($activePanel !== 'login') hidden @endif>
                    <form method="POST" action="{{ route('student.login.submit') }}">
                        @csrf
                        <div class="field">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <label for="student_id" hidden>Student ID</label>
                            <input type="text" name="student_id" id="student_id" value="{{ old('student_id') }}" placeholder="Student ID" autocomplete="username" maxlength="50" data-validation-label="Student ID" @if($activePanel === 'login') autofocus @endif required @error('student_id') aria-invalid="true" @enderror>
                        </div>
                        <div class="field">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <label for="password" hidden>Password</label>
                            <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" minlength="8" maxlength="128" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-validation-label="Password" data-validation-rule="strong-password" required @error('password') aria-invalid="true" @enderror>
                            <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                        @if($captchaRequired)
                            <div class="captcha-box">
                                <label class="captcha-label" for="captcha-answer">Security check: enter the five characters shown</label>
                                <div class="captcha-row">
                                    <img class="captcha-image" data-captcha-image src="{{ route('auth.captcha', 'student') }}" alt="Five-character security code">
                                    <button class="captcha-refresh" type="button" data-captcha-refresh aria-label="Load a new security code"><i class="bi bi-arrow-clockwise"></i></button>
                                    <input class="captcha-answer" type="text" name="captcha_answer" id="captcha-answer" maxlength="5" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                                </div>
                            </div>
                        @endif
                        <div class="form-options">
                            <label class="remember" for="remember"><input type="checkbox" name="remember" id="remember" @checked(old('remember'))><span>Remember me</span></label>
                            <button class="forgot" type="button" data-show-auth-panel="email">Forgot password?</button>
                        </div>
                        <button type="submit" class="login-button"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Log In</span></button>
                    </form>
                    <p class="register-prompt">No account yet? <button type="button" class="text-action" data-show-auth-panel="register-account">Register your account</button></p>
                    @unless(str_contains((string) request()->userAgent(), 'MCCStudentAndroid/'))
                        <a href="{{ route('landing') }}" class="landing-button"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to Landing Page</span></a>
                    @endunless
                </div>

                {{-- Registration step 1: prove you own a listed Microsoft account. --}}
                <div class="auth-panel" id="register-account-panel" data-panel="register-account" @if($activePanel !== 'register-account') hidden @endif>
                    <p class="recovery-note">Enter the Microsoft account the college issued you. We will email a six-digit code to confirm it is yours.</p>
                    <form method="POST" action="{{ route('student.register.send-code') }}">
                        @csrf
                        <div class="field">
                            <i class="bi bi-microsoft" aria-hidden="true"></i>
                            <label for="ms_account" hidden>Microsoft account</label>
                            <input type="email" name="ms_account" id="ms_account" value="{{ old('ms_account') }}" placeholder="Microsoft account (e.g. name@mcc.edu.ph)" autocomplete="email" maxlength="150" data-validation-label="Microsoft account" required>
                        </div>
                        <button type="submit" class="login-button"><i class="bi bi-send" aria-hidden="true"></i><span>Send Registration Code</span></button>
                    </form>
                    <button type="button" class="back-button" data-show-auth-panel="login"><i class="bi bi-arrow-left"></i> Back to Login</button>
                </div>

                {{-- Registration step 2: enter the emailed code. --}}
                @if (is_array($registrationState) && ($registrationState['stage'] ?? null) === 'code')
                    @php
                        $regEmail = (string) ($registrationState['ms_account'] ?? '');
                        $regParts = str_contains($regEmail, '@') ? explode('@', $regEmail, 2) : [];
                        $regMasked = count($regParts) === 2
                            ? substr($regParts[0], 0, min(2, strlen($regParts[0]))) . str_repeat('•', max(3, strlen($regParts[0]) - 2)) . '@' . $regParts[1]
                            : $regEmail;
                    @endphp
                    <div class="auth-panel" id="register-code-panel" data-panel="register-code" @if($activePanel !== 'register-code') hidden @endif>
                        <p class="recovery-note">Code sent to <strong>{{ $regMasked }}</strong>. It expires after 10 minutes.</p>
                        <form method="POST" action="{{ route('student.register.verify-code') }}">
                            @csrf
                            <div class="field code-field">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                <label for="registration_code" hidden>Six-digit registration code</label>
                                <input type="text" inputmode="numeric" name="verification_code" id="registration_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Registration code" data-validation-rule="verification-code" required autofocus>
                            </div>
                            <button type="submit" class="login-button"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Verify &amp; Continue</span></button>
                        </form>
                        <div class="recovery-footer">
                            <form method="POST" action="{{ route('student.register.send-code') }}">@csrf<input type="hidden" name="ms_account" value="{{ $regEmail }}"><button type="submit" class="text-action">Resend code</button></form>
                            <form method="POST" action="{{ route('student.register.cancel') }}">@csrf<button type="submit" class="text-action muted">Cancel registration</button></form>
                        </div>
                    </div>
                @endif

                <div class="auth-panel" id="email-panel" data-panel="email" @if($activePanel !== 'email') hidden @endif>
                    <div class="recovery-steps" aria-label="Password recovery step 1 of 3"><span class="recovery-step active"></span><span class="recovery-step"></span><span class="recovery-step"></span></div>
                    <p class="recovery-note">Enter your registered email. We will check that the student account exists before sending a code.</p>
                    <form method="POST" action="{{ route('student.password-recovery.send-code') }}">
                        @csrf
                        <input type="hidden" name="recovery_action" value="email">
                        <div class="field">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <label for="recovery_email" hidden>Registered email address</label>
                            <input type="email" name="email" id="recovery_email" value="{{ old('email', $recoveryEmail) }}" placeholder="Registered email address" autocomplete="email" maxlength="150" data-validation-label="Registered email address" required>
                        </div>
                        <button type="submit" class="login-button"><i class="bi bi-send" aria-hidden="true"></i><span>Verify Account &amp; Send Code</span></button>
                    </form>
                    <button type="button" class="back-button" data-show-auth-panel="login"><i class="bi bi-arrow-left"></i> Back to Login</button>
                </div>

                <div class="auth-panel" id="code-panel" data-panel="code" @if($activePanel !== 'code') hidden @endif>
                    <div class="recovery-steps" aria-label="Password recovery step 2 of 3"><span class="recovery-step active"></span><span class="recovery-step active"></span><span class="recovery-step"></span></div>
                    <p class="recovery-note">Code sent to <strong>{{ $maskedEmail }}</strong>. It expires after 10 minutes.</p>
                    <form method="POST" action="{{ route('student.password-recovery.verify-code') }}">
                        @csrf
                        <div class="field code-field">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <label for="verification_code" hidden>Six-digit verification code</label>
                            <input type="text" inputmode="numeric" name="verification_code" id="verification_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Verification code" data-validation-rule="verification-code" required autofocus>
                        </div>
                        <button type="submit" class="login-button"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Confirm Verification Code</span></button>
                    </form>
                    <div class="recovery-footer">
                        <form method="POST" action="{{ route('student.password-recovery.send-code') }}">@csrf<input type="hidden" name="email" value="{{ $recoveryEmail }}"><button type="submit" class="text-action">Resend code</button></form>
                        <form method="POST" action="{{ route('student.password-recovery.cancel') }}">@csrf<button type="submit" class="text-action muted">Cancel and return to login</button></form>
                    </div>
                </div>

                <div class="auth-panel" id="reset-panel" data-panel="reset" @if($activePanel !== 'reset') hidden @endif>
                    <div class="recovery-steps" aria-label="Password recovery step 3 of 3"><span class="recovery-step active"></span><span class="recovery-step active"></span><span class="recovery-step active"></span></div>
                    <p class="recovery-note">Email verified for <strong>{{ $maskedEmail }}</strong>.</p>
                    <form method="POST" action="{{ route('student.password-recovery.reset') }}">
                        @csrf
                        <div class="field">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <label for="new_password" hidden>New password</label>
                            <input type="password" name="password" id="new_password" placeholder="New password" autocomplete="new-password" minlength="8" maxlength="128" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-validation-label="New password" data-validation-rule="strong-password" data-password-primary required>
                            <button class="password-toggle" type="button" data-password-toggle="new_password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                        <div class="field">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <label for="new_password_confirmation" hidden>Confirm new password</label>
                            <input type="password" name="password_confirmation" id="new_password_confirmation" placeholder="Confirm new password" autocomplete="new-password" maxlength="128" data-validation-label="Password confirmation" data-password-confirmation required>
                            <button class="password-toggle" type="button" data-password-toggle="new_password_confirmation" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                        <p class="password-hint">Use at least 8 characters with uppercase, lowercase, a number, and a special character.</p>
                        <button type="submit" class="login-button"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Save New Password</span></button>
                    </form>
                    <form method="POST" action="{{ route('student.password-recovery.cancel') }}">@csrf<button type="submit" class="back-button"><i class="bi bi-x-lg"></i> Cancel Password Reset</button></form>
                </div>

                @if (is_array($loginChallenge))
                    @php
                        $otpEmail = (string) ($loginChallenge['email'] ?? '');
                        $otpParts = str_contains($otpEmail, '@') ? explode('@', $otpEmail, 2) : [];
                        $otpMasked = count($otpParts) === 2
                            ? substr($otpParts[0], 0, min(2, strlen($otpParts[0]))) . str_repeat('•', max(3, strlen($otpParts[0]) - 2)) . '@' . $otpParts[1]
                            : $otpEmail;
                    @endphp
                    <div class="auth-panel" id="device-otp-panel" data-panel="device-otp" @if($activePanel !== 'device-otp') hidden @endif>
                        <p class="recovery-note">This device has not signed in to your account before, so we emailed a code to <strong>{{ $otpMasked }}</strong>. It expires after 10 minutes and works only once.</p>
                        <form method="POST" action="{{ route('student.login.otp.verify') }}">
                            @csrf
                            <div class="field code-field">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                <label for="device_login_code" hidden>Six-digit sign-in code</label>
                                <input type="text" inputmode="numeric" name="verification_code" id="device_login_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Sign-in code" data-validation-rule="verification-code" required autofocus>
                            </div>
                            <button type="submit" class="login-button"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Verify &amp; Continue</span></button>
                        </form>
                        <div class="recovery-footer">
                            <form method="POST" action="{{ route('student.login.otp.resend') }}">@csrf<button type="submit" class="text-action">Resend code</button></form>
                            <form method="POST" action="{{ route('student.login.otp.cancel') }}">@csrf<button type="submit" class="text-action muted">Cancel and return to login</button></form>
                        </div>
                    </div>
                @endif

                <p class="help">Need help? <a href="mailto:admin@mcc.edu.ph">Contact your administrator.</a></p>
            </div>
            <p class="copyright">&copy; {{ date('Y') }} Madridejos Community College. All rights reserved.</p>
        </section>
    </main>

    <script>
        const headingMap = @json($panelHeadings);
        const headingTitle = document.getElementById('login-title');
        const headingCopy = document.getElementById('auth-heading-copy');
        const headingIcon = document.getElementById('auth-heading-icon');

        function showAuthPanel(name) {
            const panel = document.querySelector(`[data-panel="${name}"]`);
            if (!panel) return;
            document.querySelectorAll('[data-panel]').forEach(item => {
                item.hidden = item !== panel;
                item.classList.remove('panel-enter');
            });
            panel.classList.add('panel-enter');
            headingTitle.textContent = headingMap[name].title;
            headingCopy.textContent = headingMap[name].subtitle;
            headingIcon.className = `bi ${headingMap[name].icon}`;
            window.setTimeout(() => panel.querySelector('input:not([type="hidden"])')?.focus(), 80);
        }

        document.querySelectorAll('[data-show-auth-panel]').forEach(button => {
            button.addEventListener('click', () => showAuthPanel(button.dataset.showAuthPanel));
        });

        document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const password = document.getElementById(toggle.dataset.passwordToggle);
                const willShow = password.type === 'password';
                password.type = willShow ? 'text' : 'password';
                toggle.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', String(willShow));
                toggle.querySelector('i').className = willShow ? 'bi bi-eye-slash' : 'bi bi-eye';
                password.focus();
            });
        });

        document.querySelectorAll('[data-captcha-refresh]').forEach(button => {
            button.addEventListener('click', () => {
                const image = button.closest('.captcha-row').querySelector('[data-captcha-image]');
                image.src = image.src.split('?')[0] + `?refresh=${Date.now()}`;
            });
        });
    </script>
    <script src="{{ asset('js/auth-form-validation.js') }}"></script>
</body>
</html>
