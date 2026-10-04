<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use App\Support\StrongPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use VerifiesNewDevices;

    protected function deviceGuard(): string
    {
        return 'instructor';
    }

    protected function devicePanel(): string
    {
        return 'Instructor';
    }

    protected function deviceLoginRoute(): string
    {
        return 'instructor.login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'instructor.dashboard';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return Instructor::find($id);
    }

    // GET /instructor/login  (was index.php)
    public function showLogin()
    {
        if (Auth::guard('instructor')->check()) {
            return redirect()->route('instructor.dashboard');
        }

        return view('instructor.instructor.login');
    }

    // POST /instructor/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => StrongPassword::loginRules(),
        ], StrongPassword::loginMessages());

        $security = LoginSecurity::for($request, 'instructor', $data['email']);
        $security->assertNotLocked('email');
        $security->assertCaptcha($request, 'email');

        $instructor = Instructor::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])->first();
        if (! $instructor || ! Hash::check($data['password'], $instructor->password)) {
            $security->fail('email');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_instructor');

        return $this->completeLogin($request, $instructor, $request->boolean('remember'));
    }

    // POST /instructor/logout (was logout.php)
    public function logout(Request $request)
    {
        Auth::guard('instructor')->logout();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'instructor.login');
    }
}
