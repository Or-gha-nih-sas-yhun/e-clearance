<div class="evaluation-note success mb-3">
    <strong>Evaluation completed</strong>
    <p>{{ \Carbon\Carbon::parse($response->completed_at)->format('M d, Y h:i A') }}</p>
</div>
@php($ratings = json_decode($response->ratings, true))
@include('partials.library-evaluation-questions')
