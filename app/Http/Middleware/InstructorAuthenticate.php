<?php

namespace App\Http\Middleware;

use App\Support\PostLogout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InstructorAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('instructor')->check()) {
            return PostLogout::guestRedirect($request, 'instructor.login');
        }

        PostLogout::clear($request, 'instructor.login');

        return $next($request);
    }
}
