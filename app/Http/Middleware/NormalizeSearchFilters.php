<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeSearchFilters
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            $fields = ['search'];

            if ($request->routeIs('treasurers.index')) {
                $fields = array_merge($fields, ['department', 'program', 'year_level', 'section']);
            } elseif ($request->routeIs('assignments.index')) {
                $fields[] = 'section';
            }

            foreach ($fields as $field) {
                if (! $request->query->has($field)) {
                    continue;
                }

                $value = $request->query($field);
                $request->query->set(
                    $field,
                    is_string($value) ? (preg_replace('/[^\p{L}\p{N} -]/u', '', $value) ?? '') : '',
                );
            }
        }

        return $next($request);
    }
}
