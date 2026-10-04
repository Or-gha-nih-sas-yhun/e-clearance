<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\Registrar;
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
        return 'registrar';
    }

    protected function devicePanel(): string
    {
        return 'Registrar';
    }

    protected function deviceLoginRoute(): string
    {
        return 'registrar.login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'registrar.dashboard';
    }

    protected function deviceErrorField(): string
    {
        return 'login';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return Registrar::find($id);
    }

    public function showLogin()
    {
        if (Auth::guard('registrar')->check()) {
            return redirect()->route('registrar.dashboard');
        }

        return view('registrar.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // email or registrar_id
            'password' => StrongPassword::loginRules(),
        ], StrongPassword::loginMessages());

        $registrar = Registrar::where('email', $credentials['login'])
            ->orWhere('registrar_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'registrar', $registrar?->registrar_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $registrar || ! Hash::check($credentials['password'], $registrar->password)) {
            $security->fail('login');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_registrar');

        return $this->completeLogin($request, $registrar, $request->boolean('remember'));
    }

    public function logout(Request $request)
    {
        Auth::guard('registrar')->logout();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'registrar.login');
    }
}
