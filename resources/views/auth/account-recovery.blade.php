<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #eaf2ff, #f8fafc); }
        .recovery-card { width: min(460px, 100%); border: 0; border-radius: 18px; box-shadow: 0 18px 45px rgba(15, 23, 42, .14); }
        .recovery-icon { width: 58px; height: 58px; display: grid; place-items: center; margin: 0 auto 16px; border-radius: 50%; color: #fff; background: #2563eb; font-size: 1.5rem; }
    </style>
</head>
<body>
@include('partials.app-shell-body')
    <main class="card recovery-card">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="recovery-icon"><i class="bi bi-key-fill"></i></div>
            <h1 class="h4 mb-3">Forgot your password?</h1>
            <p class="text-secondary mb-4">Enter the email registered to your {{ $portalName }} account. We will prepare a secure reset link that expires in 30 minutes.</p>
            @if(session('recovery_status'))
                <div class="alert alert-success text-start">{{ session('recovery_status') }}</div>
            @endif
            @if(session('local_reset_url'))
                <div class="alert alert-warning text-start small">Email delivery is in local log mode. Use the button below to continue during development.</div>
                <a href="{{ session('local_reset_url') }}" class="btn btn-success w-100 mb-3"><i class="bi bi-shield-lock"></i> Continue to Password Reset</a>
            @endif
            <form method="POST" action="{{ route('account-recovery.send', $portal) }}" class="text-start">
                @csrf
                <label for="email" class="form-label fw-semibold">Registered email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-envelope"></i> Send Reset Link</button>
            </form>
            <a href="{{ route($loginRoute) }}" class="btn btn-outline-secondary w-100 mt-3">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </main>
</body>
</html>
