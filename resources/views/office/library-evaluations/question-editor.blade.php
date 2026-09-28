<div class="evaluation-question-editor" data-question-row>
    <div class="evaluation-field">
        <label for="evaluation-question-{{ $questionNumber - 1 }}" data-question-label>Question {{ $questionNumber }}</label>
        <textarea id="evaluation-question-{{ $questionNumber - 1 }}" name="sections[{{ $sectionIndex }}][questions][{{ $questionIndex }}]" rows="2" required maxlength="500" aria-describedby="questions-help" placeholder="Write a statement for students to rate.">{{ is_scalar($question) ? $question : '' }}</textarea>
    </div>
    <button class="evaluation-button secondary small evaluation-remove-question" type="button" data-remove-question aria-label="Remove question {{ $questionNumber }}"><i class="bi bi-trash"></i><span>Remove</span></button>
</div>
