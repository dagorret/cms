@php
    $currentPage = max((int) ($currentPage ?? 1), 1);
    $totalPages = max((int) ($totalPages ?? 1), 1);
    $htmlBase = rtrim((string) ($paginationBaseUrl ?? ''), '/');
    $jsonBase = rtrim((string) ($paginationJsonBaseUrl ?? ''), '/');
    $pageUrl = fn(int $page): string => $page === 1 ? ($htmlBase === '' ? '/' : $htmlBase . '/') : $htmlBase . '/page/' . $page . '/';
    $jsonUrl = fn(int $page): string => $jsonBase . '/page-' . $page . '.json';
    $normalClasses = 'pagination__control';
    $activeClasses = 'pagination__control pagination__control--current';
    $disabledClasses = 'pagination__control pagination__control--disabled';
@endphp
@if($totalPages > 1)
<nav class="pagination" id="pagination-nav" aria-label="Paginación">
    @if($currentPage > 1)
        <a href="{{ $pageUrl($currentPage - 1) }}" data-json-url="{{ $jsonUrl($currentPage - 1) }}" rel="prev" aria-label="Página anterior" class="{{ $normalClasses }}">←</a>
    @else
        <span aria-disabled="true" aria-label="Página anterior" class="{{ $disabledClasses }}">←</span>
    @endif
    @for($page = 1; $page <= $totalPages; $page++)
        <a href="{{ $pageUrl($page) }}" data-json-url="{{ $jsonUrl($page) }}" @if($page === $currentPage) aria-current="page" @endif class="{{ $page === $currentPage ? $activeClasses : $normalClasses }}">{{ $page }}</a>
    @endfor
    @if($currentPage < $totalPages)
        <a href="{{ $pageUrl($currentPage + 1) }}" data-json-url="{{ $jsonUrl($currentPage + 1) }}" rel="next" aria-label="Página siguiente" class="{{ $normalClasses }}">→</a>
    @else
        <span aria-disabled="true" aria-label="Página siguiente" class="{{ $disabledClasses }}">→</span>
    @endif
</nav>
@endif
