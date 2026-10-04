@extends('instructor.layouts.instructor')
@section('title', 'Clearance')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}?v={{ filemtime(public_path('css/clearance_workspace.css')) }}" rel="stylesheet">@endpush

@section('content')
<div class="clearance-workspace">
    @include('partials.clearance-summary', ['pendingCount' => (int) ($stats->pending ?? 0), 'approvedCount' => (int) ($stats->approved ?? 0), 'totalStudents' => (int) ($stats->total_students ?? 0), 'totalCaption' => 'Students under your subjects'])

    <section class="clearance-assignment-panel">
        <div class="clearance-assignment-heading">
            <div><h3>My Subject Assignments</h3><p>Subjects and class sections currently assigned to you.</p></div>
            <div class="clearance-assignment-heading-actions">
                <span>{{ $subjects->count() }} {{ \Illuminate\Support\Str::plural('assignment', $subjects->count()) }}</span>
                <button id="assignmentPanelToggle" type="button" aria-expanded="true" aria-controls="assignmentPanelContent">
                    <i class="bi bi-chevron-up"></i><span>Hide</span>
                </button>
            </div>
        </div>
        <div class="clearance-assignment-grid" id="assignmentPanelContent">
            @forelse($subjects as $assignment)
                <article class="clearance-assignment-item">
                    <div class="clearance-assignment-top">
                        <span class="clearance-assignment-icon"><i class="bi bi-journal-bookmark-fill"></i></span>
                        <div>
                            <strong>{{ $assignment->subject->subject_code ?? 'Subject' }}</strong>
                            <p>{{ $assignment->subject->subject_description ?: 'No subject description' }}</p>
                        </div>
                    </div>
                    <div class="clearance-assignment-details">
                        <span><i class="bi bi-mortarboard"></i>{{ $assignment->program }}</span>
                        <span><i class="bi bi-layers"></i>Year {{ $assignment->year_level }}</span>
                        <span><i class="bi bi-people"></i>{{ $assignment->section }}</span>
                        @if($assignment->subject?->semester)<span><i class="bi bi-calendar3"></i>{{ $assignment->subject->semester }}</span>@endif
                    </div>
                    <div class="clearance-assignment-footer">
                        <span><strong>{{ number_format($assignment->student_count) }}</strong> {{ \Illuminate\Support\Str::plural('student', $assignment->student_count) }}</span>
                        <a href="{{ route('instructor.clearance', ['program' => $assignment->program, 'year_level' => $assignment->year_level, 'section' => $assignment->section]) }}">View students <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>
            @empty
                <div class="clearance-assignment-empty"><i class="bi bi-journal-x"></i><span>No subject assignments have been assigned to your account yet.</span></div>
            @endforelse
        </div>
    </section>

    @include('partials.clearance-filters', ['action' => route('instructor.clearance'), 'programs' => $optPrograms, 'years' => $optYears, 'sections' => $optSections])
    <section class="clearance-table-card">
        <div class="clearance-table-heading"><h3>Student Clearance Requests</h3><span>{{ $students->total() }} records</span></div>
        @include('partials.clearance-bulk-toolbar', ['endpoint' => route('instructor.clearance.bulk'), 'itemType' => 'subject'])
        <div class="clearance-table-wrap">
    <table class="clearance-table">
        <thead>
            <tr>
                <th class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select-all aria-label="Select all clearance records on this page"></th>
                <th>Student</th>
                <th>Program</th>
                <th>Year / Section</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Remarks</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $row)
            <tr>
                <td class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select data-student="{{ $row->student_id }}" data-subject="{{ $row->subject_id }}" aria-label="Select {{ $row->firstname }} {{ $row->lastname }} for {{ $row->subject_code }}"></td>
                <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($row->firstname,0,1)) }}</span><div><strong>{{ $row->firstname }} {{ $row->lastname }}</strong><small>{{ $row->student_id }}</small></div></div></td>
                <td>{{ $row->program }}</td>
                <td>{{ $row->year_level }} - {{ $row->assigned_section }}</td>
                <td>{{ $row->subject_code }}</td>
                <td>
                    <span class="clearance-status {{ $row->clearance_status === 'Approved' ? 'approved' : 'pending' }}">{{ $row->clearance_status === 'Approved' ? 'Approved' : 'Pending' }}</span>
                </td>
                <td>{{ $row->remarks }}</td>
                <td>
                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            class="clearance-action approve"
                            type="button"
                            onclick="setClearance('{{ $row->student_id }}', {{ $row->subject_id }}, 'Approved')"
                            {{ $row->clearance_status === 'Approved' ? 'disabled' : '' }}
                        >
                            <i class="bi bi-check-lg"></i> Approve
                        </button>
                        <button
                            class="clearance-action pending"
                            type="button"
                            onclick="setClearance('{{ $row->student_id }}', {{ $row->subject_id }}, 'Pending')"
                            {{ $row->clearance_status !== 'Approved' ? 'disabled' : '' }}
                        >
                            <i class="bi bi-clock"></i> Pending
                        </button>
                        <button class="clearance-action secondary" type="button" onclick="openRemarkModal('{{ $row->student_id }}', {{ $row->subject_id }})">
                            <i class="bi bi-chat-left-text"></i> Remark
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
        <div class="clearance-pagination"><span>Showing {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} of {{ $students->total() }} records</span>{{ $students->links() }}</div>
    </section>
</div>

<div id="remarkModal" style="position:fixed;inset:0;background:rgba(15,23,42,.7);display:none;align-items:center;justify-content:center;z-index:2147483647;padding:1.25rem;">
    <div style="width:min(520px,100%);background:#fff;border-radius:1rem;box-shadow:0 24px 60px rgba(15,23,42,.25);overflow:hidden;">
        <div style="padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;">
            <div style="font-weight:700;font-size:1.05rem;">Send Remark</div>
            <button type="button" style="border:none;background:#f1f5f9;color:#334155;border-radius:999px;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;" onclick="closeRemarkModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div style="padding:1rem 1.25rem;">
            <textarea id="remarkText" rows="4" style="width:100%;border:1px solid #cbd5e1;border-radius:.75rem;padding:.75rem .9rem;resize:vertical;min-height:120px;" placeholder="Write a remark for this student..."></textarea>
        </div>
        <div style="padding:1rem 1.25rem;display:flex;justify-content:flex-end;border-top:1px solid #e2e8f0;">
            <button type="button" class="esig-btn esig-btn-primary" onclick="submitRemark()">Send</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
let remarkTarget = { student: null, subject: null };

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('assignmentPanelToggle');
    const content = document.getElementById('assignmentPanelContent');
    if (!toggle || !content) return;

    let animationTimer = null;
    const applyAssignmentPanelState = (collapsed, animate = true) => {
        window.clearTimeout(animationTimer);
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.querySelector('span').textContent = collapsed ? 'Show' : 'Hide';
        toggle.querySelector('i').className = collapsed ? 'bi bi-chevron-down' : 'bi bi-chevron-up';

        if (!animate) {
            content.style.transition = 'none';
            content.classList.toggle('is-collapsed', collapsed);
            content.style.maxHeight = collapsed ? '0px' : 'none';
            window.requestAnimationFrame(() => { content.style.transition = ''; });
            return;
        }

        if (collapsed) {
            content.style.maxHeight = `${content.scrollHeight}px`;
            content.offsetHeight;
            content.classList.add('is-collapsed');
            content.style.maxHeight = '0px';
        } else {
            content.classList.remove('is-collapsed');
            content.style.maxHeight = '0px';
            content.offsetHeight;
            content.style.maxHeight = `${content.scrollHeight}px`;
            animationTimer = window.setTimeout(() => {
                if (!content.classList.contains('is-collapsed')) content.style.maxHeight = 'none';
            }, 520);
            window.setTimeout(() => {
                const scroller = content.closest('.main');
                if (!scroller) {
                    content.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }

                const contentBottom = content.getBoundingClientRect().bottom;
                const scrollerBottom = scroller.getBoundingClientRect().bottom;
                if (contentBottom > scrollerBottom) {
                    scroller.scrollTo({
                        top: scroller.scrollTop + (contentBottom - scrollerBottom) + 18,
                        behavior: 'smooth',
                    });
                }
            }, 110);
        }
    };

    applyAssignmentPanelState(localStorage.getItem('instructorAssignmentPanel') === 'hidden', false);
    toggle.addEventListener('click', () => {
        const collapsed = !content.classList.contains('is-collapsed');
        applyAssignmentPanelState(collapsed);
        localStorage.setItem('instructorAssignmentPanel', collapsed ? 'hidden' : 'shown');
    });
});

function openRemarkModal(student, subject) {
    remarkTarget = { student, subject };
    const remarkInput = document.getElementById('remarkText');
    const modal = document.getElementById('remarkModal');
    remarkInput.value = '';
    modal.style.display = 'flex';
    setTimeout(() => remarkInput.focus(), 50);
}
function closeRemarkModal() {
    document.getElementById('remarkModal').style.display = 'none';
}

async function submitRemark() {
    const remarkInput = document.getElementById('remarkText');
    const remark = remarkInput.value.trim();
    if (!remark) return;
    const res = await apiFetch(@json(route('instructor.remarks.send')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id: remarkTarget.student, subject_id: remarkTarget.subject, remark }),
    });
    const data = await res.json();
    if (data.success) {
        closeRemarkModal();
        await window.showSuccessModal(data.message || 'Remark sent successfully.');
        location.reload();
    }
}

async function setClearance(student, subject, status) {
    const url = status === 'Approved'
        ? @json(route('instructor.clearance.approve'))
        : @json(route('instructor.clearance.pending'));
    const res = await apiFetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id: student, subject_id: subject }),
    });
    const data = await res.json();
    if (data.success) {
        await window.showSuccessModal(data.message || `Clearance marked as ${status}.`);
        location.reload();
    }
}
</script>
@endpush
@endsection
