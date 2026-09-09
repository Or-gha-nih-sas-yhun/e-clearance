@extends('mainAdmin.layouts.admin')
@section('title', 'Subject Assignments — ClearanceMS')

@push('styles')
<style>
    .dept-tabs { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.6rem; }
    .dept-tab { display:flex; align-items:center; gap:.6rem; padding:.7rem 1rem; border:1px solid rgba(191,214,232,.7); border-radius:.85rem; background:rgba(255,255,255,.62); color:#3f5673; text-decoration:none; transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease, color .16s ease; }
    .dept-tab:hover { color:#075bea; border-color:rgba(7,91,234,.4); background:#fff; transform:translateY(-2px); box-shadow:0 10px 22px rgba(16,42,86,.09); }
    .dept-tab.active { border-color:#075bea; background:linear-gradient(145deg,#36aaff,#075bea); color:#fff; box-shadow:0 12px 26px rgba(7,91,234,.28); }
    .dept-tab.active .dept-tab-meta, .dept-tab.active i { color:rgba(255,255,255,.85); }
    .dept-tab strong { display:block; font-size:.88rem; font-weight:800; line-height:1.25; }
    .dept-tab .dept-tab-meta { display:block; margin-top:.15rem; color:#71809a; font-size:.7rem; }
    .dept-tab > i { font-size:1.1rem; color:#7f93ad; }
    .drill-crumbs { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; color:#71809a; font-size:.82rem; }
    .drill-crumbs a { color:#075bea; font-weight:700; text-decoration:none; }
    .drill-crumbs a:hover { text-decoration:underline; }
    .drill-crumbs i { font-size:.65rem; opacity:.65; }
    .drill-crumbs .current { color:#102a56; font-weight:800; }
    .drill-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(238px,1fr)); gap:.85rem; }
    .drill-card { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; padding:1.05rem; border:1px solid rgba(191,214,232,.65); border-radius:1rem; background:rgba(255,255,255,.62); color:#102a56; text-decoration:none; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease; }
    .drill-card:hover { color:#102a56; border-color:rgba(7,91,234,.42); background:#fff; transform:translateY(-3px); box-shadow:0 14px 30px rgba(16,42,86,.1); }
    .drill-card strong { display:block; color:#102a56; font-size:1rem; font-weight:800; line-height:1.3; }
    .drill-card small { display:block; margin-top:.4rem; color:#71809a; font-size:.74rem; }
    .drill-load { display:inline-block; margin-top:.6rem; padding:.2rem .55rem; border-radius:99px; background:rgba(7,91,234,.1); color:#075bea; font-size:.7rem; font-weight:800; }
    .drill-load.empty { background:rgba(113,128,154,.13); color:#71809a; }
    .drill-person { display:flex; align-items:center; gap:.9rem; flex-wrap:wrap; padding:1.1rem 1.35rem; }
    .drill-avatar { display:grid; width:52px; height:52px; flex:0 0 auto; place-items:center; border-radius:16px; background:linear-gradient(145deg,#36aaff,#075bea); color:#fff; font-size:1.2rem; font-weight:800; }
    .drill-person h3 { margin:0; color:#102a56; font-size:1.1rem; font-weight:800; }
    .drill-person p { margin:.2rem 0 0; color:#71809a; font-size:.8rem; }
    .panel-hint { margin:0 0 1rem; color:#71809a; font-size:.78rem; }
    .drill-empty { padding:2.2rem 1.25rem; text-align:center; color:#71809a; font-size:.85rem; }
    .drill-empty i { display:block; margin-bottom:.6rem; font-size:1.6rem; opacity:.5; }
    .section-checklist { display:flex; flex-direction:column; gap:.55rem; background:#f8fafc; overflow-y:auto; }
    .section-dropdown { border:1px solid #dbe4f0; border-radius:.6rem; background:#fff; overflow:hidden; interpolate-size:allow-keywords; }
    .section-dropdown summary { padding:.65rem .8rem; cursor:pointer; color:#334155; font-weight:600; list-style:none; transition:background-color .2s ease; }
    .section-dropdown summary::-webkit-details-marker { display:none; }
    .section-dropdown summary::after { content:'⌄'; float:right; color:#64748b; }
    .section-dropdown[open] summary { border-bottom:1px solid #dbe4f0; background:#f8fafc; }
    .section-dropdown::details-content { block-size:0; opacity:0; overflow:hidden; transition:block-size .25s ease, opacity .2s ease, content-visibility .25s allow-discrete; }
    .section-dropdown[open]::details-content { block-size:auto; opacity:1; }
    .assignment-section-option { position:relative; display:flex; align-items:center; justify-content:center; padding:.6rem 2rem; border:1px solid #dbe4f0; border-radius:.55rem; background:#fff; cursor:pointer; font-size:.9rem; font-weight:600; text-align:center; transition:.15s ease; }
    .assignment-section-option:hover { border-color:#60a5fa; background:#eff6ff; }
    .assignment-section-option:has(input:checked) { border-color:#2563eb; background:#dbeafe; color:#1d4ed8; }
    .assignment-section-option input { position:absolute; left:.7rem; top:50%; width:.85rem; height:.85rem; transform:translateY(-50%); accent-color:#2563eb; }
</style>
@endpush

@section('content')
<x-main-admin.page-header
    title="Subject Assignments"
    description="Filter by department, open an instructor to assign their subjects, or browse every assignment below."
    icon="bi bi-diagram-3-fill"
    eyebrow="Academic setup"
>
    <x-slot:actions>
        <a href="{{ route('settings.index') }}" class="btn-back" title="Change the active term in System Settings">
            <i class="bi bi-calendar3"></i> {{ $termLabel }}
        </a>
        @if($stage === 'instructor')
            <a href="{{ route('assignments.index', ['department' => $department]) }}" class="btn-back"><i class="bi bi-arrow-left"></i> Back to {{ $department }}</a>
        @endif
        <button class="btn-csv" onclick="openCsvModal('assignments')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

{{-- ============ ONE INSTRUCTOR: their form and their subjects ============ --}}
@if($stage === 'instructor')
    <div class="drill-crumbs">
        <a href="{{ route('assignments.index') }}">All Assignments</a>
        <i class="bi bi-chevron-right"></i>
        <a href="{{ route('assignments.index', ['department' => $department]) }}">{{ $department }}</a>
        <i class="bi bi-chevron-right"></i>
        <span class="current">{{ $instructor->full_name }}</span>
    </div>

    <div class="data-table-wrap">
        <div class="drill-person">
            <span class="drill-avatar">{{ strtoupper(substr($instructor->firstname, 0, 1)) }}{{ strtoupper(substr($instructor->lastname, 0, 1)) }}</span>
            <div style="flex:1 1 220px;min-width:0;">
                <h3>{{ $instructor->full_name }}</h3>
                <p>{{ $instructor->instructor_id }} · {{ $instructor->email }} · {{ $instructor->department_label }}</p>
            </div>
            @if(\App\Models\Instructor::tracksEmploymentStatus())
            <span class="badge-type {{ $instructor->employment_status === \App\Models\Instructor::EMPLOYMENT_REGULAR ? 'badge-regular' : 'badge-irregular' }}">{{ $instructor->employment_status ?: \App\Models\Instructor::EMPLOYMENT_REGULAR }}</span>
            @endif
            <span class="drill-load {{ $assignments->count() ? '' : 'empty' }}">{{ $assignments->count() }} assignment{{ $assignments->count() === 1 ? '' : 's' }}</span>
        </div>
    </div>

    <div class="data-table-wrap" style="margin-top:18px;">
        <div class="data-table-header">
            <h3><i class="bi bi-plus-circle-fill"></i> Assign a Subject</h3>
            <span>{{ $instructor->department_label }}</span>
        </div>
        <div style="padding:18px 22px;">
            <p class="panel-hint">Choose the program and year first — the subject and section lists fill in from there.</p>
            <form method="POST" action="{{ route('assignments.store') }}">
                @csrf
                <input type="hidden" name="instructor_id" value="{{ $instructor->instructor_id }}">
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="add_program" required onchange="refreshAssignmentForm('add')">
                            <option value="">Select program</option>
                            @foreach($programs as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="add_year_level" required onchange="refreshAssignmentForm('add')">
                            <option value="">Select year</option>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Subject <small class="text-muted">{{ $activeSemester ? '(only '.$activeSemester.' subjects for that program and year)' : '(only subjects set up for that program and year)' }}</small></label>
                    <select name="subject_id" id="add_subject" required>
                        <option value="">Select program and year first</option>
                    </select>
                </div>
                <div class="fg"><label>Sections <small class="text-muted">(select one or more)</small></label>
                    <details class="section-dropdown">
                        <summary>Choose sections</summary>
                        <div id="add_sections" class="section-checklist p-2" style="min-height:110px;max-height:180px;color:#111827;">
                            <span class="text-secondary small">Select program and year first</span>
                        </div>
                    </details>
                </div>
                <button type="submit" class="btn-save" style="display:block;margin:1rem auto 0;"><i class="bi bi-save-fill"></i> Save Assignment</button>
            </form>
        </div>
    </div>

    <div class="data-table-wrap" style="margin-top:18px;">
        <div class="data-table-header">
            <h3><i class="bi bi-table"></i> Current Subjects</h3>
            <span>{{ $assignments->count() }} results</span>
        </div>
        <div class="table-scroll">
            <table class="cms-table">
                <thead>
                    <tr><th>Subject</th><th>Program</th><th>Year</th><th>Section</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($assignments as $row)
                    <tr>
                        <td>{{ $row->subject->subject_code ?? '—' }} — {{ $row->subject->subject_description ?? '' }}</td>
                        <td>{{ $row->program }}</td>
                        <td>{{ $row->year_level }}</td>
                        <td>{{ $row->section }}</td>
                        <td style="white-space:nowrap;">
                            <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                            <form method="POST" action="{{ route('assignments.destroy', ['id' => $row->assignment_id]) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this assignment?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5"><div class="empty-state">This instructor has no subjects yet. Use the form above to add the first one.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- ============ BROWSE: department filter, roster, every assignment ============ --}}
@else
    <div class="data-table-wrap">
        <div class="data-table-header">
            <h3><i class="bi bi-buildings"></i> Departments</h3>
            <span>{{ count($departments) }} departments</span>
        </div>
        <div style="padding:16px 22px;">
            <div class="dept-tabs">
                <a class="dept-tab {{ $department === null ? 'active' : '' }}" href="{{ route('assignments.index', array_filter(['search' => $search])) }}">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span><strong>All Departments</strong><span class="dept-tab-meta">Everything below</span></span>
                </a>
                @foreach($departments as $tab)
                <a class="dept-tab {{ $department === $tab['name'] ? 'active' : '' }}" href="{{ route('assignments.index', array_filter(['department' => $tab['name'], 'search' => $search])) }}">
                    <i class="{{ $tab['icon'] }}"></i>
                    <span><strong>{{ $tab['name'] }}</strong><span class="dept-tab-meta">{{ $tab['instructors'] }} instructor{{ $tab['instructors'] === 1 ? '' : 's' }} · {{ $tab['assignments'] }} assignment{{ $tab['assignments'] === 1 ? '' : 's' }}</span></span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="filter-bar" style="margin-top:18px;">
        <form method="GET" action="{{ route('assignments.index') }}">
            @if($department !== null)<input type="hidden" name="department" value="{{ $department }}">@endif
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><input type="search" name="search" placeholder="Search instructor by name, ID or email" value="{{ $search }}"></div>
                <div class="col-md-3">
                    <select name="year_level">
                        <option value="">All Years</option>
                        @foreach([1,2,3,4] as $year)
                        <option value="{{ $year }}" {{ request('year_level') == $year ? 'selected' : '' }}>{{ $year }} Year</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input type="search" name="section" placeholder="Filter section" value="{{ request('section') }}"></div>
                <div class="col-md-2"><button type="submit" class="btn-filter w-100">Filter</button></div>
            </div>
        </form>
    </div>

    @if($showRoster)
    <div class="data-table-wrap" style="margin-top:18px;">
        <div class="data-table-header">
            <h3><i class="bi bi-person-video3"></i> {{ $department ?? 'Matching' }} Instructors</h3>
            <span>{{ count($roster) }} {{ count($roster) === 1 ? 'instructor' : 'instructors' }}</span>
        </div>
        <div style="padding:18px 22px;">
            <p class="panel-hint">Open an instructor to assign their subjects and sections.</p>
            @if($roster === [])
                <div class="drill-empty">
                    <i class="bi bi-search"></i>
                    No instructor matches {{ $search !== '' ? 'that search' : 'this department' }} yet.
                </div>
            @else
                <div class="drill-grid">
                    @foreach($roster as $person)
                    <a class="drill-card" href="{{ route('assignments.index', ['department' => $person['department'], 'instructor' => $person['id']]) }}">
                        <div>
                            <strong>{{ $person['name'] }}</strong>
                            <small>{{ $person['id'] }} · {{ $person['email'] }}</small>
                            <span class="drill-load {{ $person['assignments'] ? '' : 'empty' }}">{{ $person['assignments'] }} assignment{{ $person['assignments'] === 1 ? '' : 's' }}</span>
                            @if($person['position'])
                            <span class="badge-type {{ $person['position'] === \App\Models\Instructor::EMPLOYMENT_REGULAR ? 'badge-regular' : 'badge-irregular' }}" style="margin-top:.6rem;margin-left:.35rem;">{{ $person['position'] }}</span>
                            @endif
                        </div>
                        <i class="bi bi-chevron-right" style="color:#a3b3c9;"></i>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="data-table-wrap" style="margin-top:18px;">
        <div class="data-table-header">
            <h3><i class="bi bi-table"></i> {{ $department ? $department.' Assignments' : 'All Assignments' }}</h3>
            <span>{{ $assignments->total() }} results</span>
        </div>
        <div class="table-scroll">
            <table class="cms-table">
                <thead>
                    <tr><th>Instructor</th><th>Subject</th><th>Program</th><th>Year</th><th>Section</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($assignments as $row)
                    <tr>
                        <td>{{ $row->instructor->firstname ?? '—' }} {{ $row->instructor->lastname ?? '' }}</td>
                        <td>{{ $row->subject->subject_code ?? '—' }} — {{ $row->subject->subject_description ?? '' }}</td>
                        <td>{{ $row->program }}</td>
                        <td>{{ $row->year_level }}</td>
                        <td>{{ $row->section }}</td>
                        <td style="white-space:nowrap;">
                            <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                            <form method="POST" action="{{ route('assignments.destroy', ['id' => $row->assignment_id]) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this assignment?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="empty-state">No assignments match these filters. Open an instructor above to add one.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-main-admin.table-pagination :paginator="$assignments" label="assignments" />
    </div>
@endif

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Assignment</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>Instructor</label>
                        <select name="instructor_id" id="e_instructor" required>
                            <option value="">Select instructor</option>
                            @foreach($instructors as $inst)
                            <option value="{{ $inst->instructor_id }}">{{ $inst->lastname }}, {{ $inst->firstname }} — {{ $inst->department_label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Program</label>
                        <select name="program" id="e_program" required onchange="refreshAssignmentForm('edit')">
                            <option value="">Select program</option>
                            @foreach($programs as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="e_year_level" required onchange="refreshAssignmentForm('edit')">
                            <option value="">Select year</option>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Subject</label>
                        <select name="subject_id" id="e_subject" required>
                            <option value="">Select program and year first</option>
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Sections <small class="text-muted">(select one or more)</small></label>
                    <details class="section-dropdown">
                        <summary>Choose sections</summary>
                        <div id="e_sections" class="section-checklist p-2" style="min-height:110px;max-height:180px;color:#111827;"><span class="text-secondary small">Select program and year first</span></div>
                    </details>
                </div>
                <button type="submit" class="btn-save" style="display:block;margin:1rem auto 0;"><i class="bi bi-check-circle-fill"></i> Update Assignment</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const assignmentSections = @json($sections);
const assignmentSubjects = @json($subjects);
const activeSemester = @json($activeSemester);

function assignmentFormIds(formType) {
    return formType === 'add'
        ? { program: 'add_program', year: 'add_year_level', subject: 'add_subject', sections: 'add_sections' }
        : { program: 'e_program', year: 'e_year_level', subject: 'e_subject', sections: 'e_sections' };
}

// Mirrors InstructorAssignmentController::ensureSubjectScope() — a subject is
// offered only where it is configured, so the form cannot build a combination
// the server would refuse.
function filterAssignmentSubjects(formType, selectedSubject = '') {
    const ids = assignmentFormIds(formType);
    const program = document.getElementById(ids.program).value;
    const yearLevel = document.getElementById(ids.year).value;
    const select = document.getElementById(ids.subject);
    if (!select) { return; }

    if (!program || !yearLevel) {
        select.innerHTML = '<option value="">Select program and year first</option>';
        return;
    }

    // Only the active semester's subjects can be assigned. The one an existing
    // assignment already uses stays listed even if it is from another semester,
    // so editing that row is not a dead end.
    const matching = assignmentSubjects.filter(subject =>
        String(subject.year_level) === String(yearLevel)
        && String(subject.program || '').split(',').map(part => part.trim()).includes(program)
        && (!activeSemester
            || subject.semester === activeSemester
            || String(subject.subject_id) === String(selectedSubject)));

    select.innerHTML = matching.length
        ? '<option value="">Select subject</option>'
        : `<option value="">No ${activeSemester ? activeSemester + ' ' : ''}subject is set up for this program and year</option>`;

    matching.forEach(subject => {
        const option = document.createElement('option');
        option.value = subject.subject_id;
        option.textContent = activeSemester && subject.semester !== activeSemester
            ? `${subject.subject_code} — ${subject.subject_description} (${subject.semester})`
            : `${subject.subject_code} — ${subject.subject_description}`;
        option.selected = String(subject.subject_id) === String(selectedSubject);
        select.appendChild(option);
    });
}

function filterAssignmentSections(formType, selectedSection = '') {
    const ids = assignmentFormIds(formType);
    const program = document.getElementById(ids.program).value;
    const yearLevel = document.getElementById(ids.year).value;
    const select = document.getElementById(ids.sections);
    if (!select) { return; }
    const matching = assignmentSections.filter(section => section.program === program && String(section.year_level) === String(yearLevel));
    select.innerHTML = '';
    if (!matching.length) {
        select.innerHTML = '<span class="text-secondary small">No managed sections available</span>';
        return;
    }
    const selectedSections = Array.isArray(selectedSection) ? selectedSection : [selectedSection];
    matching.forEach(section => {
        const label = document.createElement('label');
        label.className = 'assignment-section-option';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'sections[]';
        checkbox.value = section.section;
        checkbox.checked = selectedSections.includes(section.section);
        label.append(checkbox, document.createTextNode(section.section));
        select.appendChild(label);
    });
}

function refreshAssignmentForm(formType, selectedSubject = '', selectedSection = '') {
    filterAssignmentSubjects(formType, selectedSubject);
    filterAssignmentSections(formType, selectedSection);
}

function openEdit(assignment) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/assignments') }}/' + (assignment.assignment_id || assignment.id);
    document.getElementById('e_instructor').value = assignment.instructor_id || '';
    document.getElementById('e_program').value = assignment.program || '';
    document.getElementById('e_year_level').value = assignment.year_level || '';
    refreshAssignmentForm('edit', assignment.subject_id || '', assignment.section || '');
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
</script>
@endpush
