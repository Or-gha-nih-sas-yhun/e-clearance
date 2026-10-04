@extends('layouts.portal')

@section('title', 'Office Submissions & Remark')
@section('portal-name', 'Office Portal')
@section('portal-subtitle', ucwords($officeName))
@section('page-title', ucwords($officeName) . ' Submissions & Remark')
@section('user-label', $office->full_name)
@section('user-role', ucwords($officeName))
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}?v={{ filemtime(public_path('css/clearance_workspace.css')) }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('office.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('office.submissions') }}"><i class="bi bi-folder2-open me-2"></i> Submissions & Remark</a>
    <a class="nav-link" href="{{ route('office.clearance.requests') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance Requests</a>
    <a class="nav-link" href="{{ route('office.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('office.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@section('content')
@php
    $approvedSubmissions = $submissions->whereIn('status', ['Approved', 'Received'])->count();
    $pendingSubmissions = $submissions->count() - $approvedSubmissions;
@endphp
<div class="submission-workspace">
    <div class="submission-summary">
        <article class="clearance-stat total"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-files"></i></span><div class="clearance-stat-copy"><small>Submitted</small><strong>{{ $submissions->count() }}</strong><span>Files received by this office</span></div></div></article>
        <article class="clearance-stat approved"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-check-circle"></i></span><div class="clearance-stat-copy"><small>Approved</small><strong>{{ $approvedSubmissions }}</strong><span>Completed reviews</span></div></div></article>
        <article class="clearance-stat pending"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-hourglass-split"></i></span><div class="clearance-stat-copy"><small>Pending</small><strong>{{ $pendingSubmissions }}</strong><span>Awaiting office action</span></div></div></article>
    </div>

    <div class="submission-table-stack">
        <section class="clearance-table-card">
            <div class="clearance-table-heading"><h3>Office Submissions</h3><span>{{ $submissions->count() }} records</span></div>
            <div class="clearance-table-wrap"><table class="clearance-table"><thead><tr><th>#</th><th>Student</th><th>File</th><th>Status</th><th>Review & Remark</th></tr></thead><tbody>
            @forelse($submissions as $submission)
                @php($submissionApproved = in_array($submission->status, ['Approved', 'Received'], true))
                <tr><td>{{ $loop->iteration }}</td><td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($submission->firstname,0,1)) }}</span><div><strong>{{ $submission->firstname }} {{ $submission->lastname }}</strong><small>{{ $submission->student_id }}</small></div></div></td><td><div class="submission-table-file"><div class="submission-file-copy"><strong><i class="bi bi-file-earmark-text text-primary me-1"></i>{{ $submission->file_name }}</strong><small>Student submission</small></div><a class="clearance-action secondary" href="{{ route('office.submissions.file', $submission->id) }}" data-file-preview data-file-name="{{ $submission->file_name }}"><i class="bi bi-eye"></i> View File</a></div></td><td><span class="clearance-status {{ $submissionApproved ? 'approved' : 'pending' }}">{{ $submissionApproved ? 'Approved' : 'Pending' }}</span></td><td><form class="submission-review-form" method="POST" action="{{ route('office.clearance.status') }}">@csrf<input type="hidden" name="student_id" value="{{ $submission->student_id }}"><input type="hidden" name="submission_id" value="{{ $submission->id }}"><input type="text" name="remarks" value="{{ $submission->remarks ?? '' }}" placeholder="Feedback for student"><div class="submission-actions"><button name="status" value="Approved" class="clearance-action approve"><i class="bi bi-check-lg"></i> Approve</button><button name="status" value="Pending" class="clearance-action pending"><i class="bi bi-clock"></i> Keep Pending</button></div></form></td></tr>
            @empty<tr><td colspan="5" class="clearance-empty">No submissions found.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section class="clearance-table-card">
            <div class="clearance-table-heading"><h3>Remark History</h3><span>{{ $remarks->count() }} records</span></div>
            <div class="clearance-table-wrap"><table class="clearance-table"><thead><tr><th>#</th><th>Student</th><th>Remark</th><th>Program / Section</th><th>Status</th><th>Updated</th></tr></thead><tbody>
            @forelse($remarks as $remark)
                <tr><td>{{ $loop->iteration }}</td><td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($remark->firstname,0,1)) }}</span><div><strong>{{ $remark->firstname }} {{ $remark->lastname }}</strong><small>{{ $remark->student_id }}</small></div></div></td><td>{{ $remark->remarks ?: 'No remark' }}</td><td>{{ $remark->program }} · {{ $remark->year_level }} / {{ $remark->section }}</td><td><span class="clearance-status {{ $remark->status === 'Approved' ? 'approved' : 'pending' }}">{{ $remark->status === 'Approved' ? 'Approved' : 'Pending' }}</span></td><td>{{ \Carbon\Carbon::parse($remark->updated_at)->format('M d, Y') }}</td></tr>
            @empty<tr><td colspan="6" class="clearance-empty">No remark history found.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</div>
@include('partials.submission-file-viewer')
@endsection
