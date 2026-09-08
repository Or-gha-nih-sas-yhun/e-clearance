@extends('mainAdmin.layouts.admin')
@section('title', 'Registrar — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Registrar Accounts"
    description="Manage registrar accounts, credentials, and system access settings."
    icon="bi bi-building-check"
    eyebrow="User management"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Registrar</button>
        <button class="btn-csv" onclick="openCsvModal('registrar')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('registrar.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-6"><input type="search" name="search" placeholder="Search name, email or ID" value="{{ request('search') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn-filter w-100">Search</button></div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Registrar Accounts</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $registrars->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($registrars as $row)
                <tr>
                    <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->registrar_id }}</td>
                    <td>{{ $row->firstname }} {{ $row->lastname }}</td>
                    <td style="color:var(--muted);font-size:12px;">{{ $row->email }}</td>
                    <td>{{ $row->role }}</td>
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('registrar.destroy', $row->id) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this registrar account?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5"><div class="empty-state">No registrar accounts found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$registrars" label="registrars" />
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-person-plus-fill" style="color:var(--success);margin-right:8px;"></i>Add Registrar</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('registrar.store') }}" autocomplete="off">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>First Name</label><input type="text" name="firstname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Last Name</label><input type="text" name="lastname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Email</label><input type="email" name="email" required></div>
                    <div class="fg"><label>Password <small>(optional)</small></label><input type="password" name="password" id="add_password" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
                </div>
                <div class="fg"><label>Confirm Password</label><input type="password" name="password_confirmation" id="add_password_confirmation" autocomplete="new-password" placeholder="Re-enter the password"></div>
                <button type="submit" class="btn-save"><i class="bi bi-plus-circle-fill"></i> Save Registrar</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Registrar</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm" autocomplete="off">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>First Name</label><input type="text" name="firstname" id="e_firstname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                    <div class="fg"><label>Last Name</label><input type="text" name="lastname" id="e_lastname" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Email</label><input type="email" name="email" id="e_email" required></div>
                    <div class="fg"><label>New Password <small>(leave blank to keep current)</small></label><input type="password" name="password" id="e_password" autocomplete="new-password"></div>
                </div>
                <div class="fg"><label>Confirm New Password</label><input type="password" name="password_confirmation" id="e_password_confirmation" autocomplete="new-password" placeholder="Re-enter the new password"></div>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Update Registrar</button>
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
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
function openEdit(record) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/registrar-list') }}/' + record.id;
    document.getElementById('e_firstname').value = record.firstname || '';
    document.getElementById('e_lastname').value = record.lastname || '';
    document.getElementById('e_email').value = record.email || '';
    document.getElementById('e_password').value = '';
    document.getElementById('e_password_confirmation').value = '';
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
</script>
@endpush
