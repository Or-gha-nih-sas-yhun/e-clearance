@extends('mainAdmin.layouts.admin')
@section('title', 'Treasurer Management — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Treasurer Accounts"
    description="Create and manage department and section treasurer accounts."
    icon="bi bi-wallet2"
    eyebrow="User management"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Treasurer</button>
        <button class="btn-csv" onclick="openCsvModal('treasurers')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('treasurers.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="department" {{ request('type')==='department' ? 'selected' : '' }}>Department</option>
                    <option value="section" {{ request('type')==='section' ? 'selected' : '' }}>Section</option>
                </select>
            </div>
            <div class="col-md-2"><input type="search" name="department" placeholder="Department" value="{{ request('department') }}"></div>
            <div class="col-md-2"><input type="search" name="program" placeholder="Program" value="{{ request('program') }}"></div>
            <div class="col-md-2"><input type="search" name="year_level" placeholder="Year Level" value="{{ request('year_level') }}"></div>
            <div class="col-md-2"><input type="search" name="section" placeholder="Section" value="{{ request('section') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn-filter w-100">Filter</button></div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Treasurer Accounts</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $treasurers->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th><th>Department / Section</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($treasurers as $row)
                <tr>
                    <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->treasurer_id }}</td>
                    <td>{{ $row->firstname }} {{ $row->middlename ? $row->middlename.' ' : '' }}{{ $row->lastname }} {{ $row->suffix }}</td>
                    <td style="color:var(--muted);font-size:12px;">{{ $row->email }}</td>
                    <td>{{ $row->treasurer_type === 'department' ? 'Department' : 'Section' }}</td>
                    <td>
                        @if($row->treasurer_type==='department')
                            {{ $row->department ?: '—' }}
                        @else
                            {{ $row->program ?: '—' }} / {{ $row->year_level ?: '—' }} / {{ $row->section ?: '—' }}
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('treasurers.destroy', $row->id) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this treasurer account?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state">No treasurer accounts found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$treasurers" label="treasurers" />
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-person-plus-fill" style="color:var(--success);margin-right:8px;"></i>Add Treasurer</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('treasurers.store') }}" autocomplete="off">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>First Name</label><input type="text" name="firstname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Middle Name</label><input type="text" name="middlename" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Last Name</label><input type="text" name="lastname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Suffix</label><input type="text" name="suffix"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Email</label><input type="email" name="email" required></div>
                    <div class="fg"><label>Password <small>(optional)</small></label><input type="password" name="password" id="add_password" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
                </div>
                <div class="fg"><label>Confirm Password</label><input type="password" name="password_confirmation" id="add_password_confirmation" autocomplete="new-password" placeholder="Re-enter the password"></div>
                <div class="form-row">
                    <div class="fg"><label>Type</label>
                        <select name="treasurer_type" id="t_type" required onchange="toggleTreasurerFields()">
                            <option value="">Select type</option>
                            <option value="department">Department Treasurer</option>
                            <option value="section">Section Treasurer</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" id="departmentFields" style="display:none;">
                    <div class="fg"><label>Department</label>
                        <select name="department" id="t_department">
                            <option value="">Select department</option>
                            @foreach(['BSIT','BSED','BEED','BSBA','BSHM'] as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="sectionFields" style="display:none;">
                    <div class="form-row">
                        <div class="fg"><label>Program</label>
                            <select name="program" id="t_program">
                                <option value="">Select program</option>
                                @foreach(['BSIT','BSBA','BSHM','BSED','BEED'] as $prog)
                                <option value="{{ $prog }}">{{ $prog }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fg"><label>Year Level</label>
                            <select name="year_level" id="t_year_level">
                                <option value="">Select year</option>
                                @foreach([1,2,3,4] as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="fg"><label>Section</label><input type="text" name="section" id="t_section"></div>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-save-fill"></i> Save Treasurer</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Treasurer</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm" autocomplete="off">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>First Name</label><input type="text" name="firstname" id="e_firstname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Middle Name</label><input type="text" name="middlename" id="e_middlename" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Last Name</label><input type="text" name="lastname" id="e_lastname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Suffix</label><input type="text" name="suffix" id="e_suffix"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Email</label><input type="email" name="email" id="e_email" required></div>
                    <div class="fg"><label>New Password <small>(leave blank to keep)</small></label><input type="password" name="password" id="e_password" autocomplete="new-password"></div>
                </div>
                <div class="fg"><label>Confirm New Password</label><input type="password" name="password_confirmation" id="e_password_confirmation" autocomplete="new-password" placeholder="Re-enter the new password"></div>
                <div class="form-row">
                    <div class="fg"><label>Type</label>
                        <select name="treasurer_type" id="e_type" required onchange="toggleTreasurerFields('edit')">
                            <option value="">Select type</option>
                            <option value="department">Department Treasurer</option>
                            <option value="section">Section Treasurer</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" id="e_departmentFields" style="display:none;">
                    <div class="fg"><label>Department</label>
                        <select name="department" id="e_department">
                            <option value="">Select department</option>
                            @foreach(['BSIT','BSED','BEED','BSBA','BSHM'] as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="e_sectionFields" style="display:none;">
                    <div class="form-row">
                        <div class="fg"><label>Program</label>
                            <select name="program" id="e_program">
                                <option value="">Select program</option>
                                @foreach(['BSIT','BSBA','BSHM','BSED','BEED'] as $prog)
                                <option value="{{ $prog }}">{{ $prog }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fg"><label>Year Level</label>
                            <select name="year_level" id="e_year_level">
                                <option value="">Select year</option>
                                @foreach([1,2,3,4] as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="fg"><label>Section</label><input type="text" name="section" id="e_section"></div>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Update Treasurer</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleTreasurerFields(mode = 'create') {
    const type = document.getElementById(mode === 'create' ? 't_type' : 'e_type').value;
    const deptArea = document.getElementById(mode === 'create' ? 'departmentFields' : 'e_departmentFields');
    const secArea = document.getElementById(mode === 'create' ? 'sectionFields' : 'e_sectionFields');
    if (type === 'department') {
        deptArea.style.display = 'block';
        secArea.style.display = 'none';
    } else if (type === 'section') {
        deptArea.style.display = 'none';
        secArea.style.display = 'block';
    } else {
        deptArea.style.display = 'none';
        secArea.style.display = 'none';
    }
}

function openAddModal() {
    document.getElementById('add_password').value = '';
    document.getElementById('add_password_confirmation').value = '';
    toggleTreasurerFields('create');
    document.getElementById('addModal').classList.add('show');
}
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

function openEdit(treasurer) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/treasurers') }}/' + treasurer.id;
    document.getElementById('e_firstname').value = treasurer.firstname || '';
    document.getElementById('e_middlename').value = treasurer.middlename || '';
    document.getElementById('e_lastname').value = treasurer.lastname || '';
    document.getElementById('e_suffix').value = treasurer.suffix || '';
    document.getElementById('e_email').value = treasurer.email || '';
    document.getElementById('e_password').value = '';
    document.getElementById('e_password_confirmation').value = '';
    document.getElementById('e_type').value = treasurer.treasurer_type || '';
    document.getElementById('e_department').value = treasurer.department || '';
    document.getElementById('e_program').value = treasurer.program || '';
    document.getElementById('e_year_level').value = treasurer.year_level || '';
    document.getElementById('e_section').value = treasurer.section || '';
    toggleTreasurerFields('edit');
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
</script>
@endpush
