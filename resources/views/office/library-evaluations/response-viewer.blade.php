<div class="evaluation-response-overlay" id="evaluationResponseViewer" data-form-title="{{ $selected->title }}" role="dialog" aria-modal="true" aria-labelledby="evaluationResponseViewerTitle" aria-hidden="true" hidden>
    <button class="evaluation-response-backdrop" type="button" data-response-close aria-label="Close evaluation overlay" tabindex="-1"></button>
    <section class="evaluation-response-panel" role="document">
        <header class="evaluation-response-header">
            <div class="evaluation-response-heading">
                <span class="evaluation-response-icon"><i class="bi bi-person-check"></i></span>
                <div><h2 id="evaluationResponseViewerTitle">Evaluation response</h2><p id="evaluationResponseViewerSubtitle">Student answers</p></div>
            </div>
            <button class="evaluation-response-close" type="button" data-response-close aria-label="Close evaluation overlay"><i class="bi bi-x-lg"></i><span>Close</span></button>
        </header>
        <div class="evaluation-response-body">
            <div class="evaluation-response-loading" id="evaluationResponseViewerLoading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading response...</div>
            <div id="evaluationResponseViewerContent" hidden></div>
            <div class="evaluation-response-error" id="evaluationResponseViewerError" hidden><p>Could not load this response.</p><a class="evaluation-button secondary small" id="evaluationResponseViewerLink" href="#">Open response page</a></div>
        </div>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const viewer = document.getElementById('evaluationResponseViewer');
    if (!viewer) return;
    const closeButton = viewer.querySelector('.evaluation-response-close');
    const title = document.getElementById('evaluationResponseViewerTitle');
    const subtitle = document.getElementById('evaluationResponseViewerSubtitle');
    const loading = document.getElementById('evaluationResponseViewerLoading');
    const content = document.getElementById('evaluationResponseViewerContent');
    const error = document.getElementById('evaluationResponseViewerError');
    const fallback = document.getElementById('evaluationResponseViewerLink');
    const icon = viewer.querySelector('.evaluation-response-icon i');
    let activeTrigger = null;
    let request = null;
    let closeTimer = null;

    const openViewer = (trigger) => {
        window.clearTimeout(closeTimer);
        request?.abort();
        request = new AbortController();
        const currentRequest = request;
        activeTrigger = trigger;
        const row = trigger.closest('tr');
        const studentName = row?.querySelector('td strong')?.textContent?.trim() || 'Student response';
        const studentId = row?.querySelector('td small')?.textContent?.trim();
        title.textContent = studentName;
        subtitle.textContent = [studentId, viewer.dataset.formTitle].filter(Boolean).join(' · ');
        content.replaceChildren();
        content.hidden = true;
        error.hidden = true;
        loading.hidden = false;
        viewer.hidden = false;
        viewer.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(() => {
            viewer.classList.add('is-open');
            closeButton.focus();
        });

        const chart = trigger.dataset.evaluationChart;
        const form = trigger.hasAttribute('data-evaluation-form');
        icon.className = `bi ${form ? 'bi-card-checklist' : chart ? trigger.dataset.chartIcon : 'bi-person-check'}`;
        if (form) {
            title.textContent = viewer.dataset.formTitle;
            subtitle.textContent = 'Evaluation form';
            content.append(document.getElementById('evaluation-form-preview').content.cloneNode(true));
            content.hidden = false;
            loading.hidden = true;
            return;
        }
        if (chart) {
            title.textContent = trigger.dataset.chartTitle;
            subtitle.textContent = viewer.dataset.formTitle;
            content.append(document.getElementById(`evaluation-chart-${chart}`).content.cloneNode(true));
            content.hidden = false;
            loading.hidden = true;
            return;
        }
        fallback.href = trigger.href;

        fetch(trigger.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: currentRequest.signal,
        }).then((response) => {
            if (!response.ok || response.redirected) throw new Error('Response unavailable');
            return response.text();
        }).then((html) => {
            if (viewer.hidden || request !== currentRequest) return;
            content.innerHTML = html;
            content.hidden = false;
            loading.hidden = true;
        }).catch((failure) => {
            if (failure.name === 'AbortError' || viewer.hidden || request !== currentRequest) return;
            loading.hidden = true;
            error.hidden = false;
        });
    };

    const closeViewer = () => {
        if (viewer.hidden) return;
        request?.abort();
        viewer.classList.remove('is-open');
        viewer.setAttribute('aria-hidden', 'true');
        closeTimer = window.setTimeout(() => {
            viewer.hidden = true;
            content.replaceChildren();
        }, 180);
        activeTrigger?.focus();
    };

    document.querySelectorAll('.evaluation-table-wrap a.evaluation-button[href], [data-evaluation-chart], [data-evaluation-form]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openViewer(trigger);
        });
    });
    viewer.querySelectorAll('[data-response-close]').forEach((control) => control.addEventListener('click', closeViewer));
    document.addEventListener('keydown', (event) => {
        if (viewer.hidden || document.getElementById('feedbackModalOverlay')?.classList.contains('show')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeViewer();
        }
        if (event.key === 'Tab') {
            const focusable = [closeButton, ...content.querySelectorAll('a[href], button:not([disabled])'), ...(error.hidden ? [] : [fallback])];
            const index = focusable.indexOf(document.activeElement);
            if (event.shiftKey && index <= 0) {
                event.preventDefault();
                focusable.at(-1).focus();
            } else if (!event.shiftKey && (index === -1 || index === focusable.length - 1)) {
                event.preventDefault();
                focusable[0].focus();
            }
        }
    }, true);
})();
</script>
@endpush
