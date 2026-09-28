<?php

use App\Http\Middleware\AuditSecurityEvents;
use App\Http\Middleware\CaptureClientLocation;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\InstructorAuthenticate;
use App\Http\Middleware\LimitRequestFieldLengths;
use App\Http\Middleware\MainAdminAuth;
use App\Http\Middleware\NormalizeStudentIds;
use App\Http\Middleware\OfficeAuthenticate;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RegistrarAuthenticate;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StudentAuthenticate;
use App\Http\Middleware\TreasurerAuthenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([ForceHttps::class, SecurityHeaders::class]);
        $middleware->trustHosts();
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }
        $middleware->web(append: [
            NormalizeStudentIds::class,
            LimitRequestFieldLengths::class,
            CaptureClientLocation::class,
            'throttle:web',
            AuditSecurityEvents::class,
        ]);
        $middleware->alias([
            'admin.auth' => MainAdminAuth::class,
            'instructor.auth' => InstructorAuthenticate::class,
            'office.auth' => OfficeAuthenticate::class,
            'no.history' => PreventBackHistory::class,
            'student.auth' => StudentAuthenticate::class,
            'registrar.auth' => RegistrarAuthenticate::class,
            'treasurer.auth' => TreasurerAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->is('api/*', 'notifications/api*'),
        );
    })->create();
