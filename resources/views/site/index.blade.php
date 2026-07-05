@extends('site.layouts.app')

@section('title', $site->long_name ?? 'Notas')

@section('content')
@php
    $publicBaseUrl = rtrim($subdirUrl ?? '', '/');
    $dataBaseUrl = rtrim($dataBaseUrl ?? '/data', '/');
    $homeUrl = $publicBaseUrl === '' ? '/' : "{$publicBaseUrl}/";
    $menuJsonUrl = ($publicBaseUrl === '' ? '' : $publicBaseUrl) . '/menu.json';
@endphp

<section class="article-list mt-0 border-t-[3px] border-[#171717] pt-[18px]">
    <div class="kicker font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">Últimos artículos</div>

    <div class="posts-container">
        @foreach($posts as $post)
            @php
                $slug = trim((string) data_get($post, 'slug'), '/');
                $url = data_get($post, 'url') ?: "/{$slug}/";
                $title = data_get($post, 'title');
                $date = data_get($post, 'date');
                $typeLabel = data_get($post, 'typeLabel');
                $tagsValue = data_get($post, 'tags', []);
                $tags = collect(is_array($tagsValue) ? $tagsValue : explode(',', (string) $tagsValue))
                    ->map(fn($tag) => trim($tag))
                    ->filter()
                    ->values();
                $excerpt = trim((string) data_get($post, 'excerpt', ''));
            @endphp

            <article class="feed-item border-b border-[#d8d0c3] py-9">
                <div class="meta font-sans text-[.78rem] lowercase tracking-[.14em] text-[#66615a]">
                    @if($date)
                        <time datetime="{{ $date }}">{{ $date }}</time>
                    @endif
                    @if($typeLabel)
                        <span>{{ $date ? ' · ' : '' }}{{ $typeLabel }}</span>
                    @endif
                    @if($tags->isNotEmpty())
                        <span> · {{ $tags->join(', ') }}</span>
                    @endif
                </div>

                <h2 class="my-3 font-serif text-[clamp(1.875rem,4vw,2.25rem)] font-bold leading-[1.04] tracking-[-.045em] text-[#171717]">
                    <a href="{{ $url }}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        {{ $title }}
                    </a>
                </h2>

                @if($excerpt !== '')
                    <p class="summary mt-3 max-w-2xl text-[1.06rem] leading-[1.62] text-[#171717]/90">
                        {{ $excerpt }}
                    </p>
                @endif
            </article>
        @endforeach
    </div>

    <nav class="pagination mt-12 flex flex-wrap items-center justify-center gap-2 border-t border-[#d8d0c3] pt-6" id="pagination-nav" aria-label="Paginación"></nav>
</section>

<script>
    const initialTotalPages = @json((int) ($totalPages ?? 1));
    const initialCurrentPage = 1;
    const initialTag = @json($currentTag ?? '');
    const dataBaseUrl = @json($dataBaseUrl);
    const homeUrl = @json($homeUrl);
    const menuJsonUrl = @json($menuJsonUrl);

    const paginationNav = document.getElementById('pagination-nav');
    const container = document.querySelector('.posts-container');
    const menuNav = document.getElementById('spa-menu');

    let currentPage = initialCurrentPage;
    let currentTag = initialTag || null;
    let totalPages = initialTotalPages;

    function renderPagination(total) {
        totalPages = Math.max(parseInt(total || 1), 1);

        if (totalPages <= 1) {
            paginationNav.innerHTML = '';
            return;
        }

        paginationNav.innerHTML = '';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.innerHTML = '←';
        prevBtn.disabled = currentPage === 1;
        prevBtn.setAttribute('aria-label', 'Página anterior');
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationNav.appendChild(prevBtn);

        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = startPage + maxVisible - 1;

        if (endPage > totalPages) {
            endPage = totalPages;
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            const firstBtn = document.createElement('button');
            firstBtn.className = 'page-btn';
            firstBtn.textContent = '1';
            firstBtn.onclick = () => goToPage(1);
            paginationNav.appendChild(firstBtn);

            if (startPage > 2) {
                const dots = document.createElement('span');
                dots.className = 'px-2 text-[#66615a] font-sans text-sm';
                dots.textContent = '...';
                paginationNav.appendChild(dots);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
            btn.textContent = i;
            if (i === currentPage) {
                btn.setAttribute('aria-current', 'page');
            }
            btn.onclick = () => goToPage(i);
            paginationNav.appendChild(btn);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dots = document.createElement('span');
                dots.className = 'px-2 text-[#66615a] font-sans text-sm';
                dots.textContent = '...';
                paginationNav.appendChild(dots);
            }

            const lastBtn = document.createElement('button');
            lastBtn.className = 'page-btn';
            lastBtn.textContent = totalPages;
            lastBtn.onclick = () => goToPage(totalPages);
            paginationNav.appendChild(lastBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.innerHTML = '→';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.setAttribute('aria-label', 'Página siguiente');
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationNav.appendChild(nextBtn);
    }

    function buildStateUrl() {
        const params = new URLSearchParams();
        if (currentTag) params.set('tag', currentTag);
        if (currentPage > 1) params.set('page', currentPage);
        const query = params.toString();
        return query ? `?${query}` : window.location.pathname;
    }

    function buildDataUrl(path) {
        return `${dataBaseUrl}/${path}`.replace(/\/{2,}/g, '/');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderPost(post) {
        const tags = Array.isArray(post.tags) ? post.tags.filter(Boolean) : [];
        const meta = [];
        const slug = String(post.slug ?? '').replace(/^\/+|\/+$/g, '');
        const url = post.url || `/${slug}/`;

        if (post.typeLabel) meta.push(escapeHtml(post.typeLabel));
        if (tags.length > 0) meta.push(escapeHtml(tags.join(', ')));

        const dateMarkup = post.date
            ? `<time datetime="${escapeHtml(post.date)}">${escapeHtml(post.date)}</time>`
            : '';
        const metaMarkup = meta.length > 0
            ? `<span>${post.date ? ' · ' : ''}${meta.join(' · ')}</span>`
            : '';
        const excerptMarkup = post.excerpt
            ? `<p class="summary mt-3 max-w-2xl text-[1.06rem] leading-[1.62] text-[#171717]/90">${escapeHtml(post.excerpt)}</p>`
            : '';

        return `
            <article class="feed-item border-b border-[#d8d0c3] py-9">
                <div class="meta font-sans text-[.78rem] lowercase tracking-[.14em] text-[#66615a]">
                    ${dateMarkup}
                    ${metaMarkup}
                </div>
                <h2 class="my-3 font-serif text-[clamp(1.875rem,4vw,2.25rem)] font-bold leading-[1.04] tracking-[-.045em] text-[#171717]">
                    <a href="${escapeHtml(url)}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        ${escapeHtml(post.title)}
                    </a>
                </h2>
                ${excerptMarkup}
            </article>
        `;
    }

    function setMenuState() {
        if (!menuNav) return;

        menuNav.querySelectorAll('a[data-tag]').forEach(link => {
            const linkTag = link.getAttribute('data-tag');
            if (linkTag === (currentTag || '')) {
                link.classList.add('text-[#0f4c5c]', 'font-bold');
            } else {
                link.classList.remove('text-[#0f4c5c]', 'font-bold');
            }
        });
    }

    function bindMenuLinks() {
        if (!menuNav) return;

        menuNav.querySelectorAll('a[data-tag]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tag = link.getAttribute('data-tag') || null;
                if (tag === currentTag && currentPage === 1) return;

                currentTag = tag;
                currentPage = 1;

                loadPosts({ push: true });
            });
        });
    }

    async function hydrateMenu() {
        if (!menuNav) return;

        try {
            const response = await fetch(menuJsonUrl);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const items = await response.json();
            const links = [
                `<a href="${escapeHtml(homeUrl)}" data-tag="">Inicio</a>`,
                ...items.map(item => {
                    const tag = item.tag || item.slug;
                    const title = item.title || item.name || tag;
                    const href = `${homeUrl}?tag=${encodeURIComponent(tag)}`;

                    return `<a href="${escapeHtml(href)}" data-tag="${escapeHtml(tag)}">${escapeHtml(title)}</a>`;
                }),
            ];

            menuNav.innerHTML = links.join('');
        } catch (error) {
            console.warn('No se pudo hidratar menu.json:', error);
        }

        bindMenuLinks();
        setMenuState();
    }

    async function loadPosts({ push = true, scroll = true } = {}) {
        if (!container) return;

        container.classList.add('opacity-40');

        try {
            const jsonPath = currentTag 
                ? buildDataUrl(`tags/${encodeURIComponent(currentTag)}/page-${currentPage}.json`)
                : buildDataUrl(`page-${currentPage}.json`);

            const response = await fetch(jsonPath);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();

            container.innerHTML = '';

            if (!data.posts || data.posts.length === 0) {
                container.innerHTML = '<p class="py-12 text-center text-[#66615a] italic font-sans">No se encontraron artículos.</p>';
                renderPagination(1);
                return;
            }

            container.innerHTML = data.posts.map(renderPost).join('');

            renderPagination(data.totalPages);
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
</script>
@endsection
