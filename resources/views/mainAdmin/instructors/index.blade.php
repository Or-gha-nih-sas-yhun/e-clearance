@extends('mainAdmin.layouts.admin')
@section('title', 'Instructors — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Instructors"
    description="Create, update, and manage instructor accounts and department access."
    icon="bi bi-person-video3"
    eyebrow="User management"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Instructor</button>
        <button class="btn-csv" onclick="openCsvModal('instructors')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('instructors.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="search" name="search" placeholder="Search name, email, ID" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="department">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            @if($employmentAvailable)
            <div class="col-md-2">
                <select name="employment">
                    <option value="">All Positions</option>
                    @foreach($employmentStatuses as $status)
                    <option value="{{ $status }}" {{ request('employment') === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <select name="order">
                    <option value="DESC" {{ request('order','DESC')==='DESC' ? 'selected' : '' }}>Newest</option>
                    <option value="ASC" {{ request('order')==='ASC' ? 'selected' : '' }}>Oldest</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-filter w-100">Apply</button>
            </div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Instructor Records</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $instructors->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Department</th>@if($employmentAvailable)<th>Position</th>@endif<th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($instructors as $row)
                <tr>
                    <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->instructor_id }}</td>
                    <td>{{ $row->firstname }} {{ $row->middlename ? $row->middlename.' ' : '' }}{{ $row->lastname }} {{ $row->suffix }}</td>
                    <td style="color:var(--muted);font-size:12px;">{{ $row->email }}</td>
                    <td>{{ $row->department }}</td>
                    @if($employmentAvailable)
                    <td><span class="badge-type {{ $row->employment_status === \App\Models\Instructor::EMPLOYMENT_REGULAR ? 'badge-regular' : 'badge-irregular' }}">{{ $row->employment_status ?: \App\Models\Instructor::EMPLOYMENT_REGULAR }}</span></td>
                    @endif
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('instructors.destroy', $row->instructor_id) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this instructor?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                        <form method="POST" action="{{ route('instructors.reset', $row->instructor_id) }}" style="display:inline;" data-confirm-title="Reset Password?" data-confirm="A unique one-time temporary password will be generated for this instructor." data-confirm-button="Yes, Reset" data-confirm-tone="warning">
                            @csrf @method('PATCH')
                            <button type="submit" class="act-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $employmentAvailable ? 6 : 5 }}"><div class="empty-state">No instructors found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$instructors" label="instructors" />
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-person-plus-fill" style="color:var(--success);margin-right:8px;"></i>Add Instructor</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('instructors.store') }}" autocomplete="off">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>ID</label><input type="text" name="instructor_id" required autocomplete="off"></div>
                    <div class="fg"><label>Email</label><input type="email" name="email" required autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>First Name</label><input type="text" name="firstname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Middle Name</label><input type="text" name="middlename" autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Last Name</label><input type="text" name="lastname" required autocomplete="off" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Suffix</label><input type="text" name="suffix" autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Password <small>(optional)</small></label><input type="password" name="password" id="add_password" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
                    <div class="fg"><label>Confirm Password</label><input type="password" name="password_confirmation" id="add_password_confirmation" autocomplete="new-password" placeholder="Re-enter the password"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Department</label>
                        <select name="department" required>
                            <option value="">Choose department</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($employmentAvailable)
                    <div class="fg"><label>Position</label>
                        <select name="employment_status" required>
                            <option value="">Choose position</option>
                            @foreach($employmentStatuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
                @unless($employmentAvailable)
                <p style="font-size:12px;color:var(--muted);margin:-4px 0 12px;">Position (Regular / Part Timer) is unavailable: <code>instructor_account</code> has no <code>employment_status</code> column yet. Run <code>database/sql/instructor_employment_status.sql</code> to add it.</p>
                @endunless
                <button type="submit" class="btn-save"><i class="bi bi-plus-circle-fill"></i> Save Instructor</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Instructor</h4>
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
                    <div class="fg"><label>Department</label>
                        <select name="department" id="e_department" required>
                            <option value="">Choose department</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if($employmentAvailable)
                <div class="form-row">
                    <div class="fg"><label>Position</label>
                        <select name="employment_status" id="e_employment_status" required>
                            <option value="">Choose position</option>
                            @foreach($employmentStatuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
                <div class="form-row">
                    <div class="fg"><label>New Password <small>(leave blank to keep current)</small></label><input type="password" name="password" id="e_password" autocomplete="new-password"></div>
                    <div class="fg"><label>Confirm New Password</label><input type="password" name="password_confirmation" id="e_password_confirmation" autocomplete="new-password" placeholder="Re-enter the new password"></div>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Update Instructor</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAddModal() {
    document.getElementById('add_password').value = '';
    document.getElementById('add_password_confirmation').value = '';
    document.getElementById('addModal').classList.add('show');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}
function openEdit(instructor) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/instructors') }}/' + encodeURIComponent(instructor.instructor_id);
    document.getElementById('e_firstname').value = instructor.firstname || '';
    document.getElementById('e_middlename').value = instructor.middlename || '';
    document.getElementById('e_lastname').value = instructor.lastname || '';
    document.getElementById('e_suffix').value = instructor.suffix || '';
    document.getElementById('e_email').value = instructor.email || '';
    document.getElementById('e_department').value = instructor.department || '';
    const position = document.getElementById('e_employment_status');
    if (position) { position.value = instructor.employment_status || '{{ \App\Models\Instructor::EMPLOYMENT_REGULAR }}'; }
    document.getElementById('e_password').value = '';
    document.getElementById('e_password_confirmation').value = '';
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}
</script>
@endpush
