<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\Treasurer;
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
        return 'treasurer';
    }

    protected function devicePanel(): string
    {
        return 'Treasurer';
    }

    protected function deviceLoginRoute(): string
    {
        return 'treasurer.login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'treasurer.dashboard';
    }

    protected function deviceErrorField(): string
    {
        return 'login';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return Treasurer::find($id);
    }

    public function showLogin()
    {
        if (Auth::guard('treasurer')->check()) {
            return redirect()->route('treasurer.dashboard');
        }

        return view('treasurer.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // treasurer_id or email
            'password' => StrongPassword::loginRules(),
            'treasurer_type' => ['required', 'string', 'in:department,section'],
        ], StrongPassword::loginMessages());

        $treasurer = Treasurer::where('email', $credentials['login'])
            ->orWhere('treasurer_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'treasurer', $treasurer?->treasurer_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $treasurer
            || $treasurer->treasurer_type !== $credentials['treasurer_type']
            || ! Hash::check($credentials['password'], $treasurer->password)) {
            $security->fail('login');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_treasurer');

        return $this->completeLogin($request, $treasurer, $request->boolean('remember'));
    }

    public function logout(Request $request)
    {
        Auth::guard('treasurer')->logout();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'treasurer.login');
    }
}
