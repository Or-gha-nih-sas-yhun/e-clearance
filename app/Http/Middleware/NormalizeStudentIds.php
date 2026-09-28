<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeStudentIds
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->request->has('student_id')) {
            $request->merge(['student_id' => $this->format($request->input('student_id'))]);
        }

        if (is_array($request->input('student_ids'))) {
            $request->merge([
                'student_ids' => array_map(fn ($value) => $this->format($value), $request->input('student_ids')),
            ]);
        }

        return $next($request);
    }

    private function format(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if (! preg_match('/^[\d\s-]+$/', $value)) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return strlen($digits) === 8
            ? substr($digits, 0, 4).'-'.substr($digits, 4)
            : $value;
    }
}
