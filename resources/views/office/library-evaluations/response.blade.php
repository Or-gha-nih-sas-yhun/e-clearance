@extends('office.library-evaluations.layout')
@section('evaluation-content')
<section class="evaluation-panel">
    <div class="evaluation-panel-head">
        <div class="evaluation-panel-title"><span class="evaluation-panel-icon"><i class="bi bi-person-check"></i></span><div><span class="evaluation-eyebrow">Student response</span><h2>{{ $student->firstname }} {{ $student->lastname }}</h2><p>{{ $student->student_id }} &middot; {{ $evaluation->title }}</p></div></div>
        <span class="evaluation-status complete"><i class="bi bi-check-circle"></i>Completed</span>
    </div>
    <div class="evaluation-panel-body">
        @include('office.library-evaluations.response-answers')
        <div class="evaluation-actions mt-3"><a class="evaluation-button secondary" href="{{ route($officeRouteBase.'.index', ['evaluation' => $evaluation->id]) }}"><i class="bi bi-arrow-left"></i>Back to student completion</a></div>
    </div>
</section>
@endsection
