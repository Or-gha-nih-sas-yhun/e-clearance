<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TreasurerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('treasurer')->check()) {
            return PostLogout::guestRedirect($request, 'treasurer.login');
        }

        PostLogout::clear($request, 'treasurer.login');

        return $next($request);
    }
}
