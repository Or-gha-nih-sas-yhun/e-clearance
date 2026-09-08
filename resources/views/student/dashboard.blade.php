@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', 'Student Dashboard')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'My Clearance')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')
@section('nav')
    <a class="nav-link active" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    @if(\App\Support\StudentSubjects::isIrregular($student))
    <a class="nav-link" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
    @endif
    <a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection
@section('logout-form')
    <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')
<style>
    .metric-card { border: 1px solid #e8eef7; border-radius: 14px; height: 100%; padding: 1rem; background: #fff; }
    .metric-icon { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; font-size: 1.1rem; }
    .stacked-bar { height: 13px; overflow: hidden; border-radius: 99px; background: #edf1f7; display: flex; }
    .stacked-bar span { min-width: 0; }
    .legend-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: .35rem; }
    .coverage-row + .coverage-row { border-top: 1px solid #eef1f5; margin-top: .8rem; padding-top: .8rem; }
    .coverage-track { height: 8px; border-radius: 20px; background: #edf1f7; overflow: hidden; }
    .coverage-track span { display: block; height: 100%; border-radius: inherit; }
    .queue-item + .queue-item, .activity-item + .activity-item { border-top: 1px solid #eef1f5; }
    .queue-item, .activity-item { padding: .75rem 0; }
    .item-type { font-size: .67rem; letter-spacing: .05em; text-transform: uppercase; font-weight: 700; color: #718096; }
    .empty-state { color: #718096; text-align: center; padding: 2rem 1rem; }
</style>
@endpush

@section('content')
@php
    $subjectRate = $subjectsTotal ? round($subjectsApproved / $subjectsTotal * 100) : 0;
    $officeRate = $officesTotal ? round($officesApproved / $officesTotal * 100) : 0;
    $pendingTotal = $statusBreakdown['pending'] + $statusBreakdown['action'];
    $officeIcons = [
        'section treasurer' => 'bi-cash-stack', 'department treasurer' => 'bi-bank',
        'property custodian' => 'bi-box-seam', 'scc adviser' => 'bi-people',
        'sas director' => 'bi-person-badge', 'guidance office' => 'bi-heart',
        'library' => 'bi-book', 'dean' => 'bi-mortarboard', 'registrar' => 'bi-file-earmark-check',
    ];
    $itemIcon = static function ($item) use ($officeIcons) {
        if (strtolower($item->type) !== 'office') return 'bi-journal-bookmark';
        return $officeIcons[strtolower(trim($item->label))] ?? 'bi-building';
    };
@endphp
<div class="page-content">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="text-secondary small">Approved</div><div class="fs-4 fw-bold">{{ $statusBreakdown['approved'] }}</div></div><div class="metric-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div></div><div class="small text-secondary mt-2">of {{ $totalClearances }} clearances</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="text-secondary small">Awaiting review</div><div class="fs-4 fw-bold">{{ $statusBreakdown['pending'] }}</div></div><div class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div></div><div class="small text-secondary mt-2">still with reviewer</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="text-secondary small">Needs action</div><div class="fs-4 fw-bold">{{ $statusBreakdown['action'] }}</div></div><div class="metric-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-circle"></i></div></div><div class="small text-secondary mt-2">requires your attention</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="text-secondary small">Unread updates</div><div class="fs-4 fw-bold">{{ $unreadNotifications }}</div></div><div class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-bell"></i></div></div><div class="small text-secondary mt-2">new notifications</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card card-stat h-100"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4"><div><h5 class="mb-1">Clearance distribution</h5><div class="text-secondary small">A live breakdown of every required approval.</div></div><span class="fw-semibold">{{ $overallProgress }}% complete</span></div>
                <div class="stacked-bar mb-3">
                    @if($totalClearances)
                        <span class="bg-success" style="width: {{ $statusBreakdown['approved'] / $totalClearances * 100 }}%"></span><span class="bg-warning" style="width: {{ $statusBreakdown['pending'] / $totalClearances * 100 }}%"></span><span class="bg-danger" style="width: {{ $statusBreakdown['action'] / $totalClearances * 100 }}%"></span>
                    @endif
                </div>
                <div class="row g-2 mb-4 small"><div class="col-4"><span class="legend-dot bg-success"></span>Approved <strong>{{ $statusBreakdown['approved'] }}</strong></div><div class="col-4"><span class="legend-dot bg-warning"></span>Pending <strong>{{ $statusBreakdown['pending'] }}</strong></div><div class="col-4"><span class="legend-dot bg-danger"></span>Action <strong>{{ $statusBreakdown['action'] }}</strong></div></div>
                <div class="coverage-row"><div class="d-flex justify-content-between small mb-2"><span><i class="bi bi-book me-1 text-primary"></i>Subject clearances</span><strong>{{ $subjectsApproved }}/{{ $subjectsTotal }} · {{ $subjectRate }}%</strong></div><div class="coverage-track"><span class="bg-primary" style="width: {{ $subjectRate }}%"></span></div></div>
                <div class="coverage-row"><div class="d-flex justify-content-between small mb-2"><span><i class="bi bi-buildings me-1 text-info"></i>Office clearances</span><strong>{{ $officesApproved }}/{{ $officesTotal }} · {{ $officeRate }}%</strong></div><div class="coverage-track"><span class="bg-info" style="width: {{ $officeRate }}%"></span></div></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card card-stat h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start mb-2"><div><h5 class="mb-1">Action queue</h5><div class="text-secondary small">{{ $pendingTotal ? 'Items waiting for progress or your response.' : 'Everything is cleared.' }}</div></div><a href="{{ route('student.clearance-updates') }}" class="small text-decoration-none">View all</a></div>
                @forelse($actionItems->take(4) as $item)
                    @php $isAction = in_array(strtolower($item->status), ['rejected', 'disapproved', 'returned', 'needs revision', 'for revision'], true); @endphp
                    <div class="queue-item"><div class="d-flex justify-content-between gap-2"><div><div class="item-type"><i class="bi {{ $itemIcon($item) }} text-primary me-1"></i>{{ $item->type }} · {{ $item->owner }}</div><div class="fw-semibold small">{{ $item->label }}</div><div class="text-secondary small text-truncate" style="max-width: 280px;">{{ $item->remarks ?: 'No remarks yet.' }}</div></div><span class="badge align-self-start text-bg-{{ $isAction ? 'danger' : 'warning' }}">{{ $item->status }}</span></div></div>
                @empty
                    <div class="empty-state"><i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>No outstanding clearance items.</div>
                @endforelse
            </div></div>
        </div>
        <div class="col-12"><div class="card card-stat"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-2"><div><h5 class="mb-1">Recent clearance activity</h5><div class="text-secondary small">Latest status updates from your reviewers.</div></div><span class="badge text-bg-light">{{ $recentActivity->count() }} updates</span></div><div class="row g-0">
            @forelse($recentActivity as $item)
                <div class="col-md-4 activity-item pe-md-3"><div class="item-type"><i class="bi {{ $itemIcon($item) }} text-primary me-1"></i>{{ $item->type }} · {{ $item->status }}</div><div class="fw-semibold small">{{ $item->label }}</div><div class="text-secondary small">{{ \Illuminate\Support\Carbon::parse($item->updated_at)->diffForHumans() }}{{ $item->remarks ? ' · ' . $item->remarks : '' }}</div></div>
            @empty
                <div class="empty-state">Activity will appear here after a reviewer updates a clearance item.</div>
            @endforelse
        </div></div></div></div>
    </div>
</div>
@endsection
