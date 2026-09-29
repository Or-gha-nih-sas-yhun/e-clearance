<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#075bea">
    <title>{{ $pageTitle }}</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/auth-form-validation.css') }}" rel="stylesheet">
    <style>
        :root { --blue:#075bea; --ink:#071b50; --muted:#526b91; }
        * { box-sizing:border-box; }
        html,body { width:100%; height:100%; margin:0; overflow:hidden; }
        body { color:var(--ink); font-family:"Inter",system-ui,sans-serif; background:#b9ddfa url("{{ asset('images/mcc-campus.jpg') }}") center/cover fixed no-repeat; }
        button,input,select { font:inherit; }
        .page { position:relative; isolation:isolate; display:grid; grid-template-columns:minmax(480px,1.12fr) minmax(430px,.88fr); gap:clamp(34px,4vw,68px); align-items:center; width:100%; height:100vh; height:100dvh; padding:clamp(30px,5vw,78px); overflow:hidden; }
        .page::before { position:absolute; z-index:-2; inset:0; content:""; background:linear-gradient(90deg,rgba(224,243,255,.97),rgba(219,240,255,.86) 43%,rgba(207,235,255,.54)); }
        .page::after { position:absolute; z-index:-1; top:-44vw; right:-23vw; width:68vw; height:68vw; content:""; border:2px solid rgba(255,255,255,.65); border-radius:50%; box-shadow:0 0 0 28px rgba(255,255,255,.09),0 0 0 31px rgba(255,255,255,.28); }
        .intro { max-width:650px; }
        .brand { display:flex; align-items:center; gap:20px; margin-bottom:clamp(24px,3.5vh,40px); }
        .school-logo { width:clamp(104px,8vw,136px); height:clamp(104px,8vw,136px); padding:7px; flex:0 0 auto; object-fit:contain; border:3px solid rgba(255,255,255,.92); border-radius:50%; background:rgba(255,255,255,.88); box-shadow:0 8px 20px rgba(5,57,137,.18); }
        .brand h1 { margin:0; font-size:clamp(1.8rem,2.35vw,2.55rem); line-height:1.18; letter-spacing:-.035em; }
        .brand h1 span { display:block; margin-top:4px; color:var(--blue); }
        .tagline { margin:10px 0 0; color:#244979; font-size:1.03rem; }
        .intro h2 { max-width:520px; margin:0 0 14px; font-size:clamp(1.85rem,2.55vw,2.75rem); line-height:1.17; letter-spacing:-.04em; }
        .intro-copy { max-width:500px; margin:0 0 22px; color:#183c6e; font-size:1rem; line-height:1.55; }
        .benefits { display:grid; width:min(100%,560px); grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .benefit { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid rgba(255,255,255,.75); border-radius:16px; background:rgba(246,251,255,.68); box-shadow:0 8px 24px rgba(41,106,167,.09); backdrop-filter:blur(10px); }
        .benefit-icon { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; color:#fff; border-radius:12px; background:linear-gradient(145deg,#35a9ff,#075bea); box-shadow:0 7px 17px rgba(7,91,234,.22); }
        .benefit strong { display:block; font-size:.86rem; }.benefit small{display:block;margin-top:2px;color:var(--muted);font-size:.7rem;line-height:1.35}
        .login-wrap { width:min(100%,530px); justify-self:end; }
        .login-card { position:relative; display:flex; min-height:min(70vh,660px); padding:clamp(28px,3.2vw,46px); flex-direction:column; justify-content:center; overflow:hidden; border:1px solid rgba(255,255,255,.92); border-radius:34px; background:linear-gradient(135deg,rgba(255,255,255,.87),rgba(235,248,255,.7)); box-shadow:0 28px 75px rgba(20,74,134,.25),inset 0 1px rgba(255,255,255,.95); backdrop-filter:blur(22px) saturate(140%); }
        .login-card::before { position:absolute; top:-110px; right:-80px; width:280px; height:280px; content:""; border:1px solid rgba(59,143,233,.12); border-radius:50%; box-shadow:0 0 0 18px rgba(59,143,233,.035),0 0 0 36px rgba(59,143,233,.03); }
        .login-heading { position:relative; text-align:center; }
        .role-badge { display:grid; width:78px; height:78px; margin:0 auto 15px; place-items:center; color:#fff; border:10px solid rgba(255,255,255,.58); border-radius:50%; background:linear-gradient(145deg,#38adff,#075bea); box-shadow:0 14px 30px rgba(32,123,221,.25); font-size:1.85rem; }
        .login-heading h2 { margin:0; font-size:clamp(1.75rem,2.5vw,2.25rem); letter-spacing:-.04em; }
        .login-heading p { margin:8px 0 22px; color:var(--muted); font-size:.92rem; }
        .alert { display:flex; gap:9px; align-items:flex-start; margin-bottom:15px; padding:11px 13px; color:#a61b2b; border:1px solid #ffc5cc; border-radius:13px; background:#fff1f3; font-size:.83rem; }
        .alert.success{color:#12613a;border-color:#b9ebcf;background:#edfff4}.alert.info{color:#14547d;border-color:#b9ddf5;background:#edf8ff}
        .field { position:relative; margin-bottom:14px; }.field>i{position:absolute;z-index:1;top:50%;left:19px;color:var(--blue);font-size:1.15rem;transform:translateY(-50%);pointer-events:none}
        .field input,.field select { width:100%; height:56px; padding:0 52px; color:var(--ink); border:1px solid #ccdbed; border-radius:15px; outline:none; background:rgba(255,255,255,.82); box-shadow:0 8px 18px rgba(41,98,159,.06); font-size:.96rem; transition:.2s; }
        .field select { padding-right:42px; appearance:auto; }.field input::placeholder{color:#6c80a2}.field input:focus,.field select:focus{border-color:#4791ff;background:#fff;box-shadow:0 0 0 4px rgba(7,91,234,.1)}
        .password-toggle { position:absolute;z-index:2;top:50%;right:9px;display:grid;width:42px;height:42px;padding:0;place-items:center;color:#637a9c;border:0;border-radius:11px;background:transparent;transform:translateY(-50%);cursor:pointer; }.password-toggle:hover{color:var(--blue);background:#edf5ff}
        .form-options { display:flex; justify-content:space-between; align-items:center; gap:16px; margin:13px 2px 19px; font-size:.88rem; }.remember{display:flex;align-items:center;gap:8px;cursor:pointer}.remember input{width:18px;height:18px;accent-color:var(--blue)}.forgot{padding:0;color:var(--blue);border:0;outline:0;background:transparent;font-weight:500;text-decoration:none;cursor:pointer}.forgot:hover{text-decoration:underline}
        .login-button { display:flex; width:100%; min-height:54px; align-items:center; justify-content:center; gap:10px; color:#fff; border:0; border-radius:15px; background:linear-gradient(135deg,#079cff,#075bea 64%,#1546dc); box-shadow:0 12px 24px rgba(7,91,234,.26); font-size:1rem; font-weight:700; cursor:pointer; transition:.2s; }.login-button:hover{transform:translateY(-2px);box-shadow:0 16px 28px rgba(7,91,234,.31)}
        .landing-button { display:flex; width:100%; min-height:46px; margin-top:10px; align-items:center; justify-content:center; gap:8px; color:#254f83; border:1px solid rgba(86,135,192,.28); border-radius:14px; background:rgba(246,251,255,.68); font-size:.88rem; font-weight:700; text-decoration:none; transition:.2s; }.landing-button:hover{color:var(--blue);border-color:rgba(7,91,234,.32);background:#fff;box-shadow:0 8px 18px rgba(41,98,159,.09);transform:translateY(-1px)}.landing-button:focus-visible{outline:3px solid rgba(7,91,234,.22);outline-offset:2px}
        .auth-panel[hidden]{display:none}.auth-panel.panel-enter{animation:panelEnter .32s cubic-bezier(.22,1,.36,1)}@keyframes panelEnter{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
        .recovery-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin:0 0 16px}.recovery-step{height:5px;border-radius:999px;background:#d9e6f4}.recovery-step.active{background:linear-gradient(90deg,#32a9ff,#075bea);box-shadow:0 3px 9px rgba(7,91,234,.18)}
        .recovery-note{margin:-2px 0 15px;color:var(--muted);text-align:center;font-size:.8rem;line-height:1.5}.recovery-note strong{color:#193f70}.code-field input{padding-right:20px;text-align:center;letter-spacing:.42em;font-size:1.3rem;font-weight:800}.code-field>i{display:none}
        .password-hint{margin:-6px 4px 13px;color:#607795;font-size:.72rem;line-height:1.4}.recovery-footer{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px 13px;margin-top:13px}.recovery-footer form{margin:0}.text-action{padding:5px;color:var(--blue);border:0;outline:0;background:transparent;font-size:.8rem;font-weight:700;cursor:pointer}.text-action:hover{text-decoration:underline}.text-action.muted{color:#60748e}.register-prompt{margin:12px 0 0;color:#506b8f;text-align:center;font-size:.82rem}.register-prompt .text-action{padding:2px 4px}
        .back-button{display:flex;width:100%;min-height:46px;margin-top:10px;align-items:center;justify-content:center;gap:8px;color:#34506f;border:0;border-radius:14px;background:rgba(230,240,249,.76);font-weight:700;cursor:pointer}.back-button:hover{color:var(--blue);background:#e6f3ff}
        .captcha-box{margin:2px 0 14px;padding:12px;border:1px solid #c9dcef;border-radius:15px;background:rgba(239,247,255,.76)}.captcha-label{display:block;margin:0 0 9px;color:#34506f;font-size:.78rem;font-weight:700}.captcha-row{display:grid;grid-template-columns:190px 42px 1fr;gap:8px;align-items:center}.captcha-image{width:190px;height:64px;border:1px solid #b9cee5;border-radius:11px;background:#eef7ff}.captcha-refresh{display:grid;width:42px;height:42px;padding:0;place-items:center;color:var(--blue);border:1px solid #bdd3ea;border-radius:11px;background:#fff;cursor:pointer}.captcha-answer{width:100%;height:48px;padding:0 12px;text-align:center;text-transform:uppercase;letter-spacing:.16em;border:1px solid #ccdbed;border-radius:11px;outline:none;background:#fff;font-weight:800}.captcha-answer:focus{border-color:#4791ff;box-shadow:0 0 0 4px rgba(7,91,234,.1)}
        .copyright{margin:13px 0 0;color:#31557f;text-align:center;font-size:.74rem}.copyright a{color:#174f93;font-weight:700;text-decoration:none}.copyright a:hover{text-decoration:underline}.copyright a:focus-visible{outline:3px solid rgba(7,91,234,.2);outline-offset:3px;border-radius:4px}
        @media(max-width:940px){.page{display:flex;flex-direction:column;justify-content:center;gap:16px;padding:18px 20px}.intro{text-align:center}.brand{justify-content:center;margin-bottom:0;text-align:left}.intro>h2,.intro-copy,.benefits{display:none}.school-logo{width:76px;height:76px}.brand h1{font-size:1.35rem}.tagline{font-size:.82rem}.login-wrap{width:min(100%,520px)}.login-card{min-height:min(68vh,590px)}}
        @media(max-width:560px){.page{gap:10px;padding:12px}.school-logo{width:64px;height:64px;padding:4px}.brand{gap:11px}.brand h1{font-size:1.1rem}.tagline{margin-top:4px;font-size:.72rem}.login-card{min-height:min(72vh,560px);padding:20px 17px;border-radius:25px}.role-badge{width:62px;height:62px;margin-bottom:9px;border-width:8px;font-size:1.45rem}.login-heading h2{font-size:1.5rem}.login-heading p{margin:5px 0 14px;font-size:.8rem}.field{margin-bottom:10px}.field input,.field select{height:50px}.form-options{margin:10px 2px 14px}.login-button{min-height:49px}.copyright{margin-top:7px}.captcha-row{grid-template-columns:1fr 42px}.captcha-image{width:100%;object-fit:cover}.captcha-answer{grid-column:1/-1}}
        @media(max-height:680px){.page{padding-top:12px;padding-bottom:12px}.login-card{min-height:auto;padding-top:20px;padding-bottom:20px}.role-badge{width:58px;height:58px;margin-bottom:8px;border-width:7px;font-size:1.35rem}.login-heading p{margin:4px 0 12px}.field{margin-bottom:9px}.field input,.field select{height:48px}.form-options{margin:9px 2px 12px}.copyright{display:none}}
    </style>
</head>
<body>
@include('partials.app-shell-body')
@php
    $firstError = null;
    if (isset($errors) && is_object($errors) && method_exists($errors, 'any') && $errors->any()) $firstError = $errors->first();
    elseif (isset($errors) && is_array($errors) && count($errors)) $firstError = reset($errors);
    $isStudentPortal = $isStudentPortal ?? false;
    $recoverySessionKey = $isStudentPortal
        ? 'student_password_recovery'
        : 'portal_password_recovery_' . str_replace('-', '_', $recoveryPortal);
    $recoveryState = session($recoverySessionKey, []);
    $recoveryStep = $recoveryStep ?? ($recoveryState['stage'] ?? ((old('recovery_action') === 'email' && ($isStudentPortal || old('recovery_portal') === $recoveryPortal)) ? 'email' : 'login'));
    $activePanel = in_array($recoveryStep, ['email', 'code', 'reset'], true) ? $recoveryStep : 'login';
    $registrationState = $isStudentPortal ? session('student_registration') : null;
    if (is_array($registrationState) && ($registrationState['stage'] ?? null) === 'code') $activePanel = 'register-code';
    elseif ($isStudentPortal && old('registration_action') === 'account') $activePanel = 'register-account';
    $loginGuard = $recoveryPortal === 'main-admin' ? 'admin' : $recoveryPortal;
    // Set once the password is accepted from a browser this account has not verified before.
    $loginChallenge = session("login_challenge_{$loginGuard}");
    if (is_array($loginChallenge)) $activePanel = 'device-otp';
    $otpRoute = $recoveryPortal === 'main-admin' ? 'login.otp' : "{$recoveryPortal}.login.otp";
    $captchaRequired = session("login_security.captcha.{$loginGuard}", false);
    $recoveryEmail = $recoveryEmail ?? ($recoveryState['email'] ?? old('email', ''));
    $recoveryRoutes = $isStudentPortal
        ? [
            'send' => route('student.password-recovery.send-code'),
            'verify' => route('student.password-recovery.verify-code'),
            'reset' => route('student.password-recovery.reset'),
            'cancel' => route('student.password-recovery.cancel'),
        ]
        : [
            'send' => route('portal-password-recovery.send-code', $recoveryPortal),
            'verify' => route('portal-password-recovery.verify-code', $recoveryPortal),
            'reset' => route('portal-password-recovery.reset', $recoveryPortal),
            'cancel' => route('portal-password-recovery.cancel', $recoveryPortal),
        ];
    $hideLandingLink = $isStudentPortal && str_contains((string) request()->userAgent(), 'MCCStudentAndroid/');
    $emailParts = str_contains($recoveryEmail, '@') ? explode('@', $recoveryEmail, 2) : [];
    $maskedEmail = count($emailParts) === 2 ? substr($emailParts[0], 0, min(2, strlen($emailParts[0]))) . str_repeat('•', max(3, strlen($emailParts[0]) - 2)) . '@' . $emailParts[1] : $recoveryEmail;
    $panelHeadings = [
        'login' => ['icon' => $roleIcon, 'title' => $portalName, 'subtitle' => $portalDescription],
        'email' => ['icon' => 'bi-envelope-check', 'title' => 'Recover Password', 'subtitle' => 'Verify the email registered to your account.'],
        'code' => ['icon' => 'bi-shield-check', 'title' => 'Check Your Email', 'subtitle' => 'Enter the six-digit verification code we sent.'],
        'reset' => ['icon' => 'bi-key', 'title' => 'Create New Password', 'subtitle' => 'Choose a strong password for your account.'],
        'register-account' => ['icon' => 'bi-person-plus', 'title' => 'Register Your Account', 'subtitle' => 'Use the Microsoft account listed by the college.'],
        'register-code' => ['icon' => 'bi-envelope-check', 'title' => 'Verify Your Account', 'subtitle' => 'Enter the six-digit code sent to your Microsoft account.'],
        'device-otp' => ['icon' => 'bi-envelope-shield', 'title' => 'Verify This Device', 'subtitle' => 'Enter the one-time code sent to your registered email.'],
    ];
    $activeHeading = $panelHeadings[$activePanel];
@endphp
<main class="page">
    <section class="intro">
        <div class="brand"><img class="school-logo" src="{{ asset('images/mcc-logo.png') }}" alt="Madridejos Community College logo"><div><h1>Madridejos Community College<span>Clearance System</span></h1><p class="tagline">Streamlined. Secure. Smart.</p></div></div>
        <h2>Manage every clearance with ease and efficiency.</h2>
        <p class="intro-copy">A secure digital solution for students, instructors, and personnel to process clearance requests faster and smarter.</p>
        <div class="benefits"><div class="benefit"><span class="benefit-icon"><i class="bi bi-shield-check"></i></span><span><strong>Secure &amp; Reliable</strong><small>Institutional data stays protected.</small></span></div><div class="benefit"><span class="benefit-icon"><i class="bi bi-clock"></i></span><span><strong>Efficient Process</strong><small>Save time and reduce paperwork.</small></span></div><div class="benefit"><span class="benefit-icon"><i class="bi bi-bar-chart-line"></i></span><span><strong>Transparent Tracking</strong><small>Follow clearance progress in real time.</small></span></div><div class="benefit"><span class="benefit-icon"><i class="bi bi-phone"></i></span><span><strong>Accessible Anywhere</strong><small>Use the system on any device.</small></span></div></div>
    </section>
    <section class="login-wrap">
        <div class="login-card">
            <div class="login-heading"><div class="role-badge"><i id="auth-heading-icon" class="bi {{ $activeHeading['icon'] }}"></i></div><h2 id="auth-heading-title">{{ $activeHeading['title'] }}</h2><p id="auth-heading-copy">{{ $activeHeading['subtitle'] }}</p></div>
            @if(session('status'))<div class="alert success" role="status"><i class="bi bi-check-circle"></i><span>{{ session('status') }}</span></div>@endif
            @if(session('recovery_status'))<div class="alert info" role="status"><i class="bi bi-info-circle"></i><span>{{ session('recovery_status') }}</span></div>@endif
            @if($isStudentPortal && session('registration_status'))<div class="alert info" role="status"><i class="bi bi-info-circle"></i><span>{{ session('registration_status') }}</span></div>@endif
            @if($firstError)<div class="alert" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ $firstError }}</span></div>@endif

            <div class="auth-panel" id="login-panel" data-panel="login" @if($activePanel !== 'login') hidden @endif>
                <form method="POST" action="{{ $submitRoute }}" autocomplete="off" data-client-location>
                    @csrf
                    @include('partials.login-security-fields')
                    <div class="field"><i class="bi {{ $loginIcon }}"></i><label for="portal-login" hidden>{{ $loginLabel }}</label><input type="{{ $loginType }}" name="{{ $loginName }}" id="portal-login" value="{{ old($loginName) }}" placeholder="{{ $loginPlaceholder }}" autocomplete="username" maxlength="{{ $loginName === 'student_id' ? 50 : 100 }}" @if($loginName === 'student_id') inputmode="numeric" pattern="\d{4}-\d{4}" title="Format: 2000-1234" @endif data-validation-label="{{ $loginLabel }}" @if($activePanel === 'login') autofocus @endif required></div>
                    @if(!empty($roleOptions))
                        <div class="field"><i class="bi bi-person-badge"></i><label for="portal-role" hidden>{{ $roleLabel }}</label><select name="{{ $roleName }}" id="portal-role" data-validation-label="{{ $roleLabel }}" required><option value="">{{ $rolePlaceholder }}</option>@foreach($roleOptions as $value=>$label)<option value="{{ $value }}" @selected(old($roleName)===$value)>{{ $label }}</option>@endforeach</select></div>
                    @endif
                    <div class="field"><i class="bi bi-lock"></i><label for="password" hidden>Password</label><input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" minlength="8" maxlength="128" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-validation-label="Password" data-validation-rule="strong-password" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                    @if($captchaRequired)
                        <div class="captcha-box">
                            <label class="captcha-label" for="captcha-answer">Security check: enter the five characters shown</label>
                            <div class="captcha-row">
                                <img class="captcha-image" data-captcha-image src="{{ route('auth.captcha', $recoveryPortal) }}" alt="Five-character security code">
                                <button class="captcha-refresh" type="button" data-captcha-refresh aria-label="Load a new security code"><i class="bi bi-arrow-clockwise"></i></button>
                                <input class="captcha-answer" type="text" name="captcha_answer" id="captcha-answer" maxlength="5" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                            </div>
                        </div>
                    @endif
                    <div class="form-options">@if($showRemember)<label class="remember"><input type="checkbox" name="remember" @checked(old('remember'))><span>Remember me</span></label>@else<span></span>@endif<button class="forgot" type="button" data-show-auth-panel="email">Forgot password?</button></div>
                    <button type="submit" class="login-button"><i class="bi bi-box-arrow-in-right"></i><span>Log In</span></button>
                </form>
                @if($isStudentPortal)
                    <p class="register-prompt">No account yet? <button type="button" class="text-action" data-show-auth-panel="register-account">Register your account</button></p>
                @endif
                @unless($hideLandingLink)<a href="{{ route('landing') }}" class="landing-button"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to Landing Page</span></a>@endunless
            </div>

            @if($isStudentPortal)
                <div class="auth-panel" id="register-account-panel" data-panel="register-account" @if($activePanel !== 'register-account') hidden @endif>
                    <p class="recovery-note">Enter the Microsoft account included in the college student registration list.</p>
                    <form method="POST" action="{{ route('student.register.send-code') }}">
                        @csrf
                        <input type="hidden" name="registration_action" value="account">
                        <div class="field"><i class="bi bi-microsoft"></i><label for="ms-account" hidden>Microsoft account</label><input type="email" name="ms_account" id="ms-account" value="{{ old('ms_account') }}" placeholder="Microsoft account (e.g. name@mcc.edu.ph)" autocomplete="email" maxlength="150" data-validation-label="Microsoft account" required autofocus></div>
                        <button type="submit" class="login-button"><i class="bi bi-send"></i><span>Send Registration Code</span></button>
                    </form>
                    <button type="button" class="back-button" data-show-auth-panel="login"><i class="bi bi-arrow-left"></i> Back to Login</button>
                </div>

                @if(is_array($registrationState) && ($registrationState['stage'] ?? null) === 'code')
                    @php
                        $registrationEmail = (string) ($registrationState['ms_account'] ?? '');
                        $registrationParts = str_contains($registrationEmail, '@') ? explode('@', $registrationEmail, 2) : [];
                        $registrationMasked = count($registrationParts) === 2 ? substr($registrationParts[0], 0, min(2, strlen($registrationParts[0]))) . str_repeat('•', max(3, strlen($registrationParts[0]) - 2)) . '@' . $registrationParts[1] : $registrationEmail;
                    @endphp
                    <div class="auth-panel" id="register-code-panel" data-panel="register-code" @if($activePanel !== 'register-code') hidden @endif>
                        <p class="recovery-note">Code sent to <strong>{{ $registrationMasked }}</strong>. It expires after 10 minutes.</p>
                        <form method="POST" action="{{ route('student.register.verify-code') }}">
                            @csrf
                            <div class="field code-field"><i class="bi bi-shield-lock"></i><label for="registration-code" hidden>Six-digit registration code</label><input type="text" inputmode="numeric" name="verification_code" id="registration-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Registration code" data-validation-rule="verification-code" required autofocus></div>
                            <button type="submit" class="login-button"><i class="bi bi-check2-circle"></i><span>Verify &amp; Continue</span></button>
                        </form>
                        <div class="recovery-footer">
                            <form method="POST" action="{{ route('student.register.send-code') }}">@csrf<input type="hidden" name="ms_account" value="{{ $registrationEmail }}"><button type="submit" class="text-action">Resend code</button></form>
                            <form method="POST" action="{{ route('student.register.cancel') }}">@csrf<button type="submit" class="text-action muted">Cancel and return to login</button></form>
                        </div>
                    </div>
                @endif
            @endif

            <div class="auth-panel" id="email-panel" data-panel="email" @if($activePanel !== 'email') hidden @endif>
                <div class="recovery-steps" aria-label="Password recovery step 1 of 3"><span class="recovery-step active"></span><span class="recovery-step"></span><span class="recovery-step"></span></div>
                <p class="recovery-note">Enter your registered email. We will confirm that the {{ strtolower($portalName) }} account exists before sending a code.</p>
                <form method="POST" action="{{ $recoveryRoutes['send'] }}">
                    @csrf<input type="hidden" name="recovery_action" value="email">@unless($isStudentPortal)<input type="hidden" name="recovery_portal" value="{{ $recoveryPortal }}">@endunless
                    <div class="field"><i class="bi bi-envelope"></i><label for="recovery-email" hidden>Registered email</label><input type="email" name="email" id="recovery-email" value="{{ old('email', $recoveryEmail) }}" placeholder="Registered email address" autocomplete="email" maxlength="150" data-validation-label="Registered email address" required></div>
                    <button type="submit" class="login-button"><i class="bi bi-send"></i><span>Verify Account &amp; Send Code</span></button>
                </form>
                <button type="button" class="back-button" data-show-auth-panel="login"><i class="bi bi-arrow-left"></i> Back to Login</button>
            </div>

            <div class="auth-panel" id="code-panel" data-panel="code" @if($activePanel !== 'code') hidden @endif>
                <div class="recovery-steps" aria-label="Password recovery step 2 of 3"><span class="recovery-step active"></span><span class="recovery-step active"></span><span class="recovery-step"></span></div>
                <p class="recovery-note">Code sent to <strong>{{ $maskedEmail }}</strong>. It expires after 10 minutes.</p>
                <form method="POST" action="{{ $recoveryRoutes['verify'] }}">
                    @csrf
                    <div class="field code-field"><i class="bi bi-shield-lock"></i><label for="verification-code" hidden>Six-digit verification code</label><input type="text" inputmode="numeric" name="verification_code" id="verification-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Verification code" data-validation-rule="verification-code" required autofocus></div>
                    <button type="submit" class="login-button"><i class="bi bi-check2-circle"></i><span>Confirm Verification Code</span></button>
                </form>
                <div class="recovery-footer">
                    <form method="POST" action="{{ $recoveryRoutes['send'] }}">@csrf<input type="hidden" name="email" value="{{ $recoveryEmail }}"><button type="submit" class="text-action">Resend code</button></form>
                    <form method="POST" action="{{ $recoveryRoutes['cancel'] }}">@csrf<button type="submit" class="text-action muted">Cancel and return to login</button></form>
                </div>
            </div>

            <div class="auth-panel" id="reset-panel" data-panel="reset" @if($activePanel !== 'reset') hidden @endif>
                <div class="recovery-steps" aria-label="Password recovery step 3 of 3"><span class="recovery-step active"></span><span class="recovery-step active"></span><span class="recovery-step active"></span></div>
                <p class="recovery-note">Email verified for <strong>{{ $maskedEmail }}</strong>.</p>
                <form method="POST" action="{{ $recoveryRoutes['reset'] }}">
                    @csrf
                    <div class="field"><i class="bi bi-lock"></i><label for="new-password" hidden>New password</label><input type="password" name="password" id="new-password" placeholder="New password" autocomplete="new-password" minlength="8" maxlength="128" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-validation-label="New password" data-validation-rule="strong-password" data-password-primary required><button class="password-toggle" type="button" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                    <div class="field"><i class="bi bi-shield-lock"></i><label for="new-password-confirmation" hidden>Confirm new password</label><input type="password" name="password_confirmation" id="new-password-confirmation" placeholder="Confirm new password" autocomplete="new-password" maxlength="128" data-validation-label="Password confirmation" data-password-confirmation required><button class="password-toggle" type="button" data-password-toggle="new-password-confirmation" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                    <p class="password-hint">Use at least 8 characters with uppercase, lowercase, a number, and a special character.</p>
                    <button type="submit" class="login-button"><i class="bi bi-check2-circle"></i><span>Save New Password</span></button>
                </form>
                <form method="POST" action="{{ $recoveryRoutes['cancel'] }}">@csrf<button type="submit" class="back-button"><i class="bi bi-x-lg"></i> Cancel Password Reset</button></form>
            </div>

            @if(is_array($loginChallenge))
                @php
                    $otpEmail = (string) ($loginChallenge['email'] ?? '');
                    $otpParts = str_contains($otpEmail, '@') ? explode('@', $otpEmail, 2) : [];
                    $otpMasked = count($otpParts) === 2 ? substr($otpParts[0], 0, min(2, strlen($otpParts[0]))).str_repeat('•', max(3, strlen($otpParts[0]) - 2)).'@'.$otpParts[1] : $otpEmail;
                @endphp
                <div class="auth-panel" id="device-otp-panel" data-panel="device-otp" @if($activePanel !== 'device-otp') hidden @endif>
                    <p class="recovery-note">This device has not signed in to this account before, so we emailed a code to <strong>{{ $otpMasked }}</strong>. It expires after 10 minutes and works only once.</p>
                    <form method="POST" action="{{ route("{$otpRoute}.verify") }}" data-client-location>
                        @csrf
                        @include('partials.login-security-fields')
                        <div class="field code-field"><i class="bi bi-shield-lock"></i><label for="device-login-code" hidden>Six-digit sign-in code</label><input type="text" inputmode="numeric" name="verification_code" id="device-login-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" data-validation-label="Sign-in code" data-validation-rule="verification-code" required autofocus></div>
                        <button type="submit" class="login-button"><i class="bi bi-shield-check"></i><span>Verify &amp; Open {{ $portalName }}</span></button>
                    </form>
                    <div class="recovery-footer">
                        <form method="POST" action="{{ route("{$otpRoute}.resend") }}">@csrf<button type="submit" class="text-action">Resend code</button></form>
                        <form method="POST" action="{{ route("{$otpRoute}.cancel") }}">@csrf<button type="submit" class="text-action muted">Cancel and return to login</button></form>
                    </div>
                </div>
            @endif
        </div>
        <p class="copyright">&copy; {{ date('Y') }} Madridejos Community College. All rights reserved. <span aria-hidden="true">&middot;</span> <a href="{{ route('privacy-policy') }}">Privacy Policy</a></p>
    </section>
</main>
<script>
const headingMap=@json($panelHeadings),headingTitle=document.getElementById('auth-heading-title'),headingCopy=document.getElementById('auth-heading-copy'),headingIcon=document.getElementById('auth-heading-icon');
function showAuthPanel(name){const panel=document.querySelector(`[data-panel="${name}"]`);if(!panel)return;document.querySelectorAll('[data-panel]').forEach(item=>{item.hidden=item!==panel;item.classList.remove('panel-enter')});panel.classList.add('panel-enter');headingTitle.textContent=headingMap[name].title;headingCopy.textContent=headingMap[name].subtitle;headingIcon.className=`bi ${headingMap[name].icon}`;window.setTimeout(()=>panel.querySelector('input:not([type="hidden"])')?.focus(),80)}
document.querySelectorAll('[data-show-auth-panel]').forEach(button=>button.addEventListener('click',()=>showAuthPanel(button.dataset.showAuthPanel)));
document.querySelectorAll('[data-password-toggle]').forEach(toggle=>toggle.addEventListener('click',()=>{const password=document.getElementById(toggle.dataset.passwordToggle),show=password.type==='password';password.type=show?'text':'password';toggle.setAttribute('aria-label',show?'Hide password':'Show password');toggle.setAttribute('aria-pressed',String(show));toggle.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';password.focus()}));
document.querySelectorAll('[data-captcha-refresh]').forEach(button=>button.addEventListener('click',()=>{const image=button.closest('.captcha-row').querySelector('[data-captcha-image]');image.src=image.src.split('?')[0]+`?refresh=${Date.now()}`}));
</script>
<script src="{{ asset('js/auth-form-validation.js') }}"></script>
<script src="{{ asset('js/login-security.js') }}"></script>
</body>
</html>
