<div class="evaluation-scale" aria-label="Evaluation rating scale">
    @foreach(\App\Support\LibraryEvaluation::RATINGS as $rating => $label)
        <span class="evaluation-scale-item"><strong>{{ $rating }}</strong> {{ $label }}</span>
    @endforeach
</div>
