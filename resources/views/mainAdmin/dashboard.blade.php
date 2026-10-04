@extends('mainAdmin.layouts.admin')
@section('title', 'Dashboard — ClearanceMS')

@section('content')
@php
    $totalRequests = (int) $pending + (int) $approved;
    $completedRequests = (int) $approved;
    $completionRate = $totalRequests ? (int) round(($completedRequests / $totalRequests) * 100) : 0;
    $statusParts = [
        ['label' => 'Pending', 'value' => (int) $pending, 'class' => 'status-pending'],
        ['label' => 'Approved', 'value' => (int) $approved, 'class' => 'status-approved'],
    ];
@endphp
<div class="admin-dashboard-v2">
    <x-main-admin.page-header
        title="Administrative Dashboard"
        description="Monitor users, clearance activity, and institutional performance."
        icon="bi bi-grid-1x2-fill"
        eyebrow="System overview"
        class="admin-heading"
    >
        <x-slot:actions>
            <span class="admin-pill admin-role-pill"><i class="bi bi-shield-check"></i>Main Administrator</span>
        </x-slot:actions>
    </x-main-admin.page-header>

    <div class="admin-metrics">
        <div class="metric-card admin-metric" data-chart-card data-chart-template="studentsAccountChart" data-chart-title="Students by program" data-chart-description="Registered student accounts grouped by program." data-chart-icon="bi-people"><div><small>Students</small><strong>{{ number_format($students) }}</strong><span>Registered accounts</span></div><i class="metric-symbol symbol-blue bi bi-people"></i></div>
        <div class="metric-card admin-metric" data-chart-card data-chart-template="instructorsAccountChart" data-chart-title="Instructors by department" data-chart-description="Instructor accounts grouped by department." data-chart-icon="bi-person-video3"><div><small>Instructors</small><strong>{{ number_format($instructors) }}</strong><span>Teaching personnel</span></div><i class="metric-symbol symbol-purple bi bi-person-video3"></i></div>
        <div class="metric-card admin-metric" data-chart-card data-chart-template="personnelAccountChart" data-chart-title="Admin personnel by office" data-chart-description="Administrative personnel accounts grouped by office." data-chart-icon="bi-person-badge"><div><small>Admin Personnel</small><strong>{{ number_format($admins) }}</strong><span>Office personnel</span></div><i class="metric-symbol symbol-cyan bi bi-person-badge"></i></div>
        <div class="metric-card admin-metric" data-chart-card data-chart-template="registrarsAccountChart" data-chart-title="Registrar accounts by role" data-chart-description="Registered Registrar accounts grouped by role." data-chart-icon="bi-building-check"><div><small>Registrars</small><strong>{{ number_format($registrars) }}</strong><span>Registrar accounts</span></div><i class="metric-symbol symbol-green bi bi-building-check"></i></div>
        <div class="metric-card admin-metric" data-chart-card data-chart-template="treasurersAccountChart" data-chart-title="Treasurers by type" data-chart-description="Treasurer accounts grouped by assigned type." data-chart-icon="bi-wallet2"><div><small>Treasurers</small><strong>{{ number_format($treasurers) }}</strong><span>Treasury accounts</span></div><i class="metric-symbol symbol-amber bi bi-wallet2"></i></div>
    </div>

    <template id="studentsAccountChart"><x-main-admin.account-breakdown-chart :items="$studentAccountBreakdown" :total="$students" label="student accounts" tone="blue" /></template>
    <template id="instructorsAccountChart"><x-main-admin.account-breakdown-chart :items="$instructorAccountBreakdown" :total="$instructors" label="instructor accounts" tone="purple" /></template>
    <template id="personnelAccountChart"><x-main-admin.account-breakdown-chart :items="$adminAccountBreakdown" :total="$admins" label="personnel accounts" tone="cyan" /></template>
    <template id="registrarsAccountChart"><x-main-admin.account-breakdown-chart :items="$registrarAccountBreakdown" :total="$registrars" label="registrar accounts" tone="green" /></template>
    <template id="treasurersAccountChart"><x-main-admin.account-breakdown-chart :items="$treasurerAccountBreakdown" :total="$treasurers" label="treasurer accounts" tone="amber" /></template>

    <div class="admin-overview-grid">
        <section class="chart-card overview-panel">
            <div class="panel-heading"><div><h3>System-wide clearance overview</h3><p>Current status across instructor, office, treasury, and registrar clearance steps.</p></div><strong class="completion-rate">{{ $completionRate }}% complete</strong></div>
            <div class="status-track">
                @foreach($statusParts as $part)
                    <span class="{{ $part['class'] }}" style="width:{{ $totalRequests ? ($part['value'] / $totalRequests) * 100 : 0 }}%"></span>
                @endforeach
            </div>
            <div class="status-grid">
                @foreach($statusParts as $part)
                    <div class="status-item"><span class="status-dot {{ $part['class'] }}"></span><div><small>{{ $part['label'] }}</small><strong>{{ number_format($part['value']) }}</strong></div></div>
                @endforeach
            </div>
        </section>

        <section class="chart-card quick-panel">
            <div class="panel-heading"><div><h3>Quick management</h3><p>Open common administration tasks.</p></div></div>
            <div class="admin-actions">
                <a href="{{ route('students.index') }}"><i class="bi bi-people"></i><span><strong>Manage students</strong><small>Accounts and enrollment records</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="{{ route('instructors.index') }}"><i class="bi bi-person-video3"></i><span><strong>Manage instructors</strong><small>Faculty accounts and assignments</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="{{ route('assignments.index') }}"><i class="bi bi-diagram-3"></i><span><strong>Subject assignments</strong><small>Programs, sections, and instructors</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </section>
    </div>

    <div class="analytics-heading"><div><h3>Analytics</h3><p>Trends and distributions across the clearance system.</p></div><span><i class="bi bi-bell me-1"></i>{{ $notifUnread }} unread updates</span></div>
    <div class="dashboard-grid admin-charts">
        <div class="chart-card"><div class="chart-card-header"><h3><i class="bi bi-graph-up text-primary me-2"></i>All Clearance Activity Trend</h3></div><div id="requestsTrend" class="chart-canvas" role="img" aria-label="System-wide clearance activity trend chart"></div></div>
        <div class="chart-card"><div class="chart-card-header"><h3><i class="bi bi-pie-chart text-primary me-2"></i>All Clearance Status Breakdown</h3></div><div id="requestStatus" class="chart-canvas" role="img" aria-label="System-wide clearance status breakdown chart"></div></div>
        <div class="chart-card"><div class="chart-card-header"><h3><i class="bi bi-mortarboard text-primary me-2"></i>Clearance Status by Program</h3></div><div id="programStatus" class="chart-canvas" role="img" aria-label="Pending and approved clearances by student program"></div></div>
        <div class="chart-card"><div class="chart-card-header"><h3><i class="bi bi-bar-chart text-primary me-2"></i>Monthly Status Breakdown</h3></div><div id="statusStacked" class="chart-canvas" role="img" aria-label="Monthly clearance status chart"></div></div>
    </div>
</div>
<x-chart-viewer />
@endsection

@push('styles')
<style>
    .admin-dashboard-v2 { display:flex; flex-direction:column; gap:1rem; }
    .admin-heading { margin-bottom:0; }
    .admin-heading h2 { margin-bottom:.3rem; color:#102a56; font-weight:800; }
    .admin-heading p { margin:0; color:#5f7392; }
    .admin-pill { display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .8rem; color:#075bea; border:1px solid rgba(7,91,234,.17); border-radius:999px; background:rgba(255,255,255,.62); font-size:.8rem; font-weight:700; }
    {{-- .admin-metrics/.admin-metric/.metric-symbol/.symbol-*/.panel-heading now live in
         main_admin_portal.css so other Main Admin screens can reuse the same cards. --}}
    .admin-overview-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(330px,.8fr); gap:1rem; }
    .overview-panel,.quick-panel { padding:1.25rem; }
    .completion-rate { color:#14865c; font-size:.95rem; }
    .status-track { display:flex; height:14px; overflow:hidden; border-radius:99px; background:rgba(211,225,239,.7); }.status-track span{display:block;height:100%}.status-pending{background:#f5ad18}.status-approved{background:#18a771}.status-cleared{background:#19add2}.status-rejected{background:#df5264}
    .status-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.6rem; margin-top:1rem; }.status-item{display:flex;align-items:center;gap:.55rem;padding:.7rem;border:1px solid rgba(191,214,232,.55);border-radius:.75rem;background:rgba(255,255,255,.34)}.status-dot{width:9px;height:9px;flex:0 0 auto;border-radius:50%}.status-item small{display:block;color:#71809a;font-size:.68rem}.status-item strong{display:block;color:#102a56}
    .admin-actions { display:grid; gap:.6rem; }.admin-actions a{display:flex;align-items:center;gap:.7rem;padding:.72rem;color:#173763;border:1px solid rgba(191,214,232,.58);border-radius:.8rem;background:rgba(255,255,255,.4);text-decoration:none;transition:.2s}.admin-actions a:hover{color:#075bea;background:rgba(255,255,255,.72);transform:translateX(3px)}.admin-actions a>i:first-child{display:grid;width:37px;height:37px;flex:0 0 auto;place-items:center;color:#fff;border-radius:11px;background:linear-gradient(145deg,#36aaff,#075bea)}.admin-actions a>i:last-child{margin-left:auto}.admin-actions strong{display:block;font-size:.82rem}.admin-actions small{display:block;color:#71809a;font-size:.68rem}
    .analytics-heading { display:flex; justify-content:space-between; align-items:end; gap:1rem; margin-top:.25rem; }.analytics-heading h3{margin:0;color:#102a56;font-size:1.15rem;font-weight:800}.analytics-heading p{margin:.15rem 0 0;color:#71809a;font-size:.78rem}.analytics-heading>span{color:#075bea;font-size:.78rem;font-weight:700}
    .admin-charts { gap:1rem; max-height:none; }.admin-charts .chart-card{min-height:390px;max-height:none;border-radius:1rem}.admin-charts .chart-card-header h3{font-size:.95rem}
    .admin-charts .chart-canvas { position:relative; display:flex; width:100%; height:310px; min-height:310px; align-items:center; justify-content:center; overflow:hidden; }
    .admin-native-chart { display:block; width:100%; height:100%; overflow:visible; }
    .admin-native-chart text { font-family:Mulish,system-ui,sans-serif; fill:#61758e; font-size:11px; }
    .admin-native-chart .axis-value { fill:#7890a7; font-size:10px; }
    .admin-chart-grid { stroke:rgba(107,139,166,.2); stroke-width:1; }
    .admin-chart-empty { display:grid; min-height:250px; place-items:center; color:#71869b; text-align:center; }
    .admin-chart-empty i { display:block; margin-bottom:.55rem; color:#84bce5; font-size:2rem; }
    .admin-donut-layout { display:grid; width:100%; grid-template-columns:minmax(170px,1fr) minmax(150px,.8fr); align-items:center; gap:1.5rem; padding:1rem 1.25rem; }
    .admin-donut { position:relative; width:min(210px,100%); aspect-ratio:1; margin:auto; border-radius:50%; box-shadow:inset 0 0 0 1px rgba(255,255,255,.8),0 12px 30px rgba(40,96,139,.12); }
    .admin-donut::after { content:""; position:absolute; inset:24%; border:1px solid rgba(190,216,234,.65); border-radius:50%; background:rgba(248,253,255,.94); box-shadow:inset 0 2px 8px rgba(50,104,143,.08); }
    .admin-donut-center { position:absolute; z-index:1; inset:31%; display:grid; place-content:center; text-align:center; }
    .admin-donut-center strong { color:#102a56; font-size:1.55rem; line-height:1; }.admin-donut-center small{margin-top:.3rem;color:#71869b;font-size:.68rem}
    .admin-chart-legend { display:grid; gap:.75rem; }.admin-chart-legend>div{display:flex;align-items:center;gap:.6rem;padding:.7rem;border:1px solid rgba(191,214,232,.5);border-radius:.75rem;background:rgba(255,255,255,.34)}.admin-chart-legend i{width:10px;height:10px;flex:0 0 auto;border-radius:50%}.admin-chart-legend span{min-width:0;flex:1;color:#637991;font-size:.75rem}.admin-chart-legend strong{color:#102a56}
    @media(max-width:1050px){.admin-overview-grid{grid-template-columns:1fr}.status-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.status-grid{grid-template-columns:1fr 1fr}.analytics-heading{align-items:flex-start;flex-direction:column}.overview-panel,.quick-panel{padding:1rem}.admin-charts .chart-card{min-height:350px}.admin-charts .chart-canvas{height:270px;min-height:270px}.admin-donut-layout{grid-template-columns:1fr;gap:.75rem;padding:.25rem}.admin-donut{width:150px}.admin-chart-legend{grid-template-columns:1fr 1fr;width:100%}}
</style>
@endpush

@push('scripts')
<script>
    window.adminDashboardChartData = {
        trend: {
            labels: @json($monthlyData->pluck('label')->values()),
            values: @json($monthlyData->pluck('count')->values()),
        },
        status: {
            labels: ['Pending', 'Approved'],
            values: @json([(int) $pending, (int) $approved]),
        },
        programStatus: {
            labels: @json($statusByProgram->pluck('program')->values()),
            pending: @json($statusByProgram->pluck('pending')->values()),
            approved: @json($statusByProgram->pluck('approved')->values()),
        },
        stacked: {
            labels: @json($stackData->pluck('label')->values()),
            pending: @json($stackData->pluck('pending')->values()),
            approved: @json($stackData->pluck('approved')->values()),
        },
    };
</script>
<script src="{{ asset('js/admin_dashboard_charts.js') }}"></script>
@endpush
