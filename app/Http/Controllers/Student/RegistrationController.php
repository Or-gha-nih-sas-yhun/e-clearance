<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ProgramSection;
use App\Models\StudentAccount;
use App\Models\StudentRegistry;
use App\Support\AuditLogger;
use App\Support\PersonName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Student self-registration, gated on the `student_registry` roster.
 *
 * Three steps: prove you own a Microsoft account that the college has listed
 * and not yet used ("send code"), enter the emailed code ("verify"), then fill
 * in the rest of your details ("register").
 *
 * The identity half of the final form — student_id and the email — is never
 * read back from the request. Those two values are carried in the session from
 * the row that was verified, because a readonly input is only readonly until
 * someone opens developer tools.
 */
class RegistrationController extends Controller
{
    private const SESSION_KEY = 'student_registration';

    private const CODE_LIFETIME_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /** How long a verified session may sit on the form before it must start over. */
    private const FORM_LIFETIME_SECONDS = 1800;

    private const PROGRAMS = ['BSIT', 'BSHM', 'BSED', 'BEED', 'BSBA'];

    private const YEAR_LEVELS = ['1', '2', '3', '4'];

    /** Step 1 — confirm the Microsoft account is on the roster, then email a code. */
    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'ms_account' => ['required', 'email', 'max:150'],
        ], [
            'ms_account.required' => 'Enter your Microsoft account.',
            'ms_account.email' => 'Enter a valid Microsoft account address.',
        ]);

        $msAccount = strtolower(trim($data['ms_account']));

        if (! Schema::hasTable('student_registry')) {
            return $this->backToRegister('Account registration is not available yet. Please contact your administrator.');
        }

        $entry = StudentRegistry::whereRaw('LOWER(ms_account) = ?', [$msAccount])->first();
        $eligible = $entry !== null
            && $entry->status === StudentRegistry::STATUS_INACTIVE
            && ! $this->accountAlreadyExists($entry->student_id, $msAccount);

        AuditLogger::record('student_registration.requested', 'student', null, 'student_registry', $entry->id ?? null, [
            'identifier_hash' => hash('sha256', $msAccount),
            'eligible' => $eligible,
        ]);

        if (! $eligible) {
            return $this->backToRegister(
                'That Microsoft account cannot be registered. It may already have an account, or it is not on the registration list — please contact your administrator.',
            );
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::send('emails.student-registration-code', [
                'msAccount' => $msAccount,
                'studentId' => $entry->student_id,
                'code' => $code,
                'expiresInMinutes' => self::CODE_LIFETIME_MINUTES,
            ], function ($message) use ($msAccount) {
                $message->to($msAccount)->subject('Your ClearanceMS registration code');
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToRegister('The registration code could not be emailed. Please try again shortly.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'stage' => 'code',
            'registry_id' => $entry->id,
            'student_id' => $entry->student_id,
            'ms_account' => $msAccount,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES)->timestamp,
            'attempts' => 0,
        ]);

        return redirect()->route('student.login')
            ->with('registration_status', 'A six-digit registration code was sent to your Microsoft account.');
    }

    /** Step 2 — check the emailed code and unlock the registration form. */
    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ], [
            'verification_code.required' => 'Enter the six-digit registration code.',
            'verification_code.digits' => 'The registration code must contain exactly six digits.',
        ]);

        $state = $this->state($request);

        if ($state === null || ($state['stage'] ?? null) !== 'code') {
            return $this->restart($request, 'Start a new registration request.');
        }

        if (now()->timestamp > (int) ($state['expires_at'] ?? 0)) {
            return $this->restart($request, 'The registration code expired. Request a new code.');
        }

        if (! Hash::check($data['verification_code'], (string) ($state['code_hash'] ?? ''))) {
            $attempts = (int) ($state['attempts'] ?? 0) + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                return $this->restart($request, 'Too many incorrect codes. Start a new registration request.');
            }

            $state['attempts'] = $attempts;
            $request->session()->put(self::SESSION_KEY, $state);

            return back()->withErrors(['verification_code' => 'The registration code is incorrect. Please try again.']);
        }

        unset($state['code_hash'], $state['expires_at'], $state['attempts']);
        $state['stage'] = 'form';
        $state['verified_at'] = now()->timestamp;
        $request->session()->put(self::SESSION_KEY, $state);

        AuditLogger::record('student_registration.verified', 'student', null, 'student_registry', $state['registry_id'] ?? null, [
            'identifier_hash' => hash('sha256', (string) $state['ms_account']),
        ]);

        return redirect()->route('student.register');
    }

    /** Step 3 — the form itself, reachable only with a verified session. */
    public function showForm(Request $request)
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        $state = $this->verifiedState($request);

        if ($state === null) {
            return $this->restart($request, 'Your registration session expired. Start again.');
        }

        return view('student.auth.register', [
            'studentId' => $state['student_id'],
            'msAccount' => $state['ms_account'],
            'sectionsData' => Schema::hasTable('program_sections')
                ? ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')->get()
                : collect(),
            'programsList' => self::PROGRAMS,
            'yearLevelOptions' => ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'],
        ]);
    }

    /** Step 3 (submit) — create the student account and consume the roster entry. */
    public function register(Request $request)
    {
        $state = $this->verifiedState($request);

        if ($state === null) {
            return $this->restart($request, 'Your registration session expired. Start again.');
        }

        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'program' => ['required', Rule::in(self::PROGRAMS)],
            'year_level' => ['required', Rule::in(self::YEAR_LEVELS)],
            'section' => ['required', 'string', 'max:50'],
            'student_type' => ['required', Rule::in(['Regular', 'Irregular'])],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], PersonName::messages('firstname', 'middlename', 'lastname'));

        // Identity comes from the verified session, never from the submitted form.
        $studentId = (string) $state['student_id'];
        $msAccount = (string) $state['ms_account'];
        $registryId = $state['registry_id'] ?? null;

        if ($this->accountAlreadyExists($studentId, $msAccount)) {
            return $this->restart($request, 'An account already exists for that Microsoft account. Please sign in instead.');
        }

        // Every table here is MyISAM, so DB::transaction() would roll nothing
        // back. Claim the roster entry first with a conditional update — only a
        // row still 'inactive' is affected — so two submissions cannot both win.
        $claimed = DB::table('student_registry')
            ->where('id', $registryId)
            ->where('status', StudentRegistry::STATUS_INACTIVE)
            ->update([
                'status' => StudentRegistry::STATUS_ACTIVE,
                'registered_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return $this->restart($request, 'That Microsoft account has already been registered. Please sign in instead.');
        }

        try {
            StudentAccount::create([
                'student_id' => $studentId,
                'firstname' => $data['firstname'],
                'middlename' => $data['middlename'] ?? null,
                'lastname' => $data['lastname'],
                'suffix' => $data['suffix'] ?? null,
                'email' => $msAccount,
                'password' => Hash::make($data['password']),
                'program' => $data['program'],
                'year_level' => $data['year_level'],
                'section' => $data['section'],
                'student_type' => $data['student_type'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            // Hand the roster entry back so the student can try again.
            DB::table('student_registry')->where('id', $registryId)->update([
                'status' => StudentRegistry::STATUS_INACTIVE,
                'registered_at' => null,
                'updated_at' => now(),
            ]);

            return back()->withInput()->withErrors([
                'firstname' => 'Your account could not be created. Please try again shortly.',
            ]);
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerateToken();

        AuditLogger::record('student_registration.completed', 'student', null, 'student_account', $studentId, [
            'identifier_hash' => hash('sha256', $msAccount),
        ]);

        return redirect()->route('student.login')
            ->with('status', 'Your account is registered. You can now sign in with your Student ID and password.');
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('student.login');
    }

    /** @return array<string, mixed>|null */
    private function state(Request $request): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) ? $state : null;
    }

    /** The session as it must look before the form may be shown or accepted. */
    private function isVerified(array $state): bool
    {
        $verifiedAt = (int) ($state['verified_at'] ?? 0);

        return ($state['stage'] ?? null) === 'form'
            && $verifiedAt > 0
            && (now()->timestamp - $verifiedAt) <= self::FORM_LIFETIME_SECONDS
            && ! empty($state['student_id'])
            && ! empty($state['ms_account']);
    }

    /** @return array<string, mixed>|null */
    private function verifiedState(Request $request): ?array
    {
        $state = $this->state($request);

        return $state !== null && $this->isVerified($state) ? $state : null;
    }

    private function accountAlreadyExists(string $studentId, string $msAccount): bool
    {
        return StudentAccount::where('student_id', $studentId)->exists()
            || StudentAccount::whereRaw('LOWER(email) = ?', [strtolower($msAccount)])->exists();
    }

    private function backToRegister(string $message)
    {
        return back()
            ->withInput(['registration_action' => 'account'])
            ->withErrors(['ms_account' => $message]);
    }

    private function restart(Request $request, string $message)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('student.login')
            ->withInput(['registration_action' => 'account'])
            ->withErrors(['ms_account' => $message]);
    }
}
