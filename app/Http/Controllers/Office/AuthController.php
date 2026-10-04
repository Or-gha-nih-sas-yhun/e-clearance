<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\AdminPersonnel;
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
        return 'office';
    }

    protected function devicePanel(): string
    {
        return 'Office';
    }

    protected function deviceLoginRoute(): string
    {
        return 'office.login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'office.dashboard';
    }

    protected function deviceErrorField(): string
    {
        return 'login';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return AdminPersonnel::find($id);
    }

    public function showLogin()
    {
        if (Auth::guard('office')->check()) {
            return redirect()->route('office.dashboard');
        }

        return view('office.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // personnel_id or email
            'password' => StrongPassword::loginRules(),
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(AdminPersonnel::$validRoles))],
        ], StrongPassword::loginMessages());

        $personnel = AdminPersonnel::where('email', $credentials['login'])
            ->orWhere('personnel_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'office', $personnel?->personnel_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $personnel || ! Hash::check($credentials['password'], $personnel->password)) {
            $security->fail('login');
        }

        if ($personnel->role !== $credentials['role']) {
            $security->fail('login', 'role');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_office');

        return $this->completeLogin($request, $personnel, $request->boolean('remember'));
    }

    public function logout(Request $request)
    {
        Auth::guard('office')->logout();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'office.login');
    }
}
