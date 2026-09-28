@extends('mainAdmin.layouts.admin')
@section('title', 'Students — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Student Accounts"
    description="Manage student records, programs, sections, and access credentials."
    icon="bi bi-people-fill"
    eyebrow="User management"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Student</button>
        <button class="btn-csv" onclick="openCsvModal('students')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

{{-- Program pills --}}
<div class="dept-pills">
    <a href="{{ route('students.index') }}" class="dept-pill {{ !request('program') ? 'active' : '' }}">All</a>
    @foreach($programsList as $p)
    <a href="{{ route('students.index', ['program' => $p]) }}"
       class="dept-pill {{ request('program') === $p ? 'active' : '' }}">{{ $p }}</a>
    @endforeach
</div>

{{-- Filter bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('students.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <select name="filter">
                    <option value="">Student Type</option>
                    <option value="Regular"   {{ request('filter')==='Regular'   ? 'selected' : '' }}>Regular</option>
                    <option value="Irregular" {{ request('filter')==='Irregular' ? 'selected' : '' }}>Irregular</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="year_level">
                    <option value="">Year Level</option>
                    @foreach($yearLevelOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('year_level') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="order">
                    <option value="DESC" {{ request('order','DESC')==='DESC' ? 'selected' : '' }}>⬇ Newest</option>
                    <option value="ASC"  {{ request('order')==='ASC'         ? 'selected' : '' }}>⬆ Oldest</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" placeholder="🔎 Search ID / Name / Email"
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-filter w-100">Go</button>
            </div>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Student Records</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $students->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>Student ID</th><th>Name</th><th>Email</th>
                    <th>Program</th><th>Year</th><th>Section</th><th>Type</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($students as $row)
            <tr>
                <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->student_id }}</td>
                <td><strong>{{ $row->firstname }} {{ $row->middlename ? $row->middlename.' ' : '' }}{{ $row->lastname }}</strong></td>
                <td style="color:var(--muted);font-size:12px;">{{ $row->email }}</td>
                <td>{{ $row->program }}</td>
                <td>{{ $row->year_level }}</td>
                <td>{{ $row->section }}</td>
                <td>
                    <span class="badge-type {{ $row->student_type==='Regular' ? 'badge-regular' : 'badge-irregular' }}">
                        {{ $row->student_type }}
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'>
                        <i class="bi bi-pencil-fill"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('students.destroy', $row->student_id) }}"
                          style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this student record?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                    </form>
                    <form method="POST" action="{{ route('students.reset', $row->student_id) }}" style="display:inline;" data-confirm-title="Reset Password?" data-confirm="A unique one-time temporary password will be generated for this student." data-confirm-button="Yes, Reset" data-confirm-tone="warning">
                        @csrf @method('PATCH')
                        <button type="submit" class="act-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>No student records found.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px 20px;">
        <x-main-admin.table-pagination :paginator="$students" label="students" />
    </div>
</div>

{{-- ADD MODAL --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-person-plus-fill" style="color:var(--success);margin-right:8px;"></i>Add Student</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('students.store') }}" autocomplete="off">
                @csrf
                {{-- Same form fields as original students.php --}}
                <div class="form-row">
                    <div class="fg"><label>Student ID</label><input name="student_id" required maxlength="9" inputmode="numeric" pattern="\d{4}-\d{4}" title="Format: 2000-1234" placeholder="2000-1234" autocomplete="off"></div>
                    <div class="fg"><label>Email *</label><input type="email" name="email" required autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>First Name *</label><input name="firstname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Middle Name</label><input name="middlename" autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Last Name *</label><input name="lastname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Suffix</label><input name="suffix" autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Password <small>(optional)</small></label><input type="password" name="password" id="add_password" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
                    <div class="fg"><label>Confirm Password</label><input type="password" name="password_confirmation" id="add_password_confirmation" autocomplete="new-password" placeholder="Re-enter the password"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="add_program" required onchange="filterAddSections()">
                            <option value="">-- Select Program --</option>
                            @foreach($programsList as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="add_year_level" required onchange="filterAddSections()">
                            <option value="">-- Select Year --</option>
                            @foreach($yearLevelOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Section</label>
                        <select name="section" id="add_section" required>
                            <option value="">-- Select Program & Year --</option>
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Student Type</label>
                    <select name="student_type"><option>Regular</option><option>Irregular</option></select>
                </div>
                <div class="student-form-actions">
                    <button type="submit" class="btn-save"><i class="bi bi-plus-circle-fill"></i> Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT MODAL (same pattern with PUT method) --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Student</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="student_id" id="e_id">
                <div class="form-row">
                    <div class="fg"><label>Student ID</label><input id="e_student_id_display" readonly></div>
                    <div class="fg"><label>Email *</label><input type="email" name="email" id="e_email" required autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>First Name *</label><input name="firstname" id="e_firstname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Middle Name</label><input name="middlename" id="e_middlename" autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Last Name *</label><input name="lastname" id="e_lastname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Suffix</label><input name="suffix" id="e_suffix" autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>New Password <small class="text-muted">(leave blank to keep current)</small></label><input type="password" name="password" id="e_password" autocomplete="new-password"></div>
                    <div class="fg"><label>Confirm New Password</label><input type="password" name="password_confirmation" id="e_password_confirmation" autocomplete="new-password" placeholder="Re-enter the new password"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="e_program" required onchange="filterEditSections()">
                            @foreach($programsList as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="e_year_level" required onchange="filterEditSections()">
                            @foreach($yearLevelOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Section</label><select name="section" id="e_section" required><option value="">-- Select Program & Year --</option></select></div>
                </div>
                <div class="fg"><label>Student Type</label><select name="student_type" id="e_student_type"><option value="Regular">Regular</option><option value="Irregular">Irregular</option></select></div>
                <div class="student-form-actions">
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check-circle-fill"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function filterAddSections() {
    const program   = document.getElementById('add_program').value;
    const yearLevel = document.getElementById('add_year_level').value;
    const sel       = document.getElementById('add_section');
    sel.innerHTML   = '<option value="">-- Select Section --</option>';
    if (!program || !yearLevel) return;
    sel.disabled = true;
    sel.innerHTML = '<option value="">Loading sections…</option>';
    try {
        const response = await fetch(@json(route('api.sections')), { headers: { Accept: 'application/json' } });
        const sections = await response.json();
        sel.innerHTML = '<option value="">-- Select Section --</option>';
        sections.filter(s => s.program === program && String(s.year_level) === String(yearLevel))
            .forEach(s => {
            const o = document.createElement('option');
            o.value = s.section; o.textContent = s.section;
            sel.appendChild(o);
        });
    } catch (error) {
        sel.innerHTML = '<option value="">Unable to load sections</option>';
    } finally {
        sel.disabled = false;
    }
}

function openAddModal() {
    document.getElementById('add_password').value = '';
    document.getElementById('add_password_confirmation').value = '';
    document.getElementById('addModal').classList.add('show');
}
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

async function filterEditSections(selectedSection = '') {
    const program = document.getElementById('e_program').value;
    const yearLevel = document.getElementById('e_year_level').value;
    const sel = document.getElementById('e_section');
    sel.innerHTML = '<option value="">-- Select Section --</option>';
    if (!program || !yearLevel) return;
    sel.disabled = true;
    try {
        const response = await fetch(@json(route('api.sections')), { headers: { Accept: 'application/json' } });
        const sections = await response.json();
        sections.filter(s => s.program === program && String(s.year_level) === String(yearLevel)).forEach(s => {
            const option = document.createElement('option');
            option.value = s.section;
            option.textContent = s.section;
            option.selected = s.section === selectedSection;
            sel.appendChild(option);
        });
    } catch (error) {
        sel.innerHTML = '<option value="">Unable to load sections</option>';
    } finally {
        sel.disabled = false;
    }
}

async function openEdit(student) {
    const form = document.getElementById('editForm');
    form.action = `{{ url('/mainAdmin/students') }}/${student.student_id}`;
    document.getElementById('e_id').value = student.student_id;
    document.getElementById('e_student_id_display').value = student.student_id || '';
    document.getElementById('e_email').value = student.email || '';
    document.getElementById('e_firstname').value = student.firstname || '';
    document.getElementById('e_middlename').value = student.middlename || '';
    document.getElementById('e_lastname').value = student.lastname || '';
    document.getElementById('e_suffix').value = student.suffix || '';
    document.getElementById('e_password').value = '';
    document.getElementById('e_password_confirmation').value = '';
    document.getElementById('e_program').value = student.program || '';
    document.getElementById('e_year_level').value = String(student.year_level || '');
    document.getElementById('e_student_type').value = student.student_type || 'Regular';
    await filterEditSections(student.section || '');
    // populate other fields…
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('show'); })
);
</script>
@endpush
