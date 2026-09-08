@props([
    'paginator',
    'label' => 'results',
])

@php
    // The size control navigates rather than posting a form, so it carries every
    // filter already in the URL without needing a hidden input for each one.
    // `page` is dropped: a bigger page size renumbers the pages under it.
    $sizeId = 'page-size-'.$paginator->getPageName();
@endphp

<div class="table-pager">
    <div class="table-pager-size">
        <label for="{{ $sizeId }}">Show</label>
        <select id="{{ $sizeId }}" onchange="window.location.href = this.value;" aria-label="Rows per page">
            @foreach(\App\Support\ListPageSize::OPTIONS as $size)
            <option value="{{ request()->fullUrlWithQuery(['limit' => $size, $paginator->getPageName() => null]) }}" {{ $paginator->perPage() === $size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
        </select>
        <span>per page</span>
    </div>

    <p class="table-pager-count">
        @if($paginator->total() === 0)
            No {{ $label }} to show
        @else
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $label }}
        @endif
    </p>

    <div class="table-pager-nav">
        @if($paginator->onFirstPage())
            <span class="table-pager-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i> Previous</span>
        @else
            <a class="table-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-left"></i> Previous</a>
        @endif

        <span class="table-pager-page">Page {{ $paginator->currentPage() }} of {{ max($paginator->lastPage(), 1) }}</span>

        @if($paginator->hasMorePages())
            <a class="table-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next <i class="bi bi-chevron-right"></i></a>
        @else
            <span class="table-pager-btn disabled" aria-disabled="true">Next <i class="bi bi-chevron-right"></i></span>
        @endif
    </div>
</div>

@if($paginator->hasPages())
<div class="table-pager-links">{{ $paginator->onEachSide(1)->links() }}</div>
@endif
