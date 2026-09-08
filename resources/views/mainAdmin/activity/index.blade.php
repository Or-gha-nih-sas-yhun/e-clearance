@extends('mainAdmin.layouts.admin')
@section('title', 'User Activity — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="User Activity"
    description="Track who signed in, what they changed, from which device, and when."
    icon="bi bi-activity"
    eyebrow="Monitoring"
/>

@unless($available)
    <section class="admin-feed-card">
        <div class="admin-empty-state">
            <div>
                <i class="bi bi-database-exclamation" aria-hidden="true"></i>
                <h3>Activity tracking is not set up on this database</h3>
                <p>The <code>security_audit_logs</code> table is missing. Run <code>php artisan migrate</code> on the server, then reload this page.</p>
            </div>
        </div>
    </section>
@else

<div class="admin-metrics">
    @foreach($metrics as $metric)
        <div class="metric-card admin-metric">
            <div><small>{{ $metric['label'] }}</small><strong>{{ $metric['value'] }}</strong><span>{{ $metric['hint'] }}</span></div>
            <i class="metric-symbol {{ $metric['tone'] }} {{ $metric['icon'] }}"></i>
        </div>
    @endforeach
</div>

<div class="activity-breakdown">
    <section class="chart-card activity-panel" aria-labelledby="activityDeviceHeading">
        <div class="panel-heading">
            <div><h3 id="activityDeviceHeading">Devices used</h3><p>Browsers, phones, and the Android app accounts signed in from.</p></div>
        </div>
        <div class="activity-panel-list">
            @forelse($devices as $device)
                <div class="activity-panel-row">
                    <i class="{{ $device->icon }}" aria-hidden="true"></i>
                    <span>
                        <strong>{{ ucfirst($device->category) }}</strong>
                        <small>{{ implode(', ', $device->examples) }}</small>
                    </span>
                    <b>{{ number_format($device->total) }}</b>
                </div>
            @empty
                <p class="activity-panel-empty">No device information recorded yet.</p>
            @endforelse
        </div>
    </section>

    <section class="chart-card activity-panel" aria-labelledby="activityPortalHeading">
        <div class="panel-heading">
            <div><h3 id="activityPortalHeading">Activity by portal</h3><p>Which portal the recorded actions came from.</p></div>
        </div>
        <div class="activity-panel-list">
            @forelse($portals as $portal)
                <div class="activity-panel-row">
                    <i class="{{ $portal->icon }}" aria-hidden="true"></i>
                    <span><strong>{{ $portal->portal }}</strong></span>
                    <b>{{ number_format($portal->total) }}</b>
                </div>
            @empty
                <p class="activity-panel-empty">No activity recorded yet.</p>
            @endforelse
        </div>
    </section>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('activity.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <select name="portal">
                    <option value="">All portals</option>
                    @foreach($portalOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('portal') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="category">
                    <option value="">All activity</option>
                    @foreach($categoryOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="device">
                    <option value="">All devices</option>
                    @foreach($deviceOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('device') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" value="{{ request('from') }}" aria-label="From date">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" value="{{ request('to') }}" aria-label="To date">
            </div>
            <div class="col-md-2">
                <input type="text" name="search" placeholder="🔎 Account ID, IP, or event" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-filter w-100">Go</button>
            </div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-clock-history"></i> Activity Trail</h3>
        <span style="font-size:12px;color:var(--muted);">{{ number_format($activities->total()) }} recorded {{ \Illuminate\Support\Str::plural('activity', $activities->total()) }}</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th><th>Account</th><th>Portal</th>
                    <th>Activity</th><th>Device</th><th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            @forelse($activities as $activity)
                <tr>
                    <td style="white-space:nowrap;">
                        <strong>{{ $activity->at?->format('M j, Y') ?? '—' }}</strong>
                        <div style="color:var(--muted);font-size:12px;">{{ $activity->at?->format('g:i:s A') }}</div>
                    </td>
                    <td>
                        <strong>{{ $activity->actor_name ?: 'Unidentified account' }}</strong>
                        <div style="font-family:monospace;color:var(--muted);font-size:12px;">{{ $activity->actor_id ?: '—' }}</div>
                    </td>
                    <td style="white-space:nowrap;"><i class="{{ $activity->portal_icon }} me-1" aria-hidden="true"></i>{{ $activity->portal }}</td>
                    <td>
                        <span class="badge-type {{ $activity->rejected ? 'badge-irregular' : 'badge-regular' }}">{{ $activity->label }}</span>
                        @if($activity->details)
                            <div style="color:var(--muted);font-size:12px;margin-top:6px;">{{ $activity->details }}</div>
                        @elseif($activity->subject)
                            <div style="color:var(--muted);font-size:12px;margin-top:6px;">{{ $activity->subject }}</div>
                        @endif
                    </td>
                    <td>
                        <i class="{{ $activity->device['icon'] }} me-1" aria-hidden="true"></i>{{ $activity->device['label'] }}
                        <div style="color:var(--muted);font-size:12px;text-transform:capitalize;">{{ $activity->device['category'] }}</div>
                    </td>
                    <td style="font-family:monospace;font-size:12px;">{{ $activity->ip_address ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No activity matches the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$activities" label="events" />
</div>
@endunless
@endsection

@push('styles')
<style>
    /* The metric cards and .panel-heading come from main_admin_portal.css so they
       match the dashboard exactly. Only this page's two-panel row is local, and its
       rows deliberately reuse the dashboard's .admin-actions proportions. */
    .activity-breakdown { display:grid; grid-template-columns:minmax(0,1fr) minmax(290px,.7fr); gap:1rem; }
    .activity-panel { padding:1.25rem; }
    .activity-panel-list { display:grid; gap:.6rem; }
    .activity-panel-row { display:flex; align-items:center; gap:.7rem; padding:.72rem; color:#173763; border:1px solid rgba(191,214,232,.58); border-radius:.8rem; background:rgba(255,255,255,.4); }
    .activity-panel-row > i { display:grid; width:37px; height:37px; flex:0 0 auto; place-items:center; color:#fff; border-radius:11px; background:linear-gradient(145deg,#36aaff,#075bea); }
    .activity-panel-row > span { min-width:0; flex:1; }
    .activity-panel-row strong { display:block; font-size:.82rem; }
    .activity-panel-row small { display:block; overflow:hidden; color:#71809a; font-size:.68rem; text-overflow:ellipsis; white-space:nowrap; }
    .activity-panel-row > b { color:#075bea; font-size:.82rem; }
    .activity-panel-empty { margin:0; padding:.72rem; color:#71809a; font-size:.78rem; }
    @media(max-width:1050px){.activity-breakdown{grid-template-columns:1fr}}
</style>
@endpush
