<div class="evaluation-form-preview">
    <div class="evaluation-form-preview-status"><span class="evaluation-status {{ $selected->published_at ? 'current' : 'draft' }}">{{ $selected->published_at ? 'Published' : 'Draft' }}</span></div>
    @if($selected->description)<p class="evaluation-description">{{ $selected->description }}</p>@endif
    @include('partials.library-evaluation-scale')
    <div class="evaluation-form-preview-questions">@include('partials.library-evaluation-questions', ['evaluation' => $selected])</div>
    @if(! $selected->published_at)
        <div class="evaluation-note"><strong>Ready to publish?</strong><p>Students must answer this form before requesting {{ $scope }} clearance. You can edit it later; saving changes will delete its responses and require students to answer again.</p></div>
    @else
        <div class="evaluation-note warning"><strong>One {{ $scope }} evaluation</strong><p>Editing this form deletes all previous responses and resets student completion. Download a CSV copy before making changes if you need to keep the answers.</p></div>
    @endif
    <div class="evaluation-actions evaluation-form-preview-actions">
        <a class="evaluation-button secondary" href="{{ route($officeRouteBase.'.edit', $selected->id) }}"><i class="bi bi-pencil-square"></i>Edit evaluation</a>
        <a class="evaluation-button secondary" href="{{ route($officeRouteBase.'.export', $selected->id) }}"><i class="bi bi-download"></i>Download responses CSV</a>
        @if(! $selected->published_at)
            <form method="POST" action="{{ route($officeRouteBase.'.publish', $selected->id) }}">@csrf<input type="hidden" name="revision" value="{{ $selected->revision }}"><button class="evaluation-button primary" type="submit"><i class="bi bi-send-check"></i>Publish evaluation</button></form>
        @endif
        <form method="POST" action="{{ route($officeRouteBase.'.destroy', $selected->id) }}" data-evaluation-confirm data-confirm-title="Delete evaluation?" data-confirm-message="This deletes the evaluation and all its responses. Students will have no evaluation requirement until you publish a new form. Download a CSV copy first if you need the answers." data-confirm-button="Delete evaluation">
            @csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $selected->revision }}">
            <button class="evaluation-button danger" type="submit"><i class="bi bi-trash"></i>Delete evaluation</button>
        </form>
    </div>
</div>
