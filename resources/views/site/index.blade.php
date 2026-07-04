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

            <article class="feed-item border-b border-[#d8d0c3] py-9">
                <div class="meta font-sans text-[.78rem] lowercase tracking-[.14em] text-[#66615a]">
                    <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('Y-m-d') }}</time>
                    <span> · {{ $typeLabel }}</span>
                    @if($tags->isNotEmpty())
                        <span> · {{ $tags->join(', ') }}</span>
                    @endif
                </div>

                <h2 class="mt-3 mb-4 font-serif text-3xl font-bold leading-[1.04] tracking-[-.045em] text-[#171717] md:text-4xl">
                    <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">{{ $post->title }}</a>
                </h2>

                @if($excerpt !== '')
                    <p class="my-4 text-[1.08rem] leading-[1.72] text-[#333333] whitespace-pre-line">
                        {{ Illuminate\Support\Str::limit($excerpt, 420) }}
                    </p>
                @endif

                <p class="mt-5 font-sans text-[.86rem] uppercase tracking-[.08em] text-[#0f4c5c]">
                    <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#171717]">Seguir leyendo →</a>
                </p>
            </article>
        @endforeach
    </div>

    <nav class="pagination mt-12 flex flex-wrap items-center justify-center gap-2 border-t border-[#d8d0c3] pt-6" id="pagination-nav" aria-label="Paginación"></nav>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.posts-container');
    const paginationNav = document.querySelector('#pagination-nav');
    const menuNav = document.querySelector('#spa-menu');

    const initialTotalPages = {{ $totalPages }};
    const subdir = "{{ $subdirUrl }}";
    const typeLabels = @json($typeLabels);
    const tagPageTotals = new Map();

    let currentPage = 1;
    let currentTag = null;
    let totalPages = initialTotalPages;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function localPath(path) {
        return `${subdir}${path}`.replace(/\/{2,}/g, '/');
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

    function buildStateUrl() {
        const params = new URLSearchParams();

        if (currentTag) {
            params.set('tag', currentTag);
        }

        if (currentPage > 1) {
            params.set('page', currentPage);
        }

        const query = params.toString();
        const base = subdir || '/';

        return query ? `${base}?${query}` : `${base}/`.replace(/\/{2,}/g, '/');
    }

    function getDataUrl() {
        if (currentTag) {
            return localPath(`/data/tags/${encodeURIComponent(currentTag)}/page-${currentPage}.json`);
        }

        return localPath(`/data/page-${currentPage}.json`);
    }

    function renderPost(post) {
        const date = post.date || '';
        const type = formatType(post);
        const tags = formatTags(post);
        const excerpt = post.excerpt || post.summary || post.description || '';
        const url = post.url || localPath(`/posts/${post.slug}`);
        const secondaryMeta = [type, tags].filter(Boolean).join(' · ');

        return `
            <article class="feed-item border-b border-[#d8d0c3] py-9">
                <div class="meta font-sans text-[.78rem] lowercase tracking-[.14em] text-[#66615a]">
                    ${date ? `<time datetime="${escapeHtml(date)}">${escapeHtml(date)}</time>` : ''}
                    ${secondaryMeta ? `<span>${date ? ' · ' : ''}${escapeHtml(secondaryMeta)}</span>` : ''}
                </div>

                <h2 class="mt-3 mb-4 font-serif text-3xl font-bold leading-[1.04] tracking-[-.045em] text-[#171717] md:text-4xl">
                    <a href="${escapeHtml(url)}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">${escapeHtml(post.title)}</a>
                </h2>

                ${excerpt ? `<p class="my-4 text-[1.08rem] leading-[1.72] text-[#333333] whitespace-pre-line">${escapeHtml(excerpt)}</p>` : ''}

                <p class="mt-5 font-sans text-[.86rem] uppercase tracking-[.08em] text-[#0f4c5c]">
                    <a href="${escapeHtml(url)}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#171717]">Seguir leyendo →</a>
                </p>
            </article>
        `;
    }

    function normalizePayload(payload) {
        if (Array.isArray(payload)) {
            return {
                posts: payload,
                totalPages: currentTag ? (tagPageTotals.get(currentTag) || 1) : initialTotalPages,
            };
        }

        return {
            posts: payload.posts || [],
            totalPages: parseInt(payload.total_pages || payload.totalPages || (currentTag ? tagPageTotals.get(currentTag) : initialTotalPages) || 1),
        };
    }

    function renderPagination(total) {
        totalPages = Math.max(parseInt(total || 1), 1);

        if (totalPages <= 1) {
            paginationNav.innerHTML = '';
            return;
        }

        let html = '';
        let pages = [];

        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        html += `<button class="page-btn prev-btn" ${prevDisabled} aria-label="Página anterior">←</button>`;

        if (totalPages <= 5) {
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else if (currentPage <= 3) {
            pages = [1, 2, 3, { label: '...', page: 4 }, totalPages];
        } else if (currentPage >= totalPages - 2) {
            pages = [1, { label: '...', page: totalPages - 3 }, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pages = [
                1,
                { label: '...', page: currentPage - 1 },
                currentPage,
                { label: '...', page: currentPage + 1 },
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
            btn.addEventListener('click', () => goToPage(parseInt(btn.dataset.page)));
        });

        paginationNav.querySelector('.prev-btn').addEventListener('click', () => goToPage(currentPage - 1));
        paginationNav.querySelector('.next-btn').addEventListener('click', () => goToPage(currentPage + 1));
    }

    function setMenuState() {
        if (!menuNav) return;

        menuNav.querySelectorAll('a[data-tag]').forEach(link => {
            const isActive = (link.dataset.tag || '') === (currentTag || '');
            link.toggleAttribute('aria-current', isActive);
            link.classList.toggle('text-[#0f4c5c]', isActive);
        });
    }

    async function hydrateMenu() {
        if (!menuNav) return;

        try {
            const response = await fetch(localPath('/menu.json'));
            if (!response.ok) throw new Error('menu.json ausente');

            const items = await response.json();
            const links = [
                `<a href="${escapeHtml(subdir || '/')}" data-tag="">Inicio</a>`,
                ...items.map(item => {
                    const tag = item.tag || item.slug;
                    const title = item.title || item.name || tag;
                    const total = parseInt(item.total_pages || item.totalPages || 1);

                    if (tag) {
                        tagPageTotals.set(tag, total);
                    }

                    return `<a href="${escapeHtml(`${subdir || '/'}?tag=${encodeURIComponent(tag)}`)}" data-tag="${escapeHtml(tag)}">${escapeHtml(title)}</a>`;
                })
            ];

            menuNav.innerHTML = links.join('');
        } catch (error) {
            console.warn('No se pudo hidratar menu.json:', error);
        }

        menuNav.querySelectorAll('a[data-tag]').forEach(link => {
            link.addEventListener('click', (event) => {
                event.preventDefault();

                currentTag = link.dataset.tag || null;
                currentPage = 1;
                loadPosts({ push: true });
            });
        });

        setMenuState();
    }

    async function loadPosts({ push = true, scroll = true } = {}) {
        container.classList.add('opacity-40', 'transition-opacity', 'duration-200');

        try {
            const response = await fetch(getDataUrl());
            if (!response.ok) throw new Error('Fragmento JSON ausente');

            const payload = normalizePayload(await response.json());
            container.innerHTML = payload.posts.map(renderPost).join('');
            totalPages = payload.totalPages;

            renderPagination(totalPages);
            setMenuState();

            if (push) {
                history.pushState({ page: currentPage, tag: currentTag }, '', buildStateUrl());
            }

            if (scroll) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (error) {
            console.error('Fallo la transición de la SPA:', error);
            renderPagination(totalPages);
        } finally {
            container.classList.remove('opacity-40');
        }
    }

    function goToPage(page) {
        if (page < 1 || page > totalPages || page === currentPage) return;

        currentPage = page;
        loadPosts({ push: true });
    }

    function readStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        currentTag = params.get('tag') || null;
        currentPage = Math.max(parseInt(params.get('page') || '1'), 1);
    }

    window.addEventListener('popstate', (e) => {
        if (e.state) {
            currentPage = e.state.page || 1;
            currentTag = e.state.tag || null;
        } else {
            readStateFromUrl();
        }

        loadPosts({ push: false });
    });

    readStateFromUrl();
    history.replaceState({ page: currentPage, tag: currentTag }, '', buildStateUrl());

    hydrateMenu();

    if (currentTag || currentPage > 1) {
        loadPosts({ push: false, scroll: false });
    } else {
        renderPagination(totalPages);
        setMenuState();
    }
});
</script>
@endsection
