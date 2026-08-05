@php
    $currentPage = max((int) ($currentPage ?? 1), 1);
    $totalPages = max((int) ($totalPages ?? 1), 1);
    $htmlBase = rtrim((string) ($paginationBaseUrl ?? ''), '/');
    $jsonBase = rtrim((string) ($paginationJsonBaseUrl ?? ''), '/');
    $pageUrl = fn(int $page): string => $page === 1 ? ($htmlBase === '' ? '/' : $htmlBase . '/') : $htmlBase . '/page/' . $page . '/';
    $jsonUrl = fn(int $page): string => $jsonBase . '/page-' . $page . '.json';
    $normalClasses = 'min-w-9 cursor-pointer border border-[#d8d0c3] bg-transparent px-[.7rem] py-[.45rem] font-sans text-[.86rem] text-[#66615a] transition-[background-color,color,border-color] duration-150 ease-in-out hover:border-[#0f4c5c]/35 hover:bg-[#dfeff0] hover:text-[#0f4c5c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c] dark:border-[#5d5750] dark:text-[#c5bdb2] dark:hover:bg-[#24383d] dark:hover:text-[#b8dce4]';
    $activeClasses = 'min-w-9 cursor-default border border-[#171717] bg-[#171717] px-[.7rem] py-[.45rem] font-sans text-[.86rem] text-[#fffaf2] dark:border-[#e8e1d5] dark:bg-[#e8e1d5] dark:text-[#171717]';
@endphp
@if($totalPages > 1)
<nav class="mt-12 flex flex-wrap items-center justify-center gap-2 border-t border-[#d8d0c3] pt-6 dark:border-[#4a4640]" id="pagination-nav" aria-label="Paginación">
    @if($currentPage > 1)
        <a href="{{ $pageUrl($currentPage - 1) }}" data-json-url="{{ $jsonUrl($currentPage - 1) }}" rel="prev" aria-label="Página anterior" class="{{ $normalClasses }}">←</a>
    @else
        <span aria-disabled="true" aria-label="Página anterior" class="{{ $normalClasses }} cursor-not-allowed opacity-[.62]">←</span>
    @endif
    @for($page = 1; $page <= $totalPages; $page++)
        <a href="{{ $pageUrl($page) }}" data-json-url="{{ $jsonUrl($page) }}" @if($page === $currentPage) aria-current="page" @endif class="{{ $page === $currentPage ? $activeClasses : $normalClasses }}">{{ $page }}</a>
    @endfor
    @if($currentPage < $totalPages)
        <a href="{{ $pageUrl($currentPage + 1) }}" data-json-url="{{ $jsonUrl($currentPage + 1) }}" rel="next" aria-label="Página siguiente" class="{{ $normalClasses }}">→</a>
    @else
        <span aria-disabled="true" aria-label="Página siguiente" class="{{ $normalClasses }} cursor-not-allowed opacity-[.62]">→</span>
    @endif
</nav>
@endif
