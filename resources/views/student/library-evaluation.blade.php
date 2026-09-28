@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', $officeLabel.' Evaluation')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', $officeLabel.' Evaluation')
@section('user-label', $student->full_name)
@section('user-role', 'Student')
@section('nav')
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark me-2"></i> My Subjects</a>
    <a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check me-2"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up me-2"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text me-2"></i> Chat Support</a>
@endsection
@section('logout-form')
    <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection
@push('styles')<link href="{{ asset('css/library_evaluation.css') }}" rel="stylesheet">@endpush
@section('content')
<div class="evaluation-workspace">
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Please answer every question.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if(! $evaluation)
        <section class="evaluation-panel evaluation-empty"><i class="bi bi-journal-check"></i><h2>No {{ $scope }} evaluation is currently published</h2><p>You can continue with your clearance requests.</p><a class="evaluation-button secondary" href="{{ route('student.clearance-updates') }}"><i class="bi bi-arrow-left"></i>Back to clearance</a></section>
    @else
        <section class="evaluation-panel">
            <div class="evaluation-panel-head">
                <div class="evaluation-panel-title"><span class="evaluation-panel-icon"><i class="bi {{ $scope === 'guidance' ? 'bi-people' : 'bi-book' }}"></i></span><div><span class="evaluation-eyebrow">{{ $officeLabel }} services</span><h2>{{ $evaluation->title }}</h2><p>Rate your {{ $scope }} experience before requesting {{ $scope }} clearance.</p></div></div>
                <span class="evaluation-status {{ $response ? 'complete' : 'pending' }}"><i class="bi {{ $response ? 'bi-check-circle' : 'bi-clock' }}"></i>{{ $response ? 'Completed' : 'Not completed' }}</span>
            </div>
            <div class="evaluation-panel-body">
                @if($evaluation->description)<p class="evaluation-description mt-0 mb-3">{{ $evaluation->description }}</p>@endif
                @if($response)
                    <div class="evaluation-note success mb-3"><strong><i class="bi bi-check-circle me-1"></i>Evaluation completed</strong><p>Completed {{ \Carbon\Carbon::parse($response->completed_at)->format('M d, Y h:i A') }}. Your response has been saved.</p></div>
                    @php($ratings = json_decode($response->ratings, true))
                    @include('partials.library-evaluation-questions')
                @else
                    <div class="evaluation-note mb-3"><strong>How to answer</strong><p>Choose one rating for every statement. {{ $scope === 'library' ? 'The librarian' : 'Guidance staff' }} can see your completion status and responses.</p>@include('partials.library-evaluation-scale')</div>
                    <form class="evaluation-form" method="POST" action="{{ route('student.'.$scope.'-evaluation.store') }}">
                        @csrf
                        <input type="hidden" name="evaluation_id" value="{{ $evaluation->id }}">
                        <input type="hidden" name="revision" value="{{ $evaluation->revision }}">
                        @foreach(\App\Support\LibraryEvaluation::sections($evaluation) as $section)
                            <section class="evaluation-question-set">
                            @include('partials.library-evaluation-section-heading')
                            @foreach($section['questions'] as $index => $question)
                            <fieldset class="evaluation-question">
                                <legend>{{ $index + 1 }}. {{ $question }}</legend>
                                <div class="evaluation-choices">
                                    @foreach(\App\Support\LibraryEvaluation::RATINGS as $rating => $label)
                                        <label class="evaluation-choice"><input type="radio" name="ratings[{{ $index }}]" value="{{ $rating }}" required @checked((int) old('evaluation_id') === (int) $evaluation->id && (int) old('revision') === (int) $evaluation->revision && (string) old('ratings.'.$index) === (string) $rating)><span><strong>{{ $rating }}</strong><small>{{ $label }}</small></span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            @endforeach
                            </section>
                        @endforeach
                        <p class="mt-0">Review your answers before submitting. Submitted responses are final.</p>
                        <div class="evaluation-actions"><button type="submit" class="evaluation-button primary"><i class="bi bi-send-check"></i>Submit evaluation</button><a class="evaluation-button secondary" href="{{ route('student.clearance-updates') }}"><i class="bi bi-arrow-left"></i>Back to clearance</a></div>
                    </form>
                @endif
                @if($response)<div class="evaluation-actions mt-3"><a class="evaluation-button secondary" href="{{ route('student.clearance-updates') }}"><i class="bi bi-arrow-left"></i>Back to clearance</a></div>@endif
            </div>
        </section>
    @endif
</div>
@endsection
