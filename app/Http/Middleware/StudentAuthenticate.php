<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StudentAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('student')->check()) {
            return PostLogout::guestRedirect($request, 'student.login');
        }

        PostLogout::clear($request, 'student.login');

        return $next($request);
    }
}
