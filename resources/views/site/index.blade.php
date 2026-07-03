<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $site->long_name ?? 'Bitácora de Ensayos' }}</title>
<meta name="description" content="{{ $site->description ?? 'Laboratorio de pruebas estáticas' }}">
<style>
:root {
    --bg-color: #1a1b26;
    --text-color: #a9b1d6;
    --accent-color: #7aa2f7;
    --meta-color: #565f89;
}
body {
    background-color: var(--bg-color);
    color: var(--text-color);
    font-family: system-ui, -apple-system, sans-serif;
    max-width: 680px;
    margin: 40px auto;
    padding: 0 20px;
}
h1 { color: #fff; }
article { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #24283c; }
a { color: var(--accent-color); text-decoration: none; }
a:hover { text-decoration: underline; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 50px; padding-top: 20px; border-top: 1px solid var(--meta-color); }
.page-btn { padding: 6px 12px; cursor: pointer; border-radius: 4px; background: transparent; color: var(--accent-color); border: none; font-size: 1rem; }
.page-btn:hover { background: #24283c; }
.page-btn.active { background: #7aa2f7; color: #1a1b26; font-weight: bold; cursor: default; }
.page-btn:disabled { color: var(--meta-color); cursor: not-allowed; background: transparent; }
.page-ellipsis { padding: 0 4px; color: var(--meta-color); user-select: none; }
</style>
</head>
<body>

<header style="margin-bottom: 40px;">
<h1>{{ $site->long_name ?? 'Bitácora de Ensayos' }}</h1>
<p style="color: var(--meta-color)">{{ $site->description ?? 'Laboratorio de pruebas estáticas' }}</p>
</header>

<div class="posts-container">
@foreach($posts as $post)
<article>
<h2 style="font-size: 1.4rem; margin-bottom: 5px;">
<a href="{{ $subdirUrl }}/{{ $post->slug }}/">{{ $post->title }}</a>
</h2>
<small style="color: var(--meta-color)">📅 {{ $post->created_at->format('d/m/Y') }}</small>
</article>
@endforeach
</div>

<nav class="pagination"></nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.posts-container');
    const paginationNav = document.querySelector('.pagination');

    let currentPage = 1;
    const totalPages = {{ $totalPages }};
    const subdir = "{{ $subdirUrl }}";

    function updateControls() {
        let html = '';

// Flecha anterior
const prevDisabled = (currentPage === 1) ? 'disabled' : '';
html += `<button class="page-btn prev-btn" ${prevDisabled}>⬅️</button>`;

if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) {
        const activeClass = (i === currentPage) ? 'active' : '';
const disabledAttr = (i === currentPage) ? 'disabled' : '';
html += `<button class="page-btn num-btn ${activeClass}" data-page="${i}" ${disabledAttr}>${i}</button>`;
    }
} else if (currentPage <= 4) {
    for (let i = 1; i <= 5; i++) {
        const activeClass = (i === currentPage) ? 'active' : '';
const disabledAttr = (i === currentPage) ? 'disabled' : '';
html += `<button class="page-btn num-btn ${activeClass}" data-page="${i}" ${disabledAttr}>${i}</button>`;
    }
    html += `<span class="page-ellipsis">...</span>`;
    html += `<button class="page-btn num-btn" data-page="${totalPages}">${totalPages}</button>`;
} else if (currentPage >= totalPages - 3) {
    html += `<button class="page-btn num-btn" data-page="1">1</button>`;
    html += `<span class="page-ellipsis">...</span>`;
    for (let i = totalPages - 4; i <= totalPages; i++) {
        const activeClass = (i === currentPage) ? 'active' : '';
const disabledAttr = (i === currentPage) ? 'disabled' : '';
html += `<button class="page-btn num-btn ${activeClass}" data-page="${i}" ${disabledAttr}>${i}</button>`;
    }
} else {
    html += `<button class="page-btn num-btn" data-page="1">1</button>`;
    html += `<span class="page-ellipsis">...</span>`;
    for (let i = currentPage - 1; i <= currentPage + 1; i++) {
        const activeClass = (i === currentPage) ? 'active' : '';
const disabledAttr = (i === currentPage) ? 'disabled' : '';
html += `<button class="page-btn num-btn ${activeClass}" data-page="${i}" ${disabledAttr}>${i}</button>`;
    }
    html += `<span class="page-ellipsis">...</span>`;
    html += `<button class="page-btn num-btn" data-page="${totalPages}">${totalPages}</button>`;
}

// Flecha siguiente
const nextDisabled = (currentPage === totalPages) ? 'disabled' : '';
html += `<button class="page-btn next-btn" ${nextDisabled}>➡️</button>`;

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
    if (!response.ok) throw new Error("Archivo JSON ausente");

    const posts = await response.json();
    postsHtml = posts.map(post => `
    <article>
    <h2 style="font-size: 1.4rem; margin-bottom: 5px;">
    <a href="${subdir}/${post.slug}/">${post.title}</a>
    </h2>
    <small style="color: var(--meta-color)">📅 ${post.date.split('-').reverse().join('/')}</small>
    </article>
    `).join('');
}

container.innerHTML = postsHtml;
currentPage = page;

const newUrl = page === 1 ? `${subdir}/` : `${subdir}/?page=${page}`;
history.pushState({ page: page }, '', newUrl);

updateControls();
window.scrollTo({ top: 0, behavior: 'smooth' });

        } catch (error) {
            console.error("Fallo la transición de la SPA:", error);
            // Defensivo: si el fetch falla, aseguramos que la botonera exista igual.
            updateControls();
        }
    }

    window.addEventListener('popstate', (e) => {
        const page = e.state?.page || 1;
        if (page !== currentPage) navigateToPage(page);
    });

        // === INICIALIZACIÓN ===
        // Renderizamos la botonera antes de disparar cualquier fetch. Así, si el fetch tarda
        // o falla, el usuario nunca ve un <nav> vacío.
        updateControls();

        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = parseInt(urlParams.get('page'));
        // Importante: NO setear currentPage = initialPage antes de llamar a navigateToPage().
        // Si lo hacemos, el guard `page === currentPage` de navigateToPage devuelve inmediatamente
        // y la página nunca se carga. Ese era el bug que dejaba la botonera vacía cuando se
        // entraba con ?page=N.
        if (initialPage && initialPage > 1 && initialPage <= totalPages) {
            navigateToPage(initialPage);
        }
});
</script>

</body>
</html>
