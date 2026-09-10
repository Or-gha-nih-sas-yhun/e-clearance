@props([
    'id' => 'clearanceDocumentViewer',
    'title' => 'Official Clearance Form',
    'subtitle' => 'Clearance document',
    'downloadUrl' => null,
])

{{--
    A clearance form opened in place, as a modal iframe.

    Any number of triggers on the page can drive one viewer. A trigger carries
    `data-clearance-form-open` with the embedded form URL in
    `data-clearance-form-src`, and optionally `data-clearance-form-title` and
    `data-clearance-form-subtitle` to caption it — which is what lets the
    registrar's table reuse a single viewer across every student in the list.
--}}
<div class="clearance-document-overlay" id="{{ $id }}" aria-hidden="true">
    <button class="clearance-document-backdrop" type="button" data-clearance-form-close aria-label="Close clearance form"></button>
    <section class="clearance-document-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <header class="clearance-document-header">
            <div class="clearance-document-title">
                <i class="bi bi-file-earmark-check"></i>
                <div>
                    <h3 id="{{ $id }}-title" data-clearance-form-heading>{{ $title }}</h3>
                    <p data-clearance-form-subheading>{{ $subtitle }}</p>
                </div>
            </div>
            <div class="clearance-document-controls">
                @if($downloadUrl)
                <a class="clearance-document-download" href="{{ $downloadUrl }}"><i class="bi bi-download"></i>Download PDF</a>
                @endif
                <button class="clearance-document-print" type="button" data-clearance-form-print><i class="bi bi-printer"></i>Print / Save PDF</button>
                <button class="clearance-document-close" type="button" data-clearance-form-close aria-label="Close clearance form"><i class="bi bi-x-lg"></i></button>
            </div>
        </header>
        <div class="clearance-document-frame-wrap">
            <div class="clearance-document-loader"><div><i class="bi bi-hourglass-split"></i>Loading official clearance form…</div></div>
            <iframe class="clearance-document-frame" title="{{ $title }}" src="about:blank"></iframe>
        </div>
    </section>
</div>

@once
@push('scripts')
<script>
(() => {
    const viewers = document.querySelectorAll('.clearance-document-overlay');
    if (!viewers.length) return;

    let openViewer = null;
    let lastTrigger = null;

    const close = () => {
        if (!openViewer) return;
        openViewer.classList.remove('show');
        openViewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('clearance-form-viewer-open');
        openViewer = null;
        const trigger = lastTrigger;
        lastTrigger = null;
        if (trigger) window.setTimeout(() => trigger.focus(), 160);
    };

    // Delegated, so triggers rendered per table row all work without binding
    // one listener each.
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-clearance-form-open]');
        if (trigger) {
            const viewer = document.getElementById(trigger.dataset.clearanceFormViewer || '{{ $id }}');
            const frame = viewer?.querySelector('.clearance-document-frame');
            const frameWrap = viewer?.querySelector('.clearance-document-frame-wrap');
            if (!viewer || !frame) return;

            lastTrigger = trigger;
            viewer.querySelector('[data-clearance-form-heading]').textContent =
                trigger.dataset.clearanceFormTitle || viewer.querySelector('[data-clearance-form-heading]').textContent;
            if (trigger.dataset.clearanceFormSubtitle) {
                viewer.querySelector('[data-clearance-form-subheading]').textContent = trigger.dataset.clearanceFormSubtitle;
            }

            // One viewer serves many students, so a different document always
            // reloads rather than showing whoever was opened last.
            const source = trigger.dataset.clearanceFormSrc;
            if (frame.getAttribute('data-loaded-src') === source) {
                frameWrap?.classList.add('loaded');
            } else {
                frameWrap?.classList.remove('loaded');
                frame.setAttribute('data-loaded-src', source);
                frame.src = source;
            }

            openViewer = viewer;
            viewer.classList.add('show');
            viewer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('clearance-form-viewer-open');
            window.setTimeout(() => viewer.querySelector('.clearance-document-close')?.focus(), 0);
            return;
        }

        if (event.target.closest('[data-clearance-form-close]')) {
            close();
            return;
        }

        const print = event.target.closest('[data-clearance-form-print]');
        if (print) {
            const frame = print.closest('.clearance-document-overlay')?.querySelector('.clearance-document-frame');
            frame?.contentWindow?.focus();
            frame?.contentWindow?.print();
        }
    });

    viewers.forEach(viewer => {
        viewer.querySelector('.clearance-document-frame')?.addEventListener('load', () => {
            viewer.querySelector('.clearance-document-frame-wrap')?.classList.add('loaded');
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && openViewer) close();
    });
})();
</script>
@endpush
@endonce
