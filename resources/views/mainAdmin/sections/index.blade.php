@extends('mainAdmin.layouts.admin')
@section('title', 'Sections — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Sections"
    description="Create and maintain section groupings by program and year level."
    icon="bi bi-grid-3x3-gap-fill"
    eyebrow="Academic setup"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Section</button>
        <button class="btn-csv" onclick="openCsvModal('sections')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('sections.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="program">
                    <option value="">All Programs</option>
                    @foreach($programs as $program)
                    <option value="{{ $program }}" {{ $filterProg === $program ? 'selected' : '' }}>{{ $program }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="year">
                    <option value="">All Years</option>
                    @foreach([1,2,3,4] as $year)
                    <option value="{{ $year }}" {{ $filterYear == $year ? 'selected' : '' }}>{{ $year }} Year</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn-filter w-100">Filter</button></div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Section Groups</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $total }} groups across {{ $progCount }} programs.</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr><th>Program</th><th>Section</th><th>Year Levels</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($filtered as $row)
                <tr>
                    <td>{{ $row['program'] }}</td>
                    <td>{{ $row['section'] }}</td>
                    <td>{{ implode(', ', $row['years']) }}</td>
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit(@json($row))'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('sections.destroy', $row['id']) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this section group?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4"><div class="empty-state">No sections found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-main-admin.table-pagination :paginator="$filtered" label="section groups" />
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-plus-circle-fill" style="color:var(--success);margin-right:8px;"></i>Add Section</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('sections.store') }}">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" required>
                            <option value="">Select program</option>
                            @foreach($programs as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Section</label><input type="text" name="section" required></div>
                </div>
                <div class="fg"><label>Year Levels <span class="field-required">*</span></label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        @foreach([1,2,3,4] as $year)
                        <label style="font-weight:500;"><input type="checkbox" name="year_levels[]" value="{{ $year }}" data-skip-modal-validation> {{ $year }}</label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-save-fill"></i> Save Section</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Section</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="e_program" required>
                            @foreach($programs as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Section</label><input type="text" name="section" id="e_section" required></div>
                </div>
                <div class="fg"><label>Year Levels <span class="field-required">*</span></label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        @foreach([1,2,3,4] as $year)
                        <label style="font-weight:500;"><input type="checkbox" name="year_levels[]" value="{{ $year }}" class="edit-year" data-skip-modal-validation> {{ $year }}</label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Update Section</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAddModal() { document.getElementById('addModal').classList.add('show'); }
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
function openEdit(row) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/sections') }}/' + row.id;
    document.getElementById('e_program').value = row.program;
    document.getElementById('e_section').value = row.section;
    document.querySelectorAll('.edit-year').forEach(cb => cb.checked = row.years.includes(Number(cb.value)));
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

document.querySelectorAll('#addModal form, #editForm').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.querySelectorAll('input[name="year_levels[]"]:checked').length > 0) return;

        event.preventDefault();
        showActionDialog({
            title: 'Select a Year Level',
            message: 'Choose at least one year level for this section.',
            confirmText: 'Okay',
            tone: 'danger',
            notice: true,
        });
    });
});
</script>
@endpush
