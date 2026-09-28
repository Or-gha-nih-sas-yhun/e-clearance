<?php

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use App\Support\DeviceFingerprint;
use App\Support\ListPageSize;
use App\Support\PortalAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Main Admin view over security_audit_logs: who did what, from which device, when.
 *
 * The rows are already being written — Login/Logout listeners in AppServiceProvider,
 * the AuditSecurityEvents middleware on every state-changing request, and the login
 * hardening in App\Support\LoginSecurity all call App\Support\AuditLogger. What was
 * missing was somewhere to read them, so this controller only ever selects.
 *
 * The table is guarded with Schema::hasTable() because deploys never run artisan
 * migrate; on a database predating the audit migration the page renders an
 * explanatory empty state rather than a 500.
 */
class ActivityLogController extends Controller
{
    /** Readable labels for the events AuditLogger actually records. */
    private const EVENT_LABELS = [
        'authentication.login' => 'Signed in',
        'authentication.logout' => 'Signed out',
        'authentication.failed' => 'Failed sign-in attempt',
        'authentication.blocked' => 'Sign-in blocked',
        'authentication.locked' => 'Account locked out',
        'authentication.captcha_failed' => 'Failed captcha check',
        'authentication.mfa_challenge_sent' => 'Verification code sent',
        'authentication.mfa_verified' => 'Verification code accepted',
        'authentication.mfa_failed' => 'Wrong verification code',
        'authentication.mfa_locked' => 'Verification locked out',
        'password_reset.requested' => 'Requested a password reset',
        'password_reset.completed' => 'Completed a password reset',
        'clearance.status_changed' => 'Changed a clearance status',
        'chat.message_sent' => 'Sent a chat message',
        'record.deleted' => 'Deleted a record',
        'account.password_changed' => 'Changed an account password',
        'account.updated' => 'Updated account details',
        'administrator.data_imported' => 'Imported data from CSV',
        'application.state_changed' => 'Changed system data',
    ];

    /** Event families offered as a filter, matched against the event prefix. */
    private const CATEGORIES = [
        'authentication' => 'Sign-in activity',
        'password_reset' => 'Password resets',
        'clearance' => 'Clearance decisions',
        'chat' => 'Messages',
        'account' => 'Account changes',
        'record' => 'Deletions',
        'administrator' => 'Data imports',
        'application' => 'Other changes',
    ];

    /** Events that represent a rejected sign-in, surfaced as their own metric. */
    private const REJECTED_SIGN_INS = [
        'authentication.failed',
        'authentication.blocked',
        'authentication.locked',
        'authentication.captcha_failed',
        'authentication.mfa_failed',
        'authentication.mfa_locked',
    ];

    public function index(Request $request)
    {
        if (! Schema::hasTable('security_audit_logs')) {
            return view('mainAdmin.activity.index', [
                'available' => false,
                'activities' => collect(),
                'metrics' => [],
                'devices' => collect(),
                'portals' => collect(),
                'portalOptions' => [],
                'categoryOptions' => self::CATEGORIES,
                'deviceOptions' => [],
            ]);
        }

        $filters = $this->filters($request);

        $activities = $this->scoped($filters)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(ListPageSize::from($request->limit))
            ->withQueryString();

        $activities->setCollection($this->decorate($activities->getCollection(), $filters['device']));

        $breakdown = $this->deviceBreakdown($filters);

        return view('mainAdmin.activity.index', [
            'available' => true,
            'activities' => $activities,
            'metrics' => $this->metrics($filters),
            'devices' => $breakdown,
            'portals' => $this->portalBreakdown($filters),
            'portalOptions' => $this->portalOptions(),
            'categoryOptions' => self::CATEGORIES,
            'deviceOptions' => $breakdown->pluck('category')->mapWithKeys(
                fn (string $category) => [$category => ucfirst($category)]
            )->all(),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{search: string, portal: string, category: string, device: string, from: string, to: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'portal' => array_key_exists((string) $request->query('portal'), PortalAccounts::PORTALS)
                ? (string) $request->query('portal') : '',
            'category' => array_key_exists((string) $request->query('category'), self::CATEGORIES)
                ? (string) $request->query('category') : '',
            'device' => in_array((string) $request->query('device'), DeviceFingerprint::CATEGORIES, true)
                ? (string) $request->query('device') : '',
            'from' => $this->date($request->query('from')) ?? '',
            'to' => $this->date($request->query('to')) ?? '',
        ];
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Everything except the device filter, which cannot be expressed in SQL — the
     * device is derived from the user_agent string in PHP.
     *
     * @param  array<string, string>  $filters
     */
    private function scoped(array $filters): Builder
    {
        return SecurityAuditLog::query()
            ->when($filters['portal'] !== '', fn (Builder $query) => $query->where('actor_guard', $filters['portal']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->where('event', 'like', $filters['category'].'.%'))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $query->where(fn (Builder $match) => $match
                    ->where('actor_id', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('event', 'like', $term)
                    ->orWhere('user_agent', 'like', $term)
                    ->orWhere('subject_id', 'like', $term));
            });
    }

    /**
     * Resolves each row's actor name and device, one name query per portal rather
     * than one per row.
     *
     * @param  Collection<int, SecurityAuditLog>  $rows
     * @return Collection<int, object>
     */
    private function decorate(Collection $rows, string $deviceFilter): Collection
    {
        $names = $rows->groupBy('actor_guard')->mapWithKeys(fn (Collection $group, $guard) => [
            (string) $guard => PortalAccounts::names((string) $guard, $group->pluck('actor_id')),
        ]);

        return $rows->map(function (SecurityAuditLog $row) use ($names) {
            $device = DeviceFingerprint::describe($row->user_agent);
            $guard = (string) $row->actor_guard;
            $locationData = is_array($row->metadata['location'] ?? null) ? $row->metadata['location'] : [];
            $latitude = is_numeric($locationData['latitude'] ?? null) ? (float) $locationData['latitude'] : null;
            $longitude = is_numeric($locationData['longitude'] ?? null) ? (float) $locationData['longitude'] : null;
            $location = $latitude !== null && $longitude !== null
                ? [
                    'label' => number_format($latitude, 5).', '.number_format($longitude, 5),
                    'url' => 'https://www.openstreetmap.org/?mlat='.rawurlencode((string) $latitude)
                        .'&mlon='.rawurlencode((string) $longitude).'#map=16/'.$latitude.'/'.$longitude,
                    'accuracy' => is_numeric($locationData['accuracy_meters'] ?? null)
                        ? (int) round((float) $locationData['accuracy_meters']) : null,
                ]
                : null;

            return (object) [
                'id' => $row->id,
                'event' => (string) $row->event,
                'label' => self::EVENT_LABELS[$row->event] ?? ucfirst(str_replace(['.', '_'], ' ', (string) $row->event)),
                'portal' => PortalAccounts::label($guard),
                'portal_icon' => PortalAccounts::icon($guard),
                'actor_id' => (string) ($row->actor_id ?? ''),
                'actor_name' => $names->get($guard)?->get((string) $row->actor_id) ?: null,
                'device' => $device,
                'ip_address' => (string) ($row->ip_address ?? ''),
                'location' => $location,
                'user_agent' => (string) ($row->user_agent ?? ''),
                'subject' => trim(implode(' · ', array_filter([$row->subject_type, $row->subject_id]))),
                'details' => $this->details($row),
                'at' => $row->created_at,
                'rejected' => in_array($row->event, self::REJECTED_SIGN_INS, true),
            ];
        })->when(
            $deviceFilter !== '',
            fn (Collection $decorated) => $decorated->filter(
                fn (object $row) => $row->device['category'] === $deviceFilter
            )->values(),
        );
    }

    /** Metadata worth showing in the table, minus the noisy or already-shown keys. */
    private function details(SecurityAuditLog $row): string
    {
        return collect(is_array($row->metadata) ? $row->metadata : [])
            ->except(['route', 'identifier_hash', 'guard', 'location'])
            ->filter(fn ($value) => $value !== null && $value !== '' && ! is_array($value))
            ->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', (string) $key)).': '.$value)
            ->take(3)
            ->implode(' · ');
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<int, array{label: string, value: string, hint: string, icon: string, tone: string}>
     */
    private function metrics(array $filters): array
    {
        // Counting distinct (guard, id) pairs has no portable single-column form —
        // MySQL takes COUNT(DISTINCT a, b) and SQLite does not — so the database
        // deduplicates and the small result set is counted here.
        $actors = (clone $this->scoped($filters))
            ->distinct()
            ->get(['actor_guard', 'actor_id'])
            ->count();

        return [
            [
                'label' => 'Recorded activities',
                'value' => number_format((clone $this->scoped($filters))->count()),
                'hint' => $this->rangeHint($filters),
                'icon' => 'bi bi-activity',
                'tone' => 'symbol-blue',
            ],
            [
                'label' => 'Accounts active',
                'value' => number_format($actors),
                'hint' => 'Distinct portal accounts',
                'icon' => 'bi bi-people',
                'tone' => 'symbol-purple',
            ],
            [
                'label' => 'Devices used',
                'value' => number_format((clone $this->scoped($filters))->distinct()->count('user_agent')),
                'hint' => 'Distinct browsers and apps',
                'icon' => 'bi bi-laptop',
                'tone' => 'symbol-cyan',
            ],
            [
                'label' => 'Sign-ins today',
                'value' => number_format((clone $this->scoped($filters))
                    ->where('event', 'authentication.login')
                    ->whereDate('created_at', Carbon::today())
                    ->count()),
                'hint' => Carbon::today()->format('M j, Y'),
                'icon' => 'bi bi-box-arrow-in-right',
                'tone' => 'symbol-green',
            ],
            [
                'label' => 'Rejected sign-ins',
                'value' => number_format((clone $this->scoped($filters))
                    ->whereIn('event', self::REJECTED_SIGN_INS)
                    ->count()),
                'hint' => 'Failed, blocked, or locked out',
                'icon' => 'bi bi-shield-exclamation',
                'tone' => 'symbol-amber',
            ],
        ];
    }

    private function rangeHint(array $filters): string
    {
        return match (true) {
            $filters['from'] !== '' && $filters['to'] !== '' => Carbon::parse($filters['from'])->format('M j').' – '.Carbon::parse($filters['to'])->format('M j, Y'),
            $filters['from'] !== '' => 'Since '.Carbon::parse($filters['from'])->format('M j, Y'),
            $filters['to'] !== '' => 'Until '.Carbon::parse($filters['to'])->format('M j, Y'),
            default => 'All recorded time',
        };
    }

    /**
     * Distinct user agents are few, so fold them into device categories in PHP
     * rather than trying to classify a user_agent string in SQL.
     *
     * @param  array<string, string>  $filters
     * @return Collection<int, object>
     */
    private function deviceBreakdown(array $filters): Collection
    {
        return (clone $this->scoped($filters))
            ->selectRaw('user_agent, COUNT(*) as total')
            ->groupBy('user_agent')
            ->get()
            ->groupBy(fn ($row) => DeviceFingerprint::describe($row->user_agent)['category'])
            ->map(fn (Collection $group, string $category) => (object) [
                'category' => $category,
                'total' => (int) $group->sum('total'),
                'variants' => $group->count(),
                'icon' => DeviceFingerprint::describe($group->first()->user_agent)['icon'],
                'examples' => $group->sortByDesc('total')->take(3)
                    ->map(fn ($row) => DeviceFingerprint::summarize($row->user_agent))
                    ->unique()->values()->all(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, object>
     */
    private function portalBreakdown(array $filters): Collection
    {
        return (clone $this->scoped($filters))
            ->selectRaw('actor_guard, COUNT(*) as total')
            ->groupBy('actor_guard')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) [
                'portal' => PortalAccounts::label((string) $row->actor_guard),
                'icon' => PortalAccounts::icon((string) $row->actor_guard),
                'total' => (int) $row->total,
            ]);
    }

    /** @return array<string, string> */
    private function portalOptions(): array
    {
        return collect(PortalAccounts::PORTALS)
            ->map(fn (array $portal) => $portal['label'])
            ->all();
    }
}
