(() => {
    'use strict';

    function initialiseChartViewer() {
        const viewer = document.getElementById('dashboardChartViewer');
        const cards = Array.from(document.querySelectorAll('[data-chart-card]'));
        if (!viewer || !cards.length) return;

        const title = document.getElementById('dashboardChartViewerTitle');
        const description = document.getElementById('dashboardChartViewerDescription');
        const content = viewer.querySelector('[data-chart-viewer-content]');
        const icon = viewer.querySelector('[data-chart-viewer-icon]');
        const closeButton = viewer.querySelector('.chart-viewer-close');
        let activeCard = null;
        let closeTimer = null;

        function cardTitle(card) {
            return card.dataset.chartTitle
                || card.querySelector('h2, h3, h5')?.textContent?.trim()
                || 'Graph details';
        }

        function copyCanvasImages(source, clone) {
            const originals = source.matches('canvas') ? [source] : Array.from(source.querySelectorAll('canvas'));
            const copies = clone.matches('canvas') ? [clone] : Array.from(clone.querySelectorAll('canvas'));

            originals.forEach((canvas, index) => {
                const copy = copies[index];
                if (!copy) return;
                const width = Math.max(canvas.width, canvas.clientWidth || 0);
                const height = Math.max(canvas.height, canvas.clientHeight || 0);
                copy.width = width;
                copy.height = height;
                const context = copy.getContext('2d');
                if (context) context.drawImage(canvas, 0, 0, width, height);
            });
        }

        function openViewer(card) {
            window.clearTimeout(closeTimer);
            const template = card.dataset.chartTemplate
                ? document.getElementById(card.dataset.chartTemplate)
                : null;
            const selector = card.dataset.chartContent;
            const source = selector ? card.querySelector(selector) : card.querySelector('[data-chart-content]');
            const visual = template instanceof HTMLTemplateElement
                ? template.content.firstElementChild
                : (source || card);
            if (!visual) return;
            const clone = visual.cloneNode(true);

            clone.removeAttribute('id');
            clone.removeAttribute('data-chart-content');
            clone.removeAttribute('data-chart-card');
            clone.removeAttribute('role');
            clone.removeAttribute('tabindex');
            clone.classList.add('chart-viewer-copy');

            content.replaceChildren(clone);
            copyCanvasImages(visual, clone);
            title.textContent = cardTitle(card);
            description.textContent = card.dataset.chartDescription || 'View the complete graph and its current values.';
            icon.className = `bi ${card.dataset.chartIcon || 'bi-bar-chart-line'}`;

            activeCard = card;
            viewer.hidden = false;
            viewer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('chart-viewer-open');
            window.requestAnimationFrame(() => {
                viewer.classList.add('is-open');
                closeButton.focus({ preventScroll: true });
            });
        }

        function closeViewer() {
            if (viewer.hidden || !viewer.classList.contains('is-open')) return;
            window.clearTimeout(closeTimer);
            viewer.classList.remove('is-open');
            viewer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('chart-viewer-open');
            closeTimer = window.setTimeout(() => {
                viewer.hidden = true;
                content.replaceChildren();
                activeCard?.focus({ preventScroll: true });
                activeCard = null;
            }, 190);
        }

        cards.forEach(card => {
            const heading = cardTitle(card);
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-haspopup', 'dialog');
            card.setAttribute('aria-controls', viewer.id);
            card.setAttribute('aria-label', `Open ${heading} in expanded view`);

            card.addEventListener('click', event => {
                if (event.target.closest('a, button, input, select, textarea, summary, [contenteditable="true"]')) return;
                openViewer(card);
            });
            card.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                openViewer(card);
            });
        });

        viewer.querySelectorAll('[data-chart-viewer-close]').forEach(button => button.addEventListener('click', closeViewer));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !viewer.hidden) closeViewer();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseChartViewer, { once: true });
    } else {
        initialiseChartViewer();
    }
})();
