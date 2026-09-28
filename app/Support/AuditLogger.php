<?php

namespace App\Support;

use App\Models\SecurityAuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class AuditLogger
{
    private const GUARDS = ['admin', 'instructor', 'office', 'registrar', 'treasurer', 'student', 'web'];

    private const IDENTIFIERS = [
        'admin' => 'id',
        'instructor' => 'instructor_id',
        'office' => 'personnel_id',
        'registrar' => 'registrar_id',
        'treasurer' => 'treasurer_id',
        'student' => 'student_id',
        'web' => 'id',
    ];

    private static ?bool $tableAvailable = null;

    public static function record(
        string $event,
        ?string $guard = null,
        ?Authenticatable $actor = null,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        array $metadata = [],
    ): void {
        try {
            if (! self::tableAvailable()) {
                return;
            }

            if ($actor === null && $guard === null) {
                $current = self::currentActor();
                $guard = $current['guard'] ?? null;
                $actor = $current['actor'] ?? null;
            }

            $request = app()->bound('request') ? request() : null;
            $location = $request?->hasSession()
                ? $request->session()->get('security.client_location')
                : null;
            if (is_array($location)) {
                $metadata['location'] = $location;
            }

            SecurityAuditLog::create([
                'event' => Str::limit($event, 100, ''),
                'actor_guard' => $guard,
                'actor_id' => self::actorIdentifier($guard, $actor),
                'subject_type' => $subjectType ? Str::limit($subjectType, 100, '') : null,
                'subject_id' => $subjectId === null ? null : Str::limit((string) $subjectId, 100, ''),
                'metadata' => self::sanitize($metadata),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Security audit event could not be persisted.', [
                'event' => Str::limit($event, 100, ''),
                'exception' => $exception::class,
            ]);
        }
    }

    /** @return array{guard: string, actor: Authenticatable}|null */
    public static function currentActor(): ?array
    {
        foreach (self::GUARDS as $guard) {
            if (Auth::guard($guard)->check()) {
                return ['guard' => $guard, 'actor' => Auth::guard($guard)->user()];
            }
        }

        return null;
    }

    private static function actorIdentifier(?string $guard, ?Authenticatable $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        $field = self::IDENTIFIERS[$guard ?? ''] ?? null;
        $value = $field ? ($actor->{$field} ?? null) : null;

        return Str::limit((string) ($value ?: $actor->getAuthIdentifier()), 100, '');
    }

    private static function tableAvailable(): bool
    {
        if (self::$tableAvailable === null) {
            self::$tableAvailable = Schema::hasTable('security_audit_logs');
        }

        return self::$tableAvailable;
    }

    private static function sanitize(array $metadata, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        $safe = [];
        foreach ($metadata as $key => $value) {
            $key = Str::limit((string) $key, 80, '');
            if (preg_match('/password|passphrase|secret|token|otp|verification.?code|authorization|cookie/i', $key)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::sanitize($value, $depth + 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_string($value)) {
                $safe[$key] = Str::limit($value, 1000, '');
            }
        }

        return $safe;
    }
}
