<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height:100vh; display:grid; place-items:center; padding:24px; background:linear-gradient(135deg,#dff3ff,#f8fbff); }
        .reset-card { width:min(480px,100%); border:1px solid rgba(255,255,255,.85); border-radius:20px; background:rgba(255,255,255,.82); box-shadow:0 20px 55px rgba(30,90,140,.16); backdrop-filter:blur(18px); }
        .reset-icon { width:60px; height:60px; display:grid; place-items:center; margin:0 auto 16px; border-radius:50%; color:#fff; background:linear-gradient(135deg,#0ea5e9,#2563eb); font-size:1.5rem; }
    </style>
</head>
<body>
@include('partials.app-shell-body')
<main class="card reset-card"><div class="card-body p-4 p-md-5">
    <div class="reset-icon"><i class="bi bi-shield-lock-fill"></i></div>
    <h1 class="h4 text-center mb-2">Create a new password</h1>
    <p class="text-secondary text-center mb-4">Resetting the password for the {{ $portalName }} portal.</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('account-recovery.update', $portal) }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3"><label class="form-label fw-semibold" for="email">Registered email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="password">New password</label><input class="form-control" id="password" type="password" name="password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" placeholder="e.g. StrongPass1!" title="At least 8 characters with uppercase, lowercase, number, and special character." required autocomplete="new-password"><div class="form-text">Use at least 8 characters with uppercase, lowercase, number, and special character.</div></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="password_confirmation">Confirm new password</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" minlength="8" placeholder="Re-enter your new password" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check-circle"></i> Reset Password</button>
    </form>
    <a href="{{ route($loginRoute) }}" class="btn btn-outline-secondary w-100 mt-3"><i class="bi bi-arrow-left"></i> Back to Login</a>
</div></main>
</body>
</html>
