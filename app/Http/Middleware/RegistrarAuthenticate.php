<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RegistrarAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('registrar')->check()) {
            return PostLogout::guestRedirect($request, 'registrar.login');
        }

        PostLogout::clear($request, 'registrar.login');

        return $next($request);
    }
}
