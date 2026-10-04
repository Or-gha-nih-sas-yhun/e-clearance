<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MainAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('admin')->check()) {
            return PostLogout::guestRedirect($request, 'login');
        }

        PostLogout::clear($request, 'login');

        return $next($request);
    }
}
