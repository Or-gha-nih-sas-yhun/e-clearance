<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#075bea">
    <title>Complete Registration | MCC Clearance System</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/auth-form-validation.css') }}" rel="stylesheet">
    <style>
        :root { --blue:#075bea; --ink:#071b50; --muted:#49638c; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--ink); font-family:"Inter",system-ui,-apple-system,"Segoe UI",sans-serif; background:#b9ddfa url("{{ asset('images/mcc-campus.jpg') }}") center/cover no-repeat fixed; }
        body::before { position:fixed; inset:0; z-index:0; content:""; background:linear-gradient(140deg,rgba(224,243,255,.96),rgba(210,236,255,.82)); }
        button, input, select { font:inherit; }
        .shell { position:relative; z-index:1; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:clamp(18px,4vw,48px) clamp(14px,4vw,32px); }
        .card { width:min(100%,720px); padding:clamp(24px,3.4vw,42px); border:1px solid rgba(255,255,255,.9); border-radius:28px; background:linear-gradient(140deg,rgba(255,255,255,.93),rgba(238,249,255,.86)); box-shadow:0 26px 70px rgba(20,74,134,.24); backdrop-filter:blur(20px) saturate(140%); }
        .card-head { margin-bottom:22px; text-align:center; }
        .badge-ring { display:grid; width:70px; height:70px; margin:0 auto 14px; place-items:center; color:#fff; border:9px solid rgba(255,255,255,.6); border-radius:50%; background:linear-gradient(145deg,#38adff,#075bea); box-shadow:0 12px 28px rgba(32,123,221,.24); font-size:1.6rem; }
        h1 { margin:0; font-size:clamp(1.5rem,2.6vw,1.95rem); letter-spacing:-.03em; }
        .card-head p { margin:8px 0 0; color:var(--muted); font-size:.92rem; }
        .alert { display:flex; gap:9px; align-items:flex-start; margin-bottom:16px; padding:12px 14px; color:#a61b2b; border:1px solid #ffc5cc; border-radius:13px; background:#fff1f3; font-size:.85rem; }
        .locked-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-bottom:20px; }
        .locked { padding:12px 14px; border:1px solid #cfe1f5; border-radius:14px; background:rgba(233,244,255,.7); }
        .locked span { display:block; margin-bottom:3px; color:var(--muted); font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
        .locked strong { display:block; overflow-wrap:anywhere; font-size:.97rem; }
        .locked i { margin-right:5px; color:var(--blue); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:13px; }
        .fg { display:flex; flex-direction:column; gap:6px; }
        .fg label { color:#2c4a76; font-size:.8rem; font-weight:600; }
        .fg input, .fg select { width:100%; height:50px; padding:0 15px; color:var(--ink); border:1px solid #ccdbed; border-radius:13px; outline:none; background:rgba(255,255,255,.9); font-size:.94rem; transition:.2s; }
        .fg input:focus, .fg select:focus { border-color:#4791ff; background:#fff; box-shadow:0 0 0 4px rgba(7,91,234,.1); }
        .pw-wrap { position:relative; }
        .pw-wrap input { padding-right:52px; }
        .pw-toggle { position:absolute; top:50%; right:8px; display:grid; width:38px; height:38px; padding:0; place-items:center; color:#637a9c; border:0; border-radius:10px; background:transparent; transform:translateY(-50%); cursor:pointer; }
        .pw-toggle:hover { color:var(--blue); background:#edf5ff; }
        .hint { margin:10px 2px 0; color:#607795; font-size:.77rem; line-height:1.45; }
        .actions { display:flex; gap:11px; flex-wrap:wrap; margin-top:22px; }
        .btn-primary { display:flex; flex:1 1 240px; min-height:52px; align-items:center; justify-content:center; gap:9px; color:#fff; border:0; border-radius:14px; background:linear-gradient(135deg,#079cff,#075bea 64%,#1546dc); box-shadow:0 12px 24px rgba(7,91,234,.26); font-weight:700; cursor:pointer; transition:.2s; }
        .btn-primary:hover { transform:translateY(-2px); }
        .btn-ghost { display:flex; flex:0 1 170px; min-height:52px; align-items:center; justify-content:center; gap:8px; color:#34506f; border:0; border-radius:14px; background:rgba(228,239,250,.85); font-weight:700; cursor:pointer; }
        .btn-ghost:hover { color:var(--blue); background:#e6f3ff; }
        .actions form { display:contents; }
        @media (max-width:560px){ .card { border-radius:20px; } }
        @media (prefers-reduced-motion: reduce) { * { transition:none !important; } }
    </style>
</head>
<body>
@include('partials.app-shell-body')
<main class="shell">
    <div class="card">
        <div class="card-head">
            <div class="badge-ring"><i class="bi bi-person-check"></i></div>
            <h1>Complete your registration</h1>
            <p>Your Microsoft account is verified. Fill in your details to create your student account.</p>
        </div>

        @if ($errors->any())
            <div class="alert" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <div class="locked-grid">
            <div class="locked"><span>Student ID</span><strong><i class="bi bi-lock-fill"></i>{{ $studentId }}</strong></div>
            <div class="locked"><span>Microsoft account</span><strong><i class="bi bi-lock-fill"></i>{{ $msAccount }}</strong></div>
        </div>

        <form method="POST" action="{{ route('student.register.submit') }}" autocomplete="off">
            @csrf
            <div class="grid">
                <div class="fg">
                    <label for="firstname">First name *</label>
                    <input type="text" name="firstname" id="firstname" value="{{ old('firstname') }}" maxlength="20" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}" data-validation-label="First name" required autofocus>
                </div>
                <div class="fg">
                    <label for="middlename">Middle name</label>
                    <input type="text" name="middlename" id="middlename" value="{{ old('middlename') }}" maxlength="20" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}" data-validation-label="Middle name">
                </div>
                <div class="fg">
                    <label for="lastname">Last name *</label>
                    <input type="text" name="lastname" id="lastname" value="{{ old('lastname') }}" maxlength="20" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}" data-validation-label="Last name" required>
                </div>
                <div class="fg">
                    <label for="suffix">Suffix</label>
                    <input type="text" name="suffix" id="suffix" value="{{ old('suffix') }}" maxlength="10" placeholder="Jr., III">
                </div>
                <div class="fg">
                    <label for="program">Program *</label>
                    <select name="program" id="program" required onchange="filterSections()">
                        <option value="">-- Select program --</option>
                        @foreach($programsList as $p)
                            <option value="{{ $p }}" @selected(old('program') === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fg">
                    <label for="year_level">Year level *</label>
                    <select name="year_level" id="year_level" required onchange="filterSections()">
                        <option value="">-- Select year --</option>
                        @foreach($yearLevelOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('year_level') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fg">
                    <label for="section">Section *</label>
                    <select name="section" id="section" required>
                        <option value="">-- Select program &amp; year first --</option>
                    </select>
                </div>
                <div class="fg">
                    <label for="student_type">Student type *</label>
                    <select name="student_type" id="student_type" required>
                        <option value="Regular" @selected(old('student_type', 'Regular') === 'Regular')>Regular</option>
                        <option value="Irregular" @selected(old('student_type') === 'Irregular')>Irregular</option>
                    </select>
                </div>
                <div class="fg">
                    <label for="password">Password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="password" autocomplete="new-password" minlength="8" maxlength="128" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-validation-label="Password" data-validation-rule="strong-password" data-password-primary required>
                        <button class="pw-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="fg">
                    <label for="password_confirmation">Confirm password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" maxlength="128" data-validation-label="Password confirmation" data-password-confirmation required>
                        <button class="pw-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>
            <p class="hint">Use at least 8 characters with uppercase, lowercase, a number, and a special character.</p>
            <div class="actions">
                <button type="submit" class="btn-primary"><i class="bi bi-check2-circle"></i><span>Create my account</span></button>
            </div>
        </form>

        <div class="actions" style="margin-top:11px;">
            <form method="POST" action="{{ route('student.register.cancel') }}">
                @csrf
                <button type="submit" class="btn-ghost"><i class="bi bi-x-lg"></i> Cancel</button>
            </form>
        </div>
    </div>
</main>
<script>
    const SECTIONS = @json($sectionsData);
    const OLD_SECTION = @json(old('section'));

    function filterSections() {
        const program = document.getElementById('program').value;
        const year = document.getElementById('year_level').value;
        const select = document.getElementById('section');
        select.innerHTML = '';

        if (!program || !year) {
            select.innerHTML = '<option value="">-- Select program &amp; year first --</option>';
            return;
        }

        const matches = SECTIONS.filter(s => s.program === program && String(s.year_level) === String(year));
        if (matches.length === 0) {
            select.innerHTML = '<option value="">-- No sections available --</option>';
            return;
        }

        select.insertAdjacentHTML('beforeend', '<option value="">-- Select section --</option>');
        matches.forEach(s => {
            const option = document.createElement('option');
            option.value = s.section;
            option.textContent = s.section;
            if (s.section === OLD_SECTION) option.selected = true;
            select.appendChild(option);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach(toggle => toggle.addEventListener('click', () => {
        const field = document.getElementById(toggle.dataset.passwordToggle);
        const show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(show));
        toggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        field.focus();
    }));

    filterSections();
</script>
<script src="{{ asset('js/auth-form-validation.js') }}"></script>
</body>
</html>
