<template id="evaluation-chart-completion">
    <p class="evaluation-chart-caption">Completion for all {{ number_format($summary['total']) }} students. Table filters do not change these totals.</p>
    @if($summary['total'])
        <div class="evaluation-completion-chart">
            <div class="evaluation-donut" style="--completion: {{ $summary['completionPercent'] }}%" role="img" aria-label="{{ $summary['completionPercent'] }} percent completed, {{ $summary['completed'] }} of {{ $summary['total'] }} students">
                <div><strong>{{ $summary['completionPercent'] }}%</strong><span>completed</span></div>
            </div>
            <dl class="evaluation-chart-legend">
                <div><dt><span class="evaluation-chart-dot complete"></span>Completed</dt><dd>{{ number_format($summary['completed']) }}</dd></div>
                <div><dt><span class="evaluation-chart-dot pending"></span>Not completed</dt><dd>{{ number_format($summary['pending']) }}</dd></div>
            </dl>
        </div>
    @else
        <div class="evaluation-note"><strong>No students yet</strong><p>The completion chart will appear when students are registered.</p></div>
    @endif
</template>
<template id="evaluation-chart-ratings">
    <p class="evaluation-chart-caption">{{ number_format($summary['answerCount']) }} answers across {{ number_format($summary['completed']) }} completed evaluations. Each answer counts once.</p>
    @if(!$summary['answerCount'])<div class="evaluation-note mb-3"><strong>No responses yet</strong><p>Ratings will appear as students submit their evaluations.</p></div>@endif
    <div class="evaluation-chart-bars">
        @foreach(\App\Support\LibraryEvaluation::RATINGS as $rating => $label)
            @php($percent = $summary['answerCount'] ? round($summary['ratingCounts'][$rating] / $summary['answerCount'] * 100, 1) : 0)
            <div class="evaluation-chart-row">
                <div class="evaluation-chart-row-label"><strong>{{ $rating }} — {{ $label }}</strong><span>{{ $summary['ratingCounts'][$rating] }} answers · {{ $percent }}%</span></div>
                <div class="evaluation-chart-track" aria-hidden="true"><span class="rating-{{ $rating }}" style="width: {{ $percent }}%"></span></div>
            </div>
        @endforeach
    </div>
</template>
<template id="evaluation-chart-pending">
    <p class="evaluation-chart-caption">{{ number_format($summary['pending']) }} students have not completed this evaluation. Each bar shows the pending share of its program.</p>
    <div class="evaluation-chart-bars">
        @forelse($summary['programs'] as $program)
            <div class="evaluation-chart-row">
                <div class="evaluation-chart-row-label"><strong>{{ $program['name'] }}</strong><span>{{ $program['pending'] }} of {{ $program['total'] }} pending</span></div>
                <div class="evaluation-chart-track" aria-hidden="true"><span class="pending" style="width: {{ round($program['pending'] / $program['total'] * 100, 1) }}%"></span></div>
            </div>
        @empty
            <div class="evaluation-note"><strong>No students yet</strong><p>Programs will appear when students are registered.</p></div>
        @endforelse
    </div>
    @if($summary['total'] && !$summary['pending'])<div class="evaluation-note success mt-3"><strong>Everyone has completed the evaluation.</strong></div>@endif
</template>
<template id="evaluation-chart-averages">
    <p class="evaluation-chart-caption">Average score for each question, out of 5. Only submitted evaluations are included.</p>
    @if(!$summary['answerCount'])<div class="evaluation-note mb-3"><strong>No responses yet</strong><p>Question averages will appear after the first submission.</p></div>@endif
    <div class="evaluation-chart-bars">
        @foreach(\App\Support\LibraryEvaluation::sections($selected) as $section)
            @include('partials.library-evaluation-section-heading')
            @foreach($section['questions'] as $index => $question)
                @php($average = $summary['questions'][$index]['average'])
                <div class="evaluation-chart-row">
                    <div class="evaluation-chart-row-label"><strong>Q{{ $index + 1 }}. {{ $question }}</strong><span>{{ $average === null ? 'No answers' : number_format($average, 2).' / 5' }}</span></div>
                    <div class="evaluation-chart-track" aria-hidden="true"><span style="width: {{ $average === null ? 0 : $average * 20 }}%"></span></div>
                </div>
            @endforeach
        @endforeach
    </div>
</template>
