<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostLogout
{
    private const SESSION_KEY = 'security.redirect_history_to_landing';

    public static function response(Request $request, string $loginRoute): RedirectResponse
    {
        // Keep the history marker limited to the portal that logged out. Other
        // portal sessions can stay active in the same browser without clearing it.
        $request->session()->put(self::sessionKey($loginRoute), true);

        $response = redirect()->route($loginRoute)
            ->with('status', 'You have been logged out successfully.');
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Clear-Site-Data', '"cache"');

        return $response;
    }

    public static function guestRedirect(Request $request, string $loginRoute): RedirectResponse
    {
        if ((bool) $request->session()->get(self::sessionKey($loginRoute), false)) {
            return redirect()->route('landing');
        }

        return redirect()->route($loginRoute);
    }

    public static function clear(Request $request, string $loginRoute): void
    {
        $request->session()->forget(self::sessionKey($loginRoute));
    }

    private static function sessionKey(string $loginRoute): string
    {
        return self::SESSION_KEY.'.'.str_replace('.', '_', $loginRoute);
    }
}
