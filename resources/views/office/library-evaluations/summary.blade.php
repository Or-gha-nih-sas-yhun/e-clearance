<section class="evaluation-summary" aria-label="Evaluation summary">
    @foreach([
        ['key' => 'completion', 'label' => 'Total students', 'value' => number_format($summary['total']), 'icon' => 'bi-people', 'tone' => 'blue', 'hint' => 'View completion chart', 'title' => 'Student completion'],
        ['key' => 'ratings', 'label' => 'Completed', 'value' => number_format($summary['completed']), 'icon' => 'bi-check-circle', 'tone' => 'green', 'hint' => 'View rating distribution', 'title' => 'Rating distribution'],
        ['key' => 'pending', 'label' => 'Not completed', 'value' => number_format($summary['pending']), 'icon' => 'bi-clock', 'tone' => 'amber', 'hint' => 'View pending by program', 'title' => 'Pending evaluations by program'],
        ['key' => 'averages', 'label' => 'Average rating', 'value' => $summary['average'] === null ? '—' : number_format($summary['average'], 2).' / 5', 'icon' => 'bi-star', 'tone' => 'blue', 'hint' => 'View averages by question', 'title' => 'Average rating by question'],
    ] as $card)
        <button type="button" class="evaluation-panel evaluation-summary-card {{ $card['tone'] }}" data-evaluation-chart="{{ $card['key'] }}" data-chart-title="{{ $card['title'] }}" data-chart-icon="{{ $card['icon'] }}" aria-haspopup="dialog" aria-controls="evaluationResponseViewer">
            <span class="evaluation-summary-top"><span class="evaluation-summary-label">{{ $card['label'] }}</span><i class="bi {{ $card['icon'] }}" aria-hidden="true"></i></span>
            <strong class="evaluation-summary-value">{{ $card['value'] }}</strong>
            <span class="evaluation-summary-link">{{ $card['hint'] }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
        </button>
    @endforeach
</section>
