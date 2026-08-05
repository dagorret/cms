const storageKey = 'cms-faro-theme';

function readThemePreference() {
    try {
        const value = localStorage.getItem(storageKey);
        return value === 'light' || value === 'dark' ? value : null;
    } catch {
        return null;
    }
}

function systemPrefersDark() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

function applyTheme(theme, persist = false) {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.setAttribute('aria-pressed', String(dark));
        toggle.setAttribute('aria-label', dark ? 'Usar modo claro' : 'Usar modo oscuro');
    }

    if (persist) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch {
            // La preferencia se aplica durante esta visita aunque el storage esté bloqueado.
        }
    }
}

function initializeTheme() {
    applyTheme(readThemePreference() ?? (systemPrefersDark() ? 'dark' : 'light'));

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark', true);
    });

    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', (event) => {
        if (readThemePreference() === null) applyTheme(event.matches ? 'dark' : 'light');
    });
}

function publicBasePath() {
    return document.body.dataset.publicBasePath || '';
}

function jsonUrlForPath(pathname) {
    const base = publicBasePath();
    let path = pathname;
    if (base && path.startsWith(base)) path = path.slice(base.length) || '/';

    let match = path.match(/^\/page\/(\d+)\/?$/);
    if (match) return `${base}/data/page-${match[1]}.json`;
    if (path === '/') return `${base}/data/page-1.json`;

    match = path.match(/^\/category\/([^/]+)(?:\/page\/(\d+))?\/?$/);
    if (match) return `${base}/data/categories/${encodeURIComponent(match[1])}/page-${match[2] || 1}.json`;

    return null;
}

function setCurrentMenuItem() {
    const current = window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
    document.querySelectorAll('#spa-menu a').forEach((link) => {
        const active = new URL(link.href, window.location.href).pathname === current;
        if (active) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });
}

async function loadListing(jsonUrl, htmlUrl, push = true) {
    const response = await fetch(jsonUrl, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const payload = await response.json();
    if (typeof payload.html !== 'string') throw new Error('JSON de listado inválido');

    const current = document.querySelector('[data-listing]');
    if (!current) throw new Error('No existe un listado reemplazable');
    current.outerHTML = payload.html;

    if (push) history.pushState({}, '', htmlUrl);
    document.title = payload.title || document.title;
    setCurrentMenuItem();
}

function eligibleClick(event, link) {
    return event.button === 0
        && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey
        && !link.target && !link.hasAttribute('download')
        && new URL(link.href, window.location.href).origin === window.location.origin;
}

document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-json-url]');
    if (!link || !eligibleClick(event, link)) return;

    event.preventDefault();
    const htmlUrl = new URL(link.href, window.location.href).pathname;

    try {
        await loadListing(link.dataset.jsonUrl, htmlUrl, true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch {
        window.location.assign(htmlUrl);
    }
});

window.addEventListener('popstate', async () => {
    const jsonUrl = jsonUrlForPath(window.location.pathname);
    if (!jsonUrl) return window.location.reload();

    try {
        await loadListing(jsonUrl, window.location.pathname, false);
    } catch {
        window.location.reload();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    initializeTheme();
    setCurrentMenuItem();
});
