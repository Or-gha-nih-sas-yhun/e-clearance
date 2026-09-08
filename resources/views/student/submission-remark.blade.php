@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', 'Submission Center')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'Submission Center')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    @if(\App\Support\StudentSubjects::isIrregular($student))
    <a class="nav-link" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
    @endif
    <a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a>
    <a class="nav-link active" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection
@section('logout-form')<form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>@endsection

@section('content')
@php
    $assignedCount = $submissions->count();
    $submittedCount = $submissions->whereNotNull('submission_id')->count();
    $approvedCount = $submissions->where('clearance_status', 'Approved')->count();
    $pendingCount = $assignedCount - $approvedCount;
@endphp
<div class="submission-workspace">
    @if(isset($errors) && $errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="submission-summary">
        <article class="clearance-stat total"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-files"></i></span><div class="clearance-stat-copy"><small>Submitted</small><strong>{{ $submittedCount }}</strong><span>Files sent to instructors</span></div></div></article>
        <article class="clearance-stat approved"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-check-circle"></i></span><div class="clearance-stat-copy"><small>Approved</small><strong>{{ $approvedCount }}</strong><span>Completed subject reviews</span></div></div></article>
        <article class="clearance-stat pending"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-hourglass-split"></i></span><div class="clearance-stat-copy"><small>Pending</small><strong>{{ $pendingCount }}</strong><span>Still awaiting approval</span></div></div></article>
    </div>

    <div class="submission-review-grid">
    @forelse($submissions as $submission)
        <article class="submission-review-card">
            <header class="submission-card-head"><div><h3>{{ $submission->subject_code }}</h3><p>{{ $submission->subject_description }}</p></div><span class="clearance-status {{ $submission->clearance_status === 'Approved' ? 'approved' : 'pending' }}">{{ $submission->clearance_status === 'Approved' ? 'Approved' : 'Pending' }}</span></header>
            <div><span class="submission-meta-label">Instructor</span><div class="small">{{ trim(($submission->instructor_firstname ?? '') . ' ' . ($submission->instructor_lastname ?? '')) ?: 'Instructor' }}</div></div>
            <div class="submission-feedback-box"><label>Instructor Feedback</label><p>{{ $submission->clearance_remarks ?: 'No feedback yet. Your instructor will respond here after review.' }}</p></div>
            @if($submission->submission_id)
                <div class="submission-file-box"><div class="submission-file-copy"><strong><i class="bi bi-file-earmark-check text-primary me-1"></i>{{ $submission->file_name }}</strong><small>Sent {{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->diffForHumans() }}{{ $submission->description ? ' · '.$submission->description : '' }}</small></div><a class="clearance-action secondary" href="{{ route('student.submission-remark.download', $submission->submission_id) }}" data-file-preview data-file-name="{{ $submission->file_name }}"><i class="bi bi-eye"></i> View File</a></div>
            @endif
            <form class="submission-review-form" method="POST" action="{{ route('student.submission-remark.upload') }}" enctype="multipart/form-data">
                @csrf<input type="hidden" name="subject_id" value="{{ $submission->subject_id }}"><input type="hidden" name="instructor_id" value="{{ $submission->instructor_id }}">
                <label class="submission-meta-label">{{ $submission->submission_id ? 'Replace Submission' : 'Upload Submission' }}</label>
                <input type="file" name="submission_file" accept=".pdf,.jpg,.jpeg,.png" required><input type="text" name="description" maxlength="1000" placeholder="Optional note for your instructor">
                <div class="submission-actions"><button class="clearance-filter-btn apply" type="submit"><i class="bi bi-send"></i>{{ $submission->submission_id ? 'Send Replacement' : 'Send to Instructor' }}</button></div>
            </form>
        </article>
    @empty
        <div class="submission-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No subjects are assigned to your program, year level, and section yet.</div>
    @endforelse
    </div>
</div>
@include('partials.submission-file-viewer')
@endsection
