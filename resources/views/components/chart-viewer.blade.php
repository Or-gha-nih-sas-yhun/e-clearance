<div class="chart-viewer-overlay" id="dashboardChartViewer" role="dialog" aria-modal="true" aria-labelledby="dashboardChartViewerTitle" aria-describedby="dashboardChartViewerDescription" aria-hidden="true" hidden>
    <button class="chart-viewer-backdrop" type="button" data-chart-viewer-close aria-label="Close expanded graph" tabindex="-1"></button>
    <section class="chart-viewer-panel" role="document">
        <header class="chart-viewer-header">
            <div class="chart-viewer-heading">
                <span class="chart-viewer-icon"><i class="bi bi-bar-chart-line" data-chart-viewer-icon aria-hidden="true"></i></span>
                <div>
                    <span class="chart-viewer-eyebrow">Expanded graph</span>
                    <h2 id="dashboardChartViewerTitle">Graph details</h2>
                    <p id="dashboardChartViewerDescription">View the complete graph and its current values.</p>
                </div>
            </div>
            <button class="chart-viewer-close" type="button" data-chart-viewer-close aria-label="Close expanded graph">
                <i class="bi bi-x-lg" aria-hidden="true"></i><span>Close</span>
            </button>
        </header>
        <div class="chart-viewer-body">
            <div class="chart-viewer-content" data-chart-viewer-content></div>
        </div>
    </section>
</div>
