@if($section['title'] !== '' || $section['description'] !== '')
    <header class="evaluation-set-heading">
        @if($section['title'] !== '')<h3>{{ $section['title'] }}</h3>@endif
        @if($section['description'] !== '')<p class="evaluation-description">{{ $section['description'] }}</p>@endif
    </header>
@endif
