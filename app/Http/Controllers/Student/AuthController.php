<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\StudentAccount;
use App\Support\AuditLogger;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use App\Support\StrongPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use VerifiesNewDevices;

    protected function deviceGuard(): string
    {
        return 'student';
    }

    protected function devicePanel(): string
    {
        return 'Student';
    }

    protected function deviceLoginRoute(): string
    {
        return 'student.login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'student.dashboard';
    }

    protected function deviceErrorField(): string
    {
        return 'student_id';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return StudentAccount::find($id);
    }

    public function showLogin(Request $request)
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        $recovery = $request->session()->get('student_password_recovery', []);
        $recoveryStep = $recovery['stage'] ?? ($request->old('recovery_action') === 'email' ? 'email' : 'login');
        $recoveryEmail = $recovery['email'] ?? $request->old('email', '');

        return view('student.auth.login', compact('recoveryStep', 'recoveryEmail'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'password' => StrongPassword::loginRules(),
        ], StrongPassword::loginMessages());

        $security = LoginSecurity::for($request, 'student', $credentials['student_id']);
        $security->assertNotLocked('student_id');
        $security->assertCaptcha($request, 'student_id');

        $student = StudentAccount::where('student_id', $credentials['student_id'])->first();

        if (! $student || ! Hash::check($credentials['password'], $student->password)) {
            $security->fail('student_id');
        }

        // Checked only after the password, so an outsider cannot use the login
        // form to learn which student accounts have been deactivated.
        if ($this->accountIsDeactivated($student)) {
            AuditLogger::record('authentication.blocked', 'student', null, 'student_account', $student->student_id, [
                'reason' => 'account_inactive',
            ]);

            throw ValidationException::withMessages([
                'student_id' => 'This student account is inactive. Please contact the registrar.',
            ]);
        }

        $security->clear();
        $request->session()->forget('student_password_recovery');

        return $this->completeLogin($request, $student, $request->boolean('remember'));
    }

    public function sendRecoveryCode(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [
            'email.required' => 'Enter the email registered to your student account.',
            'email.email' => 'Enter a valid email address.',
        ]);

        $email = strtolower(trim($data['email']));
        $student = StudentAccount::whereRaw('LOWER(email) = ?', [$email])->first();

        AuditLogger::record('password_reset.requested', 'student', null, 'student_account', null, [
            'identifier_hash' => hash('sha256', $email),
        ]);

        if (! $student) {
            return redirect()->route('student.login')
                ->with('recovery_status', 'If that email belongs to a student account, a verification code has been sent.');
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::send('emails.student-password-code', [
                'studentName' => $student->full_name,
                'code' => $code,
                'expiresInMinutes' => 10,
            ], function ($message) use ($email) {
                $message->to($email)->subject('Your ClearanceMS verification code');
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput(['recovery_action' => 'email', 'email' => $email])
                ->withErrors(['email' => 'The verification email could not be sent. Please try again shortly.']);
        }

        $request->session()->put('student_password_recovery', [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
            'stage' => 'code',
        ]);

        return redirect()->route('student.login')
            ->with('recovery_status', 'A six-digit verification code was sent to your registered email.');
    }

    public function verifyRecoveryCode(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ], [
            'verification_code.required' => 'Enter the six-digit verification code.',
            'verification_code.digits' => 'The verification code must contain exactly six digits.',
        ]);

        $recovery = $request->session()->get('student_password_recovery');

        if (! is_array($recovery) || ($recovery['stage'] ?? null) !== 'code') {
            return redirect()->route('student.login')->withErrors([
                'email' => 'Start a new password recovery request.',
            ])->withInput(['recovery_action' => 'email']);
        }

        if (now()->timestamp > ($recovery['expires_at'] ?? 0)) {
            $email = $recovery['email'] ?? '';
            $request->session()->forget('student_password_recovery');

            return redirect()->route('student.login')->withErrors([
                'email' => 'The verification code expired. Request a new code.',
            ])->withInput(['recovery_action' => 'email', 'email' => $email]);
        }

        $recovery['attempts'] = (int) ($recovery['attempts'] ?? 0) + 1;

        if (! Hash::check($data['verification_code'], $recovery['code_hash'] ?? '')) {
            if ($recovery['attempts'] >= 5) {
                $email = $recovery['email'] ?? '';
                $request->session()->forget('student_password_recovery');

                return redirect()->route('student.login')->withErrors([
                    'email' => 'Too many incorrect attempts. Request a new verification code.',
                ])->withInput(['recovery_action' => 'email', 'email' => $email]);
            }

            $request->session()->put('student_password_recovery', $recovery);

            return back()->withErrors([
                'verification_code' => 'The verification code is incorrect. Please try again.',
            ]);
        }

        unset($recovery['code_hash'], $recovery['expires_at'], $recovery['attempts']);
        $recovery['stage'] = 'reset';
        $recovery['verified_at'] = now()->timestamp;
        $request->session()->put('student_password_recovery', $recovery);

        return redirect()->route('student.login')
            ->with('recovery_status', 'Email verified. You can now create your new password.');
    }

    public function resetRecoveredPassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $recovery = $request->session()->get('student_password_recovery');
        $verifiedAt = (int) ($recovery['verified_at'] ?? 0);

        if (! is_array($recovery) || ($recovery['stage'] ?? null) !== 'reset'
            || $verifiedAt === 0 || now()->timestamp - $verifiedAt > 900) {
            $request->session()->forget('student_password_recovery');

            return redirect()->route('student.login')->withErrors([
                'email' => 'Your verified password-reset session expired. Request a new code.',
            ])->withInput(['recovery_action' => 'email']);
        }

        $updated = StudentAccount::whereRaw('LOWER(email) = ?', [$recovery['email']])
            ->update(['password' => Hash::make($data['password'])]);

        if (! $updated) {
            $request->session()->forget('student_password_recovery');

            return redirect()->route('student.login')->withErrors([
                'email' => 'The student account is no longer available.',
            ]);
        }

        $request->session()->forget('student_password_recovery');
        $request->session()->regenerateToken();

        AuditLogger::record('password_reset.completed', 'student', null, 'student_account', null, [
            'identifier_hash' => hash('sha256', strtolower((string) $recovery['email'])),
        ]);

        return redirect()->route('student.login')
            ->with('status', 'Your password was changed successfully. You can now sign in.');
    }

    public function cancelPasswordRecovery(Request $request)
    {
        $request->session()->forget('student_password_recovery');

        return redirect()->route('student.login');
    }

    /**
     * A graduated or suspended student keeps every record but cannot sign in.
     * Guarded on the column: deploys never migrate, so a database may not have
     * it yet, and there a student can never be inactive.
     */
    private function accountIsDeactivated(StudentAccount $student): bool
    {
        return Schema::hasColumn('student_account', 'status')
            && strtolower(trim((string) ($student->status ?? 'active'))) === 'inactive';
    }

    public function logout(Request $request)
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'student.login');
    }
}
