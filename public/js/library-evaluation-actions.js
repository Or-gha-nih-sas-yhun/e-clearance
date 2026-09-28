(() => {
    const values = form => JSON.stringify([...new FormData(form)].filter(([name]) =>
        ['title', 'description', 'questions[]'].includes(name) || name.startsWith('sections[')));

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-evaluation-confirm]')) return;
        const original = form.dataset.originalValues ? JSON.stringify(JSON.parse(form.dataset.originalValues)) : values(form);
        if (form.dataset.evaluationConfirmed === 'true' || (form.hasAttribute('data-confirm-only-changes') && values(form) === original)) return;
        event.preventDefault();
        if (form.dataset.evaluationConfirming === 'true') return;
        form.dataset.evaluationConfirming = 'true';
        const accepted = typeof window.showConfirmationModal === 'function'
            ? await window.showConfirmationModal({
                title: form.dataset.confirmTitle,
                message: form.dataset.confirmMessage,
                confirmText: form.dataset.confirmButton,
                tone: 'danger',
            })
            : window.confirm(form.dataset.confirmMessage);
        delete form.dataset.evaluationConfirming;
        if (accepted) {
            form.dataset.evaluationConfirmed = 'true';
            form.requestSubmit(event.submitter || undefined);
            delete form.dataset.evaluationConfirmed;
        }
    });
})();
