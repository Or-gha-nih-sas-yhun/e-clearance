<section class="evaluation-set-editor" data-section-row>
    <div class="evaluation-question-section-head">
        <h3 data-section-label>Question set {{ $sectionIndex + 1 }}</h3>
        <button class="evaluation-button secondary small" type="button" data-remove-section><i class="bi bi-trash"></i>Remove set</button>
    </div>
    <div class="evaluation-field">
        <label for="evaluation-set-title-{{ $sectionIndex }}" data-section-title-label>Set title (optional)</label>
        <input id="evaluation-set-title-{{ $sectionIndex }}" data-section-title name="sections[{{ $sectionIndex }}][title]" maxlength="200" value="{{ is_scalar($section['title'] ?? '') ? ($section['title'] ?? '') : '' }}" placeholder="For example, {{ $officeLabel }} staff and services">
    </div>
    <div class="evaluation-field">
        <label for="evaluation-set-description-{{ $sectionIndex }}" data-section-description-label>Description or instructions (optional)</label>
        <textarea id="evaluation-set-description-{{ $sectionIndex }}" data-section-description name="sections[{{ $sectionIndex }}][description]" rows="3" maxlength="3000" placeholder="Explain what students should consider when rating this set of questions.">{{ is_scalar($section['description'] ?? '') ? ($section['description'] ?? '') : '' }}</textarea>
    </div>
    <div class="evaluation-question-editors" data-section-questions>
        @foreach($sectionQuestions as $questionIndex => $question)
            @include('office.library-evaluations.question-editor', ['questionNumber' => $questionOffset + $questionIndex + 1])
        @endforeach
    </div>
    <div class="evaluation-actions"><button class="evaluation-button secondary small" type="button" data-add-question><i class="bi bi-plus-lg"></i>Add question</button></div>
</section>
