<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyRecaptchaV3
{
    public function handle(Request $request, Closure $next, string $action = 'login'): Response
    {
        if (! config('services.recaptcha.enabled')) {
            return $next($request);
        }

        $token = $request->input('recaptcha_token');
        if (! is_string($token) || $token === '' || mb_strlen($token) > 4096) {
            return $this->reject($request, $action, 'missing_token');
        }

        try {
            $verification = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.recaptcha.verify_url'), [
                    'secret' => (string) config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException) {
            return $this->reject($request, $action, 'verification_unavailable');
        }

        $result = $verification->json();
        $score = is_numeric($result['score'] ?? null) ? (float) $result['score'] : 0.0;
        $valid = $verification->successful()
            && ($result['success'] ?? false) === true
            && hash_equals($action, (string) ($result['action'] ?? ''))
            && $score >= (float) config('services.recaptcha.minimum_score', 0.5);

        if (! $valid) {
            return $this->reject($request, $action, 'low_confidence', $score);
        }

        $request->request->remove('recaptcha_token');

        return $next($request);
    }

    private function reject(Request $request, string $action, string $reason, ?float $score = null): never
    {
        AuditLogger::record('authentication.recaptcha_failed', metadata: [
            'action' => $action,
            'reason' => $reason,
            'score' => $score,
        ]);

        throw ValidationException::withMessages([
            'recaptcha' => $reason === 'verification_unavailable'
                ? 'The security check is temporarily unavailable. Please try again.'
                : 'The security check could not verify this login. Please try again.',
        ]);
    }
}
