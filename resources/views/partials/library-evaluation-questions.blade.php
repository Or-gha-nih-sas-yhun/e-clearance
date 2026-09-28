<div class="evaluation-question-sets">
    @foreach(\App\Support\LibraryEvaluation::sections($evaluation) as $section)
        <section class="evaluation-question-set">
            @include('partials.library-evaluation-section-heading')
            <ol class="evaluation-question-list" start="{{ $section['offset'] + 1 }}" style="counter-reset: evaluation-question {{ $section['offset'] }}">
                @foreach($section['questions'] as $index => $question)
                    <li><div>{{ $question }}
                        @if(isset($ratings))<br><span class="evaluation-answer">{{ $ratings[$index] }} &mdash; {{ \App\Support\LibraryEvaluation::RATINGS[$ratings[$index]] }}</span>@endif
                    </div></li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>
