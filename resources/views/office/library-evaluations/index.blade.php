@extends('office.library-evaluations.layout')
@section('evaluation-content')
@if(! $available)
    <div class="alert alert-info">{{ $officeLabel }} evaluations are not available yet. Please contact the system administrator to enable this feature.</div>
@else
    <section class="evaluation-panel">
        <div class="evaluation-panel-head no-border">
            <div class="evaluation-panel-title">
                <span class="evaluation-panel-icon"><i class="bi bi-ui-checks"></i></span>
                <div><span class="evaluation-eyebrow">{{ $officeLabel }} services</span><h2>{{ $officeLabel }} evaluation</h2><p>Manage your evaluation form and track student responses before {{ $scope }} clearance.</p></div>
            </div>
            @if(! $selected)<a class="evaluation-button primary" href="{{ route($officeRouteBase.'.create') }}"><i class="bi bi-plus-lg"></i>Create evaluation</a>@endif
        </div>
    </section>

    @if(! $selected)
        <section class="evaluation-panel evaluation-empty"><i class="bi bi-journal-plus"></i><h3>Create your first evaluation</h3><p>The form becomes required for {{ $scope }} clearance when you publish it.</p><a class="evaluation-button primary" href="{{ route($officeRouteBase.'.create') }}">Create evaluation</a></section>
    @else
        @if($summary) @include('office.library-evaluations.summary') @endif
        <section class="evaluation-panel">
            <div class="evaluation-panel-head no-border">
                <div class="evaluation-panel-title"><span class="evaluation-panel-icon"><i class="bi bi-card-checklist"></i></span><div><span class="evaluation-eyebrow">Evaluation form</span><h2>{{ $selected->title }}</h2><p>View the questions and manage this form.</p></div></div>
                <div class="evaluation-form-launch-actions">
                    <span class="evaluation-status {{ $selected->published_at ? 'current' : 'draft' }}">{{ $selected->published_at ? 'Published' : 'Draft' }}</span>
                    <button class="evaluation-button primary" type="button" data-evaluation-form aria-haspopup="dialog" aria-controls="evaluationResponseViewer"><i class="bi bi-eye"></i>View evaluation form</button>
                </div>
            </div>
        </section>
        <template id="evaluation-form-preview">
            @include('office.library-evaluations.form-preview')
        </template>

        @if($students)
            <section class="evaluation-panel">
                <div class="evaluation-panel-head">
                    <div class="evaluation-panel-title"><span class="evaluation-panel-icon"><i class="bi bi-people"></i></span><div><span class="evaluation-eyebrow">Response tracking</span><h2>Student completion</h2><p>{{ $completedCount }} completed &middot; {{ $totalStudents - $completedCount }} not completed</p></div></div>
                    <span class="evaluation-status complete"><i class="bi bi-check-circle"></i>{{ $completedCount }} of {{ $totalStudents }}</span>
                </div>
                <form method="GET" class="evaluation-toolbar">
                    <input type="hidden" name="evaluation" value="{{ $selected->id }}">
                    <div class="evaluation-field search"><label for="evaluation-search">Student name or ID</label><input id="evaluation-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search student name or ID..."></div>
                    <div class="evaluation-field"><label for="completion-filter">Completion</label><select id="completion-filter" name="completion"><option value="">All students</option><option value="completed" @selected(request('completion') === 'completed')>Completed</option><option value="pending" @selected(request('completion') === 'pending')>Not completed</option></select></div>
                    <button class="evaluation-button secondary" type="submit"><i class="bi bi-funnel"></i>Apply filters</button>
                </form>
                <div class="evaluation-table-wrap"><table class="evaluation-table"><thead><tr><th>Student</th><th>Program / Year / Section</th><th>Evaluation</th><th>Completed at</th><th>Response</th></tr></thead><tbody>
                    @forelse($students as $student)
                        <tr><td><strong>{{ $student->firstname }} {{ $student->lastname }}</strong><small>{{ $student->student_id }}</small></td><td>{{ $student->program }} / {{ $student->year_level }} / {{ $student->section }}</td><td><span class="evaluation-status {{ $student->response_id ? 'complete' : 'pending' }}">{{ $student->response_id ? 'Completed' : 'Not completed' }}</span></td><td>{{ $student->completed_at ? \Carbon\Carbon::parse($student->completed_at)->format('M d, Y h:i A') : '—' }}</td><td>@if($student->response_id)<a class="evaluation-button secondary small" href="{{ route($officeRouteBase.'.response', $student->response_id) }}"><i class="bi bi-eye"></i>View response</a>@else<span class="text-secondary">Awaiting response</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-secondary">No students match these filters.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="evaluation-pagination"><span>Showing {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} of {{ $students->total() }} students</span>{{ $students->links() }}</div>
            </section>
        @endif
        @include('office.library-evaluations.response-viewer')
        @if($summary) @include('office.library-evaluations.charts') @endif
    @endif
@endif
@endsection
