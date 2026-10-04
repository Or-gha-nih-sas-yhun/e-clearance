<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OfficeAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('office')->check()) {
            return PostLogout::guestRedirect($request, 'office.login');
        }

        PostLogout::clear($request, 'office.login');

        return $next($request);
    }
}
