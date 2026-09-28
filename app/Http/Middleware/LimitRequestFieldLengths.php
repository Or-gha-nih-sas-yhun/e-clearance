<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestFieldLengths
{
    private const LIMITS = [
        'email' => 254,
        'ms_account' => 254,
        'login' => 150,
        'password' => 128,
        'password_confirmation' => 128,
        'verification_code' => 6,
        'captcha_answer' => 5,
        'recaptcha_token' => 4096,
        'firstname' => 100,
        'lastname' => 100,
        'title' => 200,
        'message' => 2000,
        'remarks' => 3000,
        'description' => 3000,
        'instructions' => 3000,
        'search' => 100,
    ];

    private const EXCLUDED = ['_token', '_method', 'signature_data'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            $errors = [];
            $this->inspect($request->except(self::EXCLUDED), '', $errors);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return $next($request);
    }

    /** @param array<string, mixed> $values @param array<string, string> $errors */
    private function inspect(array $values, string $prefix, array &$errors): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $this->inspect($value, $path, $errors);

                continue;
            }
            if (! is_string($value)) {
                continue;
            }

            $field = (string) $key;
            $limit = str_contains($path, '.questions.') ? 500 : (self::LIMITS[$field] ?? 10000);
            if (mb_strlen($value) > $limit) {
                $errors[$path] = 'This field may not contain more than '.number_format($limit).' characters.';
            }
        }
    }
}
