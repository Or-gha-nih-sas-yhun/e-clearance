<div class="clearance-bulk-toolbar" data-clearance-bulk data-endpoint="{{ $endpoint }}" data-item-type="{{ $itemType ?? 'student' }}">
    <div class="clearance-bulk-selection">
        <span class="clearance-bulk-icon"><i class="bi bi-ui-checks-grid"></i></span>
        <div><strong><span data-bulk-count>0</span> selected</strong><small>Select records from this page</small></div>
    </div>
    <div class="clearance-bulk-actions">
        <button type="button" class="clearance-bulk-button approve" data-bulk-status="Approved" disabled><i class="bi bi-check2-circle"></i> Approve Selected</button>
        <button type="button" class="clearance-bulk-button pending" data-bulk-status="Pending" disabled><i class="bi bi-clock-history"></i> Set as Pending</button>
        <button type="button" class="clearance-bulk-button clear" data-bulk-clear disabled><i class="bi bi-x-lg"></i> Clear</button>
    </div>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-clearance-bulk]').forEach(toolbar => {
            const card = toolbar.closest('.clearance-table-card');
            const selectAll = card.querySelector('[data-bulk-select-all]');
            const checkboxes = [...card.querySelectorAll('[data-bulk-select]')];
            const count = toolbar.querySelector('[data-bulk-count]');
            const actionButtons = [...toolbar.querySelectorAll('[data-bulk-status]')];
            const clearButton = toolbar.querySelector('[data-bulk-clear]');

            const refresh = () => {
                const selected = checkboxes.filter(checkbox => checkbox.checked);
                count.textContent = selected.length;
                actionButtons.forEach(button => button.disabled = selected.length === 0);
                clearButton.disabled = selected.length === 0;
                checkboxes.forEach(checkbox => checkbox.closest('tr')?.classList.toggle('is-selected', checkbox.checked));
                if (selectAll) {
                    selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
                    selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
                }
                return selected;
            };

            selectAll?.addEventListener('change', () => {
                checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
                refresh();
            });
            checkboxes.forEach(checkbox => checkbox.addEventListener('change', refresh));
            clearButton.addEventListener('click', () => {
                checkboxes.forEach(checkbox => checkbox.checked = false);
                refresh();
            });

            actionButtons.forEach(button => button.addEventListener('click', async () => {
                const selected = refresh();
                if (!selected.length) return;
                const status = button.dataset.bulkStatus;
                const verb = status === 'Approved' ? 'approve' : 'return to pending';
                const confirmed = await window.showConfirmationModal({
                    title: status === 'Approved' ? 'Approve Selected Clearances?' : 'Set Selected as Pending?',
                    message: `You are about to ${verb} ${selected.length} selected clearance ${selected.length === 1 ? 'record' : 'records'}.`,
                    confirmText: status === 'Approved' ? 'Yes, Approve All' : 'Yes, Set Pending',
                    tone: status === 'Approved' ? 'success' : 'warning',
                });
                if (!confirmed) return;

                const subjectItems = selected.map(checkbox => ({
                    student: checkbox.dataset.student,
                    subject: Number(checkbox.dataset.subject),
                }));
                const payload = toolbar.dataset.itemType === 'subject'
                    ? { items: subjectItems, status }
                    : { student_ids: selected.map(checkbox => checkbox.dataset.student), status };

                actionButtons.forEach(action => action.disabled = true);
                clearButton.disabled = true;
                try {
                    const response = await fetch(toolbar.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        const validationMessages = data.errors ? Object.values(data.errors).flat().join('\n') : null;
                        await window.showFeedbackModal({ title: 'Bulk Update Failed', message: validationMessages || data.message || 'The selected clearances could not be updated.', tone: 'danger' });
                        refresh();
                        return;
                    }
                    const title = data.title || (status === 'Approved' ? 'Clearances Approved' : 'Clearances Set to Pending');
                    await window.showSuccessModal(data.message || `${data.updated} clearance records updated successfully.`, title);
                    window.location.reload();
                } catch (error) {
                    await window.showFeedbackModal({ title: 'Bulk Update Failed', message: 'Unable to update the selected clearances right now. Please try again.', tone: 'danger' });
                    refresh();
                }
            }));

            refresh();
        });
    });
    </script>
    @endpush
@endonce
