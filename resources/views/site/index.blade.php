@extends('site.layouts.app')

@section('title', $site->long_name ?? 'Notas')

@section('content')
@php
    $typeLabels = config('static_cms.types', []);
@endphp

<section class="article-list mt-0 border-t-[3px] border-[#171717] pt-[18px]">
    <div class="kicker font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">Últimos artículos</div>

    <div class="posts-container">
        @foreach($posts as $post)
            @php
                $type = $post->category ?: ($post->type ?? 'post');
                $typeLabel = $typeLabels[$type] ?? ucfirst($type);
                $tags = collect(explode(',', $post->keywords ?? ''))
                    ->map(fn($tag) => trim($tag))
                    ->filter()
                    ->values();
                $excerpt = trim(strip_tags($post->excerpt ?? $post->summary ?? $post->description ?? $post->body ?? ''));
            @endphp

            <article class="article-item grid grid-cols-1 gap-4 border-b border-[#d8d0c3] py-[18px] md:grid-cols-[160px_1fr] md:gap-6">
                <div class="meta font-sans text-[.86rem] leading-6 text-[#66615a]">
                    <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('Y-m-d') }}</time><br>
                    <span>{{ $typeLabel }}</span>
                </div>

                <div>
                    <h2 class="m-0 mb-1 font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em] text-[#171717]">
                        <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">{{ $post->title }}</a>
                    </h2>

                    @if($tags->isNotEmpty())
                        <p class="mb-3 font-sans text-[.86rem] leading-6 text-[#66615a]">
                            {{ $tags->join(', ') }}
                        </p>
                    @endif

                    @if($excerpt !== '')
                        <p class="my-3 text-[1.05rem] leading-[1.68] text-[#333333] whitespace-pre-line">
                            {{ Illuminate\Support\Str::limit($excerpt, 420) }}
                        </p>
                    @endif

                    <p class="mt-4 font-sans text-[.86rem] uppercase tracking-[.08em] text-[#0f4c5c]">
                        <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#171717]">Leer artículo completo →</a>
                    </p>
                </div>
            </article>
        @endforeach
    </div>

    <nav class="pagination mt-12 flex flex-wrap items-center justify-center gap-2 border-t border-[#d8d0c3] pt-6" id="pagination-nav" aria-label="Paginación"></nav>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.posts-container');
    const paginationNav = document.querySelector('#pagination-nav');

    let currentPage = 1;
    const totalPages = {{ $totalPages }};
    const subdir = "{{ $subdirUrl }}";
    const typeLabels = @json($typeLabels);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(date) {
        return date || '';
    }

    function formatType(post) {
        const type = post.category || post.type || 'post';
        return typeLabels[type] || String(type).charAt(0).toUpperCase() + String(type).slice(1);
    }

    function formatTags(post) {
        return String(post.keywords || '')
            .split(',')
            .map(tag => tag.trim())
            .filter(Boolean)
            .join(', ');
    }

    function renderPost(post) {
        const date = formatDate(post.date);
        const type = formatType(post);
        const tags = formatTags(post);
        const excerpt = post.excerpt || post.summary || post.description || '';
        const url = `${subdir}/${post.slug}/`;

        return `
            <article class="article-item grid grid-cols-1 gap-4 border-b border-[#d8d0c3] py-[18px] md:grid-cols-[160px_1fr] md:gap-6">
                <div class="meta font-sans text-[.86rem] leading-6 text-[#66615a]">
                    <time datetime="${escapeHtml(date)}">${escapeHtml(date)}</time><br>
                    <span>${escapeHtml(type)}</span>
                </div>

                <div>
                    <h2 class="m-0 mb-1 font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em] text-[#171717]">
                        <a href="${escapeHtml(url)}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">${escapeHtml(post.title)}</a>
                    </h2>

                    ${tags ? `<p class="mb-3 font-sans text-[.86rem] leading-6 text-[#66615a]">${escapeHtml(tags)}</p>` : ''}
                    ${excerpt ? `<p class="my-3 text-[1.05rem] leading-[1.68] text-[#333333] whitespace-pre-line">${escapeHtml(excerpt)}</p>` : ''}

                    <p class="mt-4 font-sans text-[.86rem] uppercase tracking-[.08em] text-[#0f4c5c]">
                        <a href="${escapeHtml(url)}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#171717]">Leer artículo completo →</a>
                    </p>
                </div>
            </article>
        `;
    }

    function updateControls() {
        let html = '';
        const maxItems = 7;
        let pages = [];

        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        html += `<button class="page-btn prev-btn" ${prevDisabled} aria-label="Página anterior">←</button>`;

        if (totalPages <= maxItems) {
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else if (currentPage <= 4) {
            pages = [1, 2, 3, 4, 5, { label: '...', page: 6 }, totalPages];
        } else if (currentPage >= totalPages - 3) {
            pages = [1, { label: '...', page: totalPages - 5 }, totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pages = [
                1,
                { label: '...', page: currentPage - 2 },
                currentPage - 1,
                currentPage,
                currentPage + 1,
                { label: '...', page: currentPage + 2 },
                totalPages
            ];
        }

        pages.forEach(item => {
            if (typeof item === 'number') {
                const activeClass = item === currentPage ? 'active' : '';
                const disabledAttr = item === currentPage ? 'disabled' : '';
                html += `<button class="page-btn num-btn ${activeClass}" data-page="${item}" ${disabledAttr}>${item}</button>`;
                return;
            }

            html += `<button class="page-btn num-btn" data-page="${item.page}" aria-label="Saltar a página ${item.page}">${item.label}</button>`;
        });

        const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
        html += `<button class="page-btn next-btn" ${nextDisabled} aria-label="Página siguiente">→</button>`;

        paginationNav.innerHTML = html;

        paginationNav.querySelectorAll('.num-btn').forEach(btn => {
            btn.addEventListener('click', () => navigateToPage(parseInt(btn.dataset.page)));
        });

        paginationNav.querySelector('.prev-btn').addEventListener('click', () => navigateToPage(currentPage - 1));
        paginationNav.querySelector('.next-btn').addEventListener('click', () => navigateToPage(currentPage + 1));
    }

    async function navigateToPage(page) {
        if (page < 1 || page > totalPages || page === currentPage) return;

        try {
            let postsHtml = '';

            if (page === 1) {
                const response = await fetch(`${subdir}/index.html`);
                const htmlText = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                postsHtml = doc.querySelector('.posts-container').innerHTML;
            } else {
                const response = await fetch(`${subdir}/page-${page}.json`);
                if (!response.ok) throw new Error('Archivo JSON ausente');

                const posts = await response.json();
                postsHtml = posts.map(renderPost).join('');
            }

            container.innerHTML = postsHtml;
            currentPage = page;

            const newUrl = page === 1 ? `${subdir}/` : `${subdir}/?page=${page}`;
            history.pushState({ page: page }, '', newUrl);

            updateControls();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            console.error('Fallo la transición de la SPA:', error);
            updateControls();
        }
    }

    window.addEventListener('popstate', (e) => {
        const page = e.state?.page || 1;
        if (page !== currentPage) navigateToPage(page);
    });

    updateControls();

    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = parseInt(urlParams.get('page'));
    if (initialPage && initialPage > 1 && initialPage <= totalPages) {
        navigateToPage(initialPage);
    }
});
</script>
@endsection
