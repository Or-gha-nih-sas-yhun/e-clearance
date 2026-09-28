@extends('office.library-evaluations.layout')
@section('evaluation-content')
@php
    $blankSection = ['title' => '', 'description' => '', 'questions' => ['']];
    $savedSections = $evaluation ? \App\Support\LibraryEvaluation::sections($evaluation) : [$blankSection];
    $sections = old('sections', $savedSections);
    if (session()->hasOldInput('questions') && !session()->hasOldInput('sections')) {
        $oldQuestions = old('questions');
        $sections = [['title' => '', 'description' => '', 'questions' => is_string($oldQuestions) ? preg_split('/\\R/u', $oldQuestions) : $oldQuestions]];
    }
    $sections = is_array($sections) && $sections ? array_values($sections) : [$blankSection];
    $originalFields = $evaluation ? [['title', $evaluation->title], ['description', $evaluation->description ?? '']] : [];
    foreach ($savedSections as $sectionIndex => $section) {
        $originalFields[] = ["sections[$sectionIndex][title]", $section['title']];
        $originalFields[] = ["sections[$sectionIndex][description]", $section['description']];
        foreach (array_values($section['questions']) as $questionIndex => $question) {
            $originalFields[] = ["sections[$sectionIndex][questions][$questionIndex]", $question];
        }
    }
    $questionOffset = 0;
@endphp
<section class="evaluation-panel">
    <div class="evaluation-panel-head">
        <div class="evaluation-panel-title"><span class="evaluation-panel-icon"><i class="bi bi-pencil-square"></i></span><div><span class="evaluation-eyebrow">Evaluation editor</span><h2>{{ $evaluation ? 'Edit '.$scope.' evaluation' : 'Create '.$scope.' evaluation' }}</h2><p>Group your statements and guide students with instructions for each set.</p></div></div>
        <span class="evaluation-status {{ $evaluation?->published_at ? 'current' : 'draft' }}">{{ $evaluation?->published_at ? 'Published' : 'Draft' }}</span>
    </div>
    <div class="evaluation-panel-body">
        <form class="evaluation-form" method="POST" action="{{ $evaluation ? route($officeRouteBase.'.update', $evaluation->id) : route($officeRouteBase.'.store') }}" @if($evaluation?->published_at) data-evaluation-confirm data-confirm-only-changes data-original-values="{{ json_encode($originalFields) }}" data-confirm-title="Save changes and clear responses?" data-confirm-message="Saving changes permanently deletes all previous responses. Every student must answer the updated evaluation again. Download the responses as CSV first if you need a copy." data-confirm-button="Save changes" @endif>
            @csrf
            @if($evaluation) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $evaluation->revision) }}"> @endif
            @if($evaluation?->published_at)
                <div class="evaluation-note warning"><strong>Saving changes clears all responses</strong><p>Changes to the title, instructions, question sets, or questions require every student to answer again. The updated form stays published.</p><a class="evaluation-button secondary small mt-3" href="{{ route($officeRouteBase.'.export', $evaluation->id) }}"><i class="bi bi-download"></i>Download responses CSV</a></div>
            @endif
            <div class="evaluation-note evaluation-editor-scale">
                <strong><i class="bi bi-info-circle me-1"></i>Student rating scale</strong><p>Students choose one rating for each statement, using this same scale throughout the form.</p>
                @include('partials.library-evaluation-scale')
            </div>
            <div class="evaluation-form-fields">
                <div class="evaluation-field"><label for="evaluation-title">Evaluation title</label><input id="evaluation-title" name="title" required maxlength="200" value="{{ old('title', $evaluation->title ?? '') }}" placeholder="{{ $officeLabel }} Services Evaluation"></div>
                <div class="evaluation-field"><label for="evaluation-description">General instructions (optional)</label><textarea id="evaluation-description" name="description" rows="3" maxlength="3000" placeholder="Tell students what this evaluation covers.">{{ old('description', $evaluation->description ?? '') }}</textarea></div>
            </div>
            <div class="evaluation-question-section" aria-labelledby="evaluation-questions-heading">
                <div class="evaluation-question-section-head">
                    <div><h3 id="evaluation-questions-heading">Question sets</h3><p id="questions-help">Give each set its own instructions. Write one statement in each question box.</p></div>
                    <button class="evaluation-button secondary" type="button" data-add-section><i class="bi bi-plus-lg"></i>Add question set</button>
                </div>
                <div class="evaluation-section-editors" data-evaluation-sections>
                    @foreach($sections as $sectionIndex => $section)
                        @php
                            $section = is_array($section) ? $section : $blankSection;
                            $sectionQuestions = is_array($section['questions'] ?? null) && $section['questions'] ? array_values($section['questions']) : [''];
                        @endphp
                        @include('office.library-evaluations.section-editor')
                        @php($questionOffset += count($sectionQuestions))
                    @endforeach
                </div>
                <small class="evaluation-question-count" data-question-count aria-live="polite"></small>
            </div>
            <div class="evaluation-actions"><button class="evaluation-button primary" type="submit"><i class="bi bi-check-lg"></i>{{ $evaluation ? 'Save changes' : 'Save draft' }}</button><a class="evaluation-button secondary" href="{{ route($officeRouteBase.'.index') }}"><i class="bi bi-arrow-left"></i>Cancel</a></div>
        </form>
    </div>
</section>
<template id="evaluation-section-template">
    @include('office.library-evaluations.section-editor', ['section' => $blankSection, 'sectionIndex' => -1, 'sectionQuestions' => [''], 'questionOffset' => -1])
</template>
<template id="evaluation-question-template">
    @include('office.library-evaluations.question-editor', ['question' => '', 'sectionIndex' => -1, 'questionIndex' => -1, 'questionNumber' => 0])
</template>
@endsection
@push('scripts')
<script src="{{ asset('js/library-evaluation-editor.js') }}" defer></script>
@endpush
