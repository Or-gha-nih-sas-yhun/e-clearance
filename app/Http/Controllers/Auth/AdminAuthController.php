<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\VerifiesNewDevices;
use App\Http\Controllers\Controller;
use App\Models\MainAdmin;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use App\Support\StrongPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    use VerifiesNewDevices;

    protected function deviceGuard(): string
    {
        return 'admin';
    }

    protected function devicePanel(): string
    {
        return 'Main Admin';
    }

    protected function deviceLoginRoute(): string
    {
        return 'login';
    }

    protected function deviceHomeRoute(): string
    {
        return 'dashboard';
    }

    protected function deviceAccount(string|int $id): ?Authenticatable
    {
        return MainAdmin::find($id);
    }

    /**
     * Main Admin never banks a device. This portal creates and deletes every
     * other portal's accounts, so it pays the code on every sign-in rather than
     * trusting a browser for a month.
     */
    protected function deviceTrustAllowed(): bool
    {
        return false;
    }

    protected function afterDeviceLogin(Request $request, Authenticatable $account): void
    {
        $request->session()->put([
            'admin_id' => $account->id,
            'admin_name' => $account->name,
            'admin_email' => $account->email,
        ]);
    }

    public function showLogin()
    {
        return view('mainAdmin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => StrongPassword::loginRules(),
        ], StrongPassword::loginMessages());

        $security = LoginSecurity::for($request, 'admin', $credentials['email']);
        $security->assertNotLocked('email');
        $security->assertCaptcha($request, 'email');

        $admin = MainAdmin::whereRaw('LOWER(email) = ?', [strtolower(trim($credentials['email']))])->first();
        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $security->fail('email');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_main_admin');

        return $this->completeLogin($request, $admin);
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->forget(['admin_id', 'admin_name', 'admin_email']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'login');
    }
}
