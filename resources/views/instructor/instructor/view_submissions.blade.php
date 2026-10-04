@extends('instructor.layouts.instructor')
@section('title', 'Submission & Remark')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}?v={{ filemtime(public_path('css/clearance_workspace.css')) }}" rel="stylesheet">@endpush

@section('content')
<div class="submission-workspace">
    <div class="submission-summary">
        <article class="clearance-stat total"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-files"></i></span><div class="clearance-stat-copy"><small>Submitted</small><strong>{{ number_format($stats->total ?? 0) }}</strong><span>Files received for review</span></div></div></article>
        <article class="clearance-stat approved"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-check-circle"></i></span><div class="clearance-stat-copy"><small>Approved</small><strong>{{ number_format($stats->approved ?? 0) }}</strong><span>Completed reviews</span></div></div></article>
        <article class="clearance-stat pending"><div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-hourglass-split"></i></span><div class="clearance-stat-copy"><small>Pending</small><strong>{{ number_format($stats->pending ?? 0) }}</strong><span>Awaiting instructor action</span></div></div></article>
    </div>

    <form method="GET" action="{{ route('instructor.submissions.index') }}" class="clearance-filters">
        <div class="clearance-filter-field">
            <label>Student</label>
            <select name="student_id"><option value="">All Students</option>@foreach($students as $student)<option value="{{ $student->student_id }}" @selected($fStudent === $student->student_id)>{{ $student->full_name }}</option>@endforeach</select>
        </div>
        <div class="clearance-filter-field">
            <label>Subject</label>
            <select name="subject_id"><option value="">All Subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->subject_id }}" @selected($fSubject === (int) $subject->subject_id)>{{ $subject->subject_code }} — {{ $subject->subject_description }}</option>@endforeach</select>
        </div>
        <div class="clearance-filter-field">
            <label>Status</label>
            <select name="status"><option value="">All Statuses</option><option value="Pending" @selected($fStatus === 'Pending')>Pending</option><option value="Approved" @selected($fStatus === 'Approved')>Approved</option></select>
        </div>
        <div class="clearance-filter-actions"><a class="clearance-filter-btn reset" href="{{ route('instructor.submissions.index') }}"><i class="bi bi-arrow-clockwise"></i> Reset</a><button class="clearance-filter-btn apply" type="submit"><i class="bi bi-funnel"></i> Apply</button></div>
    </form>

    <div class="submission-table-stack">
        <section class="clearance-table-card">
            <div class="clearance-table-heading"><h3>Student Submissions</h3><span>{{ $submissions->total() }} records</span></div>
            <div class="clearance-table-wrap">
                <table class="clearance-table">
                    <thead><tr><th>#</th><th>Student</th><th>Subject / Class</th><th>File</th><th>Status</th><th>Review & Remark</th></tr></thead>
                    <tbody>
                    @forelse($submissions as $submission)
                        <tr>
                            <td>{{ ($submissions->firstItem() ?? 1) + $loop->index }}</td>
                            <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($submission->student_name, 0, 1)) }}</span><div><strong>{{ $submission->student_name }}</strong><small>{{ $submission->student_id }}</small></div></div></td>
                            <td><div class="submission-file-copy"><strong>{{ $submission->subject_code }}</strong><small>{{ $submission->subject_description }}</small><small>{{ $submission->program }} · Year {{ $submission->year_level }} / {{ $submission->section }}</small></div></td>
                            <td>
                                @if($submission->id)
                                    <div class="submission-table-file"><div class="submission-file-copy"><strong><i class="bi bi-file-earmark-text text-primary me-1"></i>{{ $submission->file_name }}</strong><small>{{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->diffForHumans() }}</small></div><a class="clearance-action secondary" href="{{ route('instructor.submissions.download', $submission->id) }}" data-file-preview data-file-name="{{ $submission->file_name }}"><i class="bi bi-eye"></i> View File</a></div>
                                @else
                                    <div class="submission-file-copy"><strong><i class="bi bi-hourglass text-warning me-1"></i>No file yet</strong><small>Awaiting submission</small></div>
                                @endif
                            </td>
                            <td><span class="clearance-status {{ $submission->clearance_status === 'Approved' ? 'approved' : 'pending' }}">{{ $submission->clearance_status === 'Approved' ? 'Approved' : 'Pending' }}</span></td>
                            <td>
                                <form class="submission-review-form" onsubmit="event.preventDefault(); updateReview(this, '{{ $submission->student_id }}', {{ $submission->assigned_subject_id }});">
                                    <input type="text" name="remarks" value="{{ $submission->clearance_remarks }}" placeholder="Feedback for student">
                                    <div class="submission-actions">
                                        <button class="clearance-action approve" name="status" value="Approved" type="submit" {{ !$submission->id ? 'disabled' : '' }}><i class="bi bi-check-lg"></i> Approve</button>
                                        <button class="clearance-action pending" name="status" value="Pending" type="submit"><i class="bi bi-clock"></i> Keep Pending</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="clearance-empty">No assigned students match the selected filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="clearance-pagination"><span>Showing {{ $submissions->firstItem() ?? 0 }}–{{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} records</span>{{ $submissions->links() }}</div>
        </section>

        <section class="clearance-table-card">
            <div class="clearance-table-heading"><h3>Remark History</h3><span>{{ $allRemarks->count() }} records</span></div>
            <div class="clearance-table-wrap">
                <table class="clearance-table">
                    <thead><tr><th>#</th><th>Student</th><th>Subject</th><th>Remark</th><th>Status</th><th>Sent</th></tr></thead>
                    <tbody>
                    @forelse($allRemarks as $remark)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($remark->student_name, 0, 1)) }}</span><div><strong>{{ $remark->student_name }}</strong><small>{{ $remark->student_id }}</small></div></div></td>
                            <td><div class="submission-file-copy"><strong>{{ $remark->subject_code }}</strong><small>{{ $remark->subject_description }}</small></div></td>
                            <td>{{ $remark->remark }}</td>
                            <td><span class="clearance-status {{ $remark->clearance_status === 'Approved' ? 'approved' : 'pending' }}">{{ $remark->clearance_status }}</span></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($remark->created_at)->format('M d, Y · h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="clearance-empty">No remark history found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

@include('partials.submission-file-viewer')

@push('scripts')
<script>
async function updateReview(form, student, subject) {
    const button = document.activeElement;
    const status = button?.value || 'Pending';
    const response = await apiFetch(@json(route('instructor.submissions.clearance')), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ student_id: student, subject_id: subject, status, remarks: form.elements.remarks.value.trim() }),
    });
    const data = await response.json();
    if (data.success) {
        await window.showSuccessModal(data.message || `Review saved and clearance marked as ${status}.`);
        location.reload();
    } else {
        await window.showFeedbackModal({ title: 'Review Failed', message: data.message || 'Unable to save the review.', tone: 'danger' });
    }
}
</script>
@endpush
@endsection
