@extends('mainAdmin.layouts.admin')
@section('title', 'Registration List — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Student Registration List"
    description="Microsoft accounts cleared to self-register. Inactive entries may still register; active ones already have an account."
    icon="bi bi-person-vcard"
    eyebrow="User management"
>
    <x-slot:actions>
        @if($entries !== null)
            <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Entry</button>
            <button class="btn-csv" onclick="openCsvModal('student_registry')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
        @endif
    </x-slot:actions>
</x-main-admin.page-header>

@if($entries === null)
    <div class="data-table-wrap">
        <div style="padding:28px 22px;">
            <div class="empty-state" style="text-align:left;">
                <i class="bi bi-database-exclamation"></i>
                <strong style="display:block;margin-bottom:6px;">The <code>student_registry</code> table does not exist yet.</strong>
                <span style="color:var(--muted);font-size:13px;">
                    Run <code>php artisan migrate</code>, or create the table manually with the SQL in
                    <code>database/sql/student_registry.sql</code>. Student self-registration stays switched off until then.
                </span>
            </div>
        </div>
    </div>
@else

{{-- Status pills --}}
<div class="dept-pills">
    <a href="{{ route('student-registry.index') }}" class="dept-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('student-registry.index', ['status' => 'inactive']) }}" class="dept-pill {{ request('status') === 'inactive' ? 'active' : '' }}">Inactive ({{ $counts['inactive'] }})</a>
    <a href="{{ route('student-registry.index', ['status' => 'active']) }}" class="dept-pill {{ request('status') === 'active' ? 'active' : '' }}">Active ({{ $counts['active'] }})</a>
</div>

{{-- Filter bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('student-registry.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="order">
                    <option value="DESC" {{ request('order','DESC')==='DESC' ? 'selected' : '' }}>⬇ Newest</option>
                    <option value="ASC"  {{ request('order')==='ASC' ? 'selected' : '' }}>⬆ Oldest</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" placeholder="🔎 Search Student ID / Microsoft account" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-filter w-100">Go</button>
            </div>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Registration Entries</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $entries->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>Student ID</th><th>Microsoft Account</th><th>Status</th><th>Registered</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($entries as $row)
            <tr>
                <td style="font-family:monospace;color:var(--accent2);font-weight:700;">{{ $row->student_id }}</td>
                <td style="color:var(--muted);font-size:12px;">{{ $row->ms_account }}</td>
                <td>
                    <span class="badge-type {{ $row->status === 'active' ? 'badge-regular' : 'badge-irregular' }}">
                        {{ ucfirst($row->status) }}
                    </span>
                </td>
                <td style="color:var(--muted);font-size:12px;">{{ $row->registered_at ? \Illuminate\Support\Carbon::parse($row->registered_at)->format('M d, Y g:i A') : '—' }}</td>
                <td style="white-space:nowrap;">
                    <button class="act-edit" onclick='openEdit(@json($row))'>
                        <i class="bi bi-pencil-fill"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('student-registry.destroy', $row->id) }}"
                          style="display:inline;" data-confirm-title="Remove Entry" data-confirm="Remove this Microsoft account from the registration list?&#10;It will no longer be able to register." data-confirm-button="Yes, Remove">
                        @csrf @method('DELETE')
                        <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-person-vcard"></i>No registration entries found.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px 20px;">
        <x-main-admin.table-pagination :paginator="$entries" label="entries" />
    </div>
</div>

{{-- ADD MODAL --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-person-plus-fill" style="color:var(--success);margin-right:8px;"></i>Add Registration Entry</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('student-registry.store') }}" autocomplete="off">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>Student ID *</label><input name="student_id" required placeholder="2026-0001" pattern="\d{4}-\d{4}" title="Format: 2026-0001" autocomplete="off"></div>
                    <div class="fg"><label>Microsoft Account *</label><input type="email" name="ms_account" required placeholder="name@mcc.edu.ph" autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Status *</label>
                        <select name="status" required>
                            <option value="inactive">Inactive — may register</option>
                            <option value="active">Active — already registered</option>
                        </select>
                    </div>
                </div>
                <div class="student-form-actions">
                    <button type="submit" class="btn-save"><i class="bi bi-plus-circle-fill"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--accent2);margin-right:8px;"></i>Edit Registration Entry</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm" autocomplete="off">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>Student ID *</label><input name="student_id" id="edit_student_id" required pattern="\d{4}-\d{4}" title="Format: 2026-0001" autocomplete="off"></div>
                    <div class="fg"><label>Microsoft Account *</label><input type="email" name="ms_account" id="edit_ms_account" required autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Status *</label>
                        <select name="status" id="edit_status" required>
                            <option value="inactive">Inactive — may register</option>
                            <option value="active">Active — already registered</option>
                        </select>
                    </div>
                </div>
                <p style="margin:2px 4px 0;color:var(--muted);font-size:12px;line-height:1.5;">
                    Setting an entry back to <strong>Inactive</strong> lets that Microsoft account register again. It is refused while a student account still exists — delete the student account first, which re-opens this entry automatically.
                </p>
                <div class="student-form-actions">
                    <button type="submit" class="btn-save"><i class="bi bi-save-fill"></i> Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    function openAddModal()  { document.getElementById('addModal').classList.add('show'); }
    function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
    function closeEditModal(){ document.getElementById('editModal').classList.remove('show'); }

    function openEdit(row) {
        document.getElementById('editForm').action = @json(route('student-registry.index')) + '/' + row.id;
        document.getElementById('edit_student_id').value = row.student_id ?? '';
        document.getElementById('edit_ms_account').value = row.ms_account ?? '';
        document.getElementById('edit_status').value = row.status ?? 'inactive';
        document.getElementById('editModal').classList.add('show');
    }

    document.querySelectorAll('.modal-overlay').forEach(overlay =>
        overlay.addEventListener('click', event => {
            if (event.target === overlay) overlay.classList.remove('show');
        })
    );
</script>
@endpush
