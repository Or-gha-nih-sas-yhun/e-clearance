@extends('mainAdmin.layouts.admin')
@section('title', 'Subject Codes — ClearanceMS')

@push('styles')
<style>
.department-options { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
.fg .department-option {
    display:flex; align-items:center; gap:8px; min-height:42px; margin:0; padding:9px 11px;
    border:1px solid #d7e0ea; border-radius:8px; background:rgba(255,255,255,.76);
    color:#334155; font-size:.8rem; font-weight:700; cursor:pointer;
}
.fg .department-option:hover { border-color:#83b9e2; background:#f0f9ff; }
.fg .department-option input[type="checkbox"] {
    width:16px; height:16px; min-height:0; margin:0; padding:0; accent-color:#168dcc; box-shadow:none;
}
.fg .department-help { display:block; margin-top:6px; color:#64748b; font-size:.72rem; }
@media (max-width:480px) { .department-options { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
@endpush

@section('content')
<x-main-admin.page-header
    title="Subject Codes"
    description="Create and maintain subject codes, descriptions, and program assignments."
    icon="bi bi-journal-bookmark-fill"
    eyebrow="Academic setup"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Subject</button>
        <button class="btn-csv" onclick="openCsvModal('subject_codes')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('subjects.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><input type="search" name="search" placeholder="Search code or description" value="{{ request('search') }}"></div>
            <div class="col-md-2">
                <select name="department">
                    <option value="">All Departments</option>
                    @foreach($programs as $program)
                    <option value="{{ $program }}" {{ request('department') === $program ? 'selected' : '' }}>{{ $program }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="year_level">
                    <option value="">All Years</option>
                    @foreach([1,2,3,4] as $year)
                    <option value="{{ $year }}" {{ request('year_level') == $year ? 'selected' : '' }}>{{ $year }} Year</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="semester">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $sem)
                    <option value="{{ $sem }}" {{ request('semester') === $sem ? 'selected' : '' }}>{{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn-filter w-100">Filter</button></div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Subject Codes</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $subjects->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr><th>Code</th><th>Description</th><th>Year</th><th>Program</th><th>Semester</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($subjects as $row)
                <tr>
                    <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->subject_code }}</td>
                    <td>{{ $row->subject_description }}</td>
                    <td>{{ $row->year_level }}</td>
                    <td>{{ $row->program }}</td>
                    <td>{{ $row->semester }}</td>
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('subjects.destroy', $row->subject_id) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this subject?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state">No subject codes found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$subjects" label="subjects" />
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-plus-circle-fill" style="color:var(--success);margin-right:8px;"></i>Add Subject Code</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('subjects.store') }}">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>Subject Code</label><input type="text" name="subject_code" required maxlength="30"></div>
                    <div class="fg"><label>Description</label><input type="text" name="subject_description" required></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" required>
                            <option value="">Select year</option>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Semester</label>
                        <select name="semester" required>
                            <option value="">Select semester</option>
                            @foreach($semesters as $semester)
                            <option value="{{ $semester }}">{{ $semester }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Department</label>
                    <div class="department-options">
                        @foreach($programs as $program)
                        <label class="department-option">
                            <input type="checkbox" name="program[]" value="{{ $program }}" data-skip-modal-validation>
                            <span>{{ $program }}</span>
                        </label>
                        @endforeach
                    </div>
                    <small class="department-help">Select one or more departments.</small>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-save-fill"></i> Save Subject</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Subject</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>Subject Code</label><input type="text" name="subject_code" id="e_subject_code" required maxlength="30"></div>
                    <div class="fg"><label>Description</label><input type="text" name="subject_description" id="e_subject_description" required></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="e_year_level" required>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Semester</label>
                        <select name="semester" id="e_semester" required>
                            @foreach($semesters as $semester)
                            <option value="{{ $semester }}">{{ $semester }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Department</label>
                    <div class="department-options" id="e_program">
                        @foreach($programs as $program)
                        <label class="department-option">
                            <input type="checkbox" name="program[]" value="{{ $program }}" data-skip-modal-validation>
                            <span>{{ $program }}</span>
                        </label>
                        @endforeach
                    </div>
                    <small class="department-help">Select one or more departments.</small>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Update Subject</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAddModal() { document.getElementById('addModal').classList.add('show'); }
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
function openEdit(subject) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/subject-codes') }}/' + subject.subject_id;
    document.getElementById('e_subject_code').value = subject.subject_code || '';
    document.getElementById('e_subject_description').value = subject.subject_description || '';
    document.getElementById('e_year_level').value = subject.year_level;
    document.getElementById('e_semester').value = subject.semester;
    const programs = subject.program ? subject.program.split(',') : [];
    document.querySelectorAll('#e_program input[type="checkbox"]').forEach(
        checkbox => checkbox.checked = programs.includes(checkbox.value)
    );
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

document.querySelectorAll('#addModal form, #editForm').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        if (form.querySelectorAll('input[name="program[]"]:checked').length === 0) {
            event.preventDefault();
            showActionDialog({
                title: 'Select a Department',
                message: 'Choose at least one department for this subject.',
                confirmText: 'Okay',
                tone: 'danger',
                notice: true,
            });
            return;
        }

        if (!form.checkValidity()) return;

        form.dataset.submitting = 'true';
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving...';
        }
    });
});
</script>
@endpush
