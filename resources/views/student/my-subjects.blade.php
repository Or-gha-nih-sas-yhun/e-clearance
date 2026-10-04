@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', 'My Subjects')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'My Subjects')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark"></i> Bridging Subjects</a>
    <a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection
@section('logout-form')<form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>@endsection

@push('styles')
<style>
    .subject-panel { border:1px solid rgba(148,178,205,.45); border-radius:1.1rem; background:rgba(255,255,255,.62); margin-bottom:1.1rem; overflow:hidden; }
    .subject-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:1.1rem 1.35rem; border-bottom:1px solid rgba(148,178,205,.35); }
    .subject-panel-head h2 { margin:0; font-size:1.05rem; font-weight:800; color:#102a56; }
    .subject-panel-head p { margin:.25rem 0 0; color:#64748b; font-size:.82rem; max-width:60ch; }
    .subject-panel-count { padding:.3rem .7rem; border-radius:99px; background:rgba(13,110,253,.1); color:#0d6efd; font-size:.72rem; font-weight:800; white-space:nowrap; }
    .subject-panel-body { padding:1.1rem 1.35rem; }
    .subject-card { border:1px solid rgba(148,178,205,.45); border-radius:1rem; background:rgba(255,255,255,.72); padding:1.1rem 1.25rem; margin-bottom:.85rem; }
    .subject-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .subject-card h3 { margin:0; font-size:1rem; font-weight:800; color:#102a56; }
    .subject-card p { margin:.2rem 0 0; color:#64748b; font-size:.82rem; }
    .subject-state { display:inline-block; padding:.25rem .65rem; border-radius:99px; font-size:.7rem; font-weight:800; }
    .subject-state.approved { background:rgba(16,185,129,.14); color:#047857; }
    .subject-state.pending { background:rgba(234,179,8,.16); color:#92400e; }
    .subject-state.available { background:rgba(13,110,253,.12); color:#075bea; }
    .subject-state.none { background:rgba(100,116,139,.14); color:#475569; }
    .subject-empty { padding:2.4rem 1.25rem; text-align:center; color:#64748b; }
    .subject-empty i { display:block; margin-bottom:.6rem; font-size:1.8rem; opacity:.45; }
    .subject-picker { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) auto; gap:.75rem; align-items:end; }
    .bridging-actions { display:flex; justify-content:flex-end; padding-top:.25rem; }
    .bridging-note { display:flex; align-items:flex-start; gap:.75rem; padding:.9rem 1rem; margin-bottom:1rem; border:1px solid rgba(13,110,253,.2); border-radius:.85rem; background:rgba(13,110,253,.07); color:#45617f; font-size:.8rem; line-height:1.55; }
    .bridging-note i { color:#0d6efd; font-size:1rem; }
    @media (max-width: 800px) { .subject-picker { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="submission-workspace">
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="subject-panel">
        <div class="subject-panel-head">
            <div>
                <h2><i class="bi bi-signpost-split"></i> Optional Bridging Subjects</h2>
                <p>Bridging subjects assigned to your section appear here. Add them only when you are taking them this term.</p>
            </div>
            <span class="subject-panel-count">{{ $bridgingAssignments->where('enrolled', true)->count() }} of {{ $bridgingAssignments->count() }} added</span>
        </div>

        <div class="subject-panel-body">
            @unless($bridgingAvailable)
                <div class="subject-empty">
                    <i class="bi bi-database-exclamation"></i>
                    <strong>Bridging subject selection is not set up yet.</strong>
                    <p class="mt-2 mb-0">Ask the administrator to run the latest database update.</p>
                </div>
            @else
                @if($bridgingAssignments->isEmpty())
                    <div class="subject-empty">
                        <i class="bi bi-inbox"></i>
                        <strong>No bridging subjects are assigned to your section.</strong>
                        <p class="mt-2 mb-0">Nothing will be added to your clearance progress, submissions, or instructor chat.</p>
                    </div>
                @else
                    <div class="bridging-note">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>After you add these subjects, you can request clearance, upload files, and message their assigned instructors just like your regular subjects.</span>
                    </div>

                    @foreach($bridgingAssignments as $row)
                        @php
                            $status = $row->clearance_status;
                            $state = $status === 'Approved' ? 'approved' : ($status === null ? 'none' : 'pending');
                            $stateLabel = $status ?? 'Not submitted';
                        @endphp
                        <article class="subject-card">
                            <div class="subject-card-head">
                                <div>
                                    <h3>{{ $row->subject_code ?? 'Subject' }} — {{ $row->subject_description }}</h3>
                                    <p>Instructor: <strong>{{ trim(($row->instructor_firstname ?? '').' '.($row->instructor_lastname ?? '')) ?: 'Unknown' }}</strong>@if($row->semester) · {{ $row->semester }}@endif · Bridging</p>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    @if($row->enrolled)
                                        <span class="subject-state {{ $state }}">{{ $stateLabel }}</span>
                                        <form method="POST" action="{{ route('student.subjects.bridging.destroy') }}"
                                              onsubmit="return confirm('Remove this bridging subject? Its clearance request, remarks, and uploaded file will also be deleted.');">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="subject_id" value="{{ $row->subject_id }}">
                                            <input type="hidden" name="instructor_id" value="{{ $row->instructor_id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($status === 'Approved')>
                                                <i class="bi bi-trash3"></i> Remove
                                            </button>
                                        </form>
                                    @else
                                        <span class="subject-state available">Available</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach

                    @if($bridgingAssignments->contains(fn ($row) => ! $row->enrolled))
                        <div class="bridging-actions">
                            <form method="POST" action="{{ route('student.subjects.bridging.store') }}"
                                  onsubmit="return confirm('Add the bridging subjects assigned to your section? They will appear in your clearance progress, submissions, and instructor chat.');">
                                @csrf
                                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Bridging Subjects</button>
                            </form>
                        </div>
                    @endif
                @endif
            @endunless
        </div>
    </div>

    @if($irregular)
    @unless($irregularAvailable)
        <div class="subject-panel">
            <div class="subject-empty">
                <i class="bi bi-database-exclamation"></i>
                <strong>Subject selection is not set up yet.</strong>
                <p class="mt-2 mb-0">The <code>irregular_enrollment</code> table does not exist on this database. Please tell the administrator — until it does, your subjects cannot be recorded here.</p>
            </div>
        </div>
    @else
        <div class="subject-panel">
            <div class="subject-panel-head">
                <div>
                    <h2><i class="bi bi-journal-bookmark"></i> Your Subjects</h2>
                    <p>You are enrolled as an irregular student, so you choose your own subjects and the instructor who will clear each one.</p>
                </div>
                <span class="subject-panel-count">{{ $enrolled->count() }} subject{{ $enrolled->count() === 1 ? '' : 's' }}</span>
            </div>

            <div class="subject-panel-body">
                @forelse($enrolled as $row)
                    @php
                        $status = $row->clearance_status;
                        $state = $status === 'Approved' ? 'approved' : ($status === null ? 'none' : 'pending');
                        $stateLabel = $status ?? 'Not submitted';
                    @endphp
                    <article class="subject-card">
                        <div class="subject-card-head">
                            <div>
                                <h3>{{ $row->subject_code ?? 'Subject' }} — {{ $row->subject_description }}</h3>
                                <p>
                                    Instructor: <strong>{{ trim(($row->instructor_firstname ?? '').' '.($row->instructor_lastname ?? '')) ?: 'Unknown' }}</strong>
                                    @if($row->year_level) · Year {{ $row->year_level }} @endif
                                    @if($row->semester) · {{ $row->semester }} @endif
                                </p>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="subject-state {{ $state }}">{{ $stateLabel }}</span>
                                <form method="POST" action="{{ route('student.subjects.destroy') }}"
                                      onsubmit="return confirm('Remove this subject from your list? Its clearance record, remarks and any uploaded file are deleted with it.');">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="subject_id" value="{{ $row->subject_id }}">
                                    <input type="hidden" name="instructor_id" value="{{ $row->instructor_id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($status === 'Approved')>
                                        <i class="bi bi-trash3"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="subject-empty">
                        <i class="bi bi-journal-plus"></i>
                        <strong>You have not added any subjects yet.</strong>
                        <p class="mt-2 mb-0">Add each subject you are taking below. Until you do, there is nothing for your instructors to clear.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="subject-panel">
            <div class="subject-panel-head">
                <div>
                    <h2><i class="bi bi-plus-circle"></i> Add a Subject</h2>
                    <p>Pick the subject, then the instructor teaching it who will handle your clearance.</p>
                </div>
            </div>

            <div class="subject-panel-body">
                @if($offered->isEmpty())
                    <div class="subject-empty">
                        <i class="bi bi-inbox"></i>
                        <strong>No subjects are available to choose from.</strong>
                        <p class="mt-2 mb-0">Subjects appear here once the administrator has assigned instructors to them.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('student.subjects.store') }}">
                        @csrf
                        <div class="subject-picker">
                            <div>
                                <label class="form-label" for="subject_id">Subject</label>
                                <select class="form-select" name="subject_id" id="subject_id" required onchange="showInstructorsFor(this.value)">
                                    <option value="">Choose a subject</option>
                                    @foreach($offered as $subject)
                                    <option value="{{ $subject['subject_id'] }}">{{ $subject['subject_code'] }} — {{ $subject['subject_description'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="instructor_id">Instructor</label>
                                <select class="form-select" name="instructor_id" id="instructor_id" required>
                                    <option value="">Choose a subject first</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Subject</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endunless
    @endif
</div>
@endsection

@push('scripts')
<script>
// Only instructors the administrator assigned to a subject are offered for it,
// which is the same rule the server enforces on submit.
const subjectInstructors = @json($offered->mapWithKeys(fn ($subject) => [$subject['subject_id'] => $subject['instructors']]));

function showInstructorsFor(subjectId) {
    const select = document.getElementById('instructor_id');
    const choices = subjectInstructors[subjectId] || [];

    select.innerHTML = choices.length
        ? '<option value="">Choose an instructor</option>'
        : '<option value="">No instructor is assigned to this subject</option>';

    choices.forEach(instructor => {
        const option = document.createElement('option');
        option.value = instructor.instructor_id;
        option.textContent = instructor.name;
        select.appendChild(option);
    });
}
</script>
@endpush
