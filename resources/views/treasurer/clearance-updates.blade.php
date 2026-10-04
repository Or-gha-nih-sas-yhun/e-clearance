@extends('layouts.portal')

@section('title', 'Clearance Updates')
@section('portal-name', 'Treasurer Portal')
@section('portal-subtitle', $treasurer->isMainTreasurer() ? 'Main Treasury' : ($treasurer->program . ' ' . $treasurer->year_level . '-' . $treasurer->section))
@section('page-title', 'Clearance Updates')
@section('user-label', $treasurer->full_name)
@section('user-role', 'Treasurer')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}?v={{ filemtime(public_path('css/clearance_workspace.css')) }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('treasurer.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('treasurer.clearance-updates') }}"><i class="bi bi-bar-chart-line me-2"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('treasurer.submission-remark') }}"><i class="bi bi-folder2-open me-2"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('treasurer.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('treasurer.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@section('content')
<div class="clearance-workspace">
    @include('partials.clearance-summary', compact('pendingCount', 'approvedCount', 'totalStudents'))
    @include('partials.clearance-filters', ['action' => route('treasurer.clearance-updates'), 'programs' => $filterPrograms, 'years' => $filterYears, 'sections' => $filterSections])
    <section class="clearance-table-card">
        <div class="clearance-table-heading"><h3>Financial Clearance Requests</h3><span>{{ $officeClearances->total() }} records</span></div>
        @include('partials.clearance-bulk-toolbar', ['endpoint' => route('treasurer.clearance.bulk-status')])
        <div class="clearance-table-wrap"><table class="clearance-table">
            <thead><tr><th class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select-all aria-label="Select all clearance records on this page"></th><th>#</th><th>Student</th><th>Program</th><th>Year Level</th><th>Section</th><th>Remarks</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($officeClearances as $oc)
                <tr>
                    <td class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select data-student="{{ $oc->student_id }}" aria-label="Select {{ $oc->firstname }} {{ $oc->lastname }}"></td>
                    <td>{{ $officeClearances->firstItem() + $loop->index }}</td>
                    <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($oc->firstname,0,1)) }}</span><div><strong>{{ $oc->firstname }} {{ $oc->lastname }}</strong><small>{{ $oc->student_id }}</small></div></div></td>
                    <td>{{ $oc->program }}</td><td>{{ $oc->year_level }}</td><td>{{ $oc->section }}</td><td>{{ $oc->remarks ?: '—' }}</td>
                    <td><span class="clearance-status {{ $oc->status === 'Approved' ? 'approved' : 'pending' }}">{{ $oc->status === 'Approved' ? 'Approved' : 'Pending' }}</span></td>
                    <td><form method="POST" action="{{ route('treasurer.clearance.status') }}">@csrf<input type="hidden" name="student_id" value="{{ $oc->student_id }}"><div class="clearance-actions"><input type="text" name="remarks" class="clearance-remark" value="{{ $oc->remarks ?? '' }}" placeholder="Add remark"><button name="status" value="Approved" class="clearance-action approve" {{ $oc->status === 'Approved' ? 'disabled' : '' }}><i class="bi bi-check-lg"></i> Approve</button><button name="status" value="Pending" class="clearance-action pending"><i class="bi bi-clock"></i> Keep Pending</button></div></form></td>
                </tr>
            @empty
                <tr><td colspan="9" class="clearance-empty">No clearance updates found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="clearance-pagination"><span>Showing {{ $officeClearances->firstItem() ?? 0 }}–{{ $officeClearances->lastItem() ?? 0 }} of {{ $officeClearances->total() }} records</span>{{ $officeClearances->links() }}</div>
    </section>
</div>
@endsection
