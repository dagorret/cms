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

function controlledElement(button) {
    const id = button.getAttribute('aria-controls');
    return id ? document.getElementById(id) : null;
}

function setSubmenuOpen(branch, open) {
    const button = branch.querySelector(':scope > .site-menu__row > [data-menu-submenu-toggle]');
    const submenu = button ? controlledElement(button) : null;
    if (!button || !submenu) return;

    branch.toggleAttribute('data-menu-open', open);
    button.setAttribute('aria-expanded', String(open));
    submenu.hidden = !open;

    const label = button.dataset.menuLabel || button.getAttribute('aria-label')
        ?.replace(/^(Abrir|Cerrar) submenú\s+/, '') || '';
    button.dataset.menuLabel = label;
    button.setAttribute('aria-label', `${open ? 'Cerrar' : 'Abrir'} submenú ${label}`.trim());

    if (!open) {
        branch.querySelectorAll('[data-menu-branch][data-menu-open]').forEach((child) => {
            if (child !== branch) setSubmenuOpen(child, false);
        });
    }
}

function closeSiblingSubmenus(branch) {
    const siblings = branch.parentElement?.children || [];
    [...siblings].forEach((sibling) => {
        if (sibling !== branch && sibling.matches?.('[data-menu-branch][data-menu-open]')) {
            setSubmenuOpen(sibling, false);
        }
    });
}

function closeMenu(nav) {
    nav.querySelectorAll('[data-menu-branch][data-menu-open]').forEach((branch) => setSubmenuOpen(branch, false));
    nav.removeAttribute('data-menu-open');

    const root = nav.querySelector('[data-menu-root]');
    const toggle = root?.id ? document.querySelector(`[data-menu-main-toggle][aria-controls="${root.id}"]`) : null;
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menú principal');
    }
}

function initializeMenus(root = document) {
    root.querySelectorAll('[data-site-menu]:not([data-menu-initialized])').forEach((nav) => {
        nav.setAttribute('data-menu-initialized', '');
        nav.querySelectorAll('[data-menu-submenu]').forEach((submenu) => { submenu.hidden = true; });
    });
}

document.addEventListener('click', (event) => {
    const submenuToggle = event.target.closest('[data-menu-submenu-toggle]');
    if (submenuToggle) {
        const branch = submenuToggle.closest('[data-menu-branch]');
        if (!branch) return;

        const open = submenuToggle.getAttribute('aria-expanded') !== 'true';
        if (open) closeSiblingSubmenus(branch);
        setSubmenuOpen(branch, open);
        return;
    }

    const mainToggle = event.target.closest('[data-menu-main-toggle]');
    if (mainToggle) {
        const root = controlledElement(mainToggle);
        const nav = root?.closest('[data-site-menu]');
        if (!nav) return;

        const open = mainToggle.getAttribute('aria-expanded') !== 'true';
        mainToggle.setAttribute('aria-expanded', String(open));
        mainToggle.setAttribute('aria-label', open ? 'Cerrar menú principal' : 'Abrir menú principal');
        nav.toggleAttribute('data-menu-open', open);
        return;
    }

    const menuLink = event.target.closest('[data-site-menu] a');
    if (menuLink) {
        closeMenu(menuLink.closest('[data-site-menu]'));
        return;
    }

    document.querySelectorAll('[data-site-menu]').forEach(closeMenu);
});

document.addEventListener('focusin', (event) => {
    const toggle = event.target.closest('[data-menu-submenu-toggle]');
    const branch = toggle?.closest('[data-menu-branch]');
    if (!branch || !toggle.matches(':focus-visible')) return;

    closeSiblingSubmenus(branch);
    setSubmenuOpen(branch, true);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const nav = event.target.closest?.('[data-site-menu]');
    if (!nav) return;

    const branch = event.target.closest?.('[data-menu-branch][data-menu-open]')
        || [...nav.querySelectorAll('[data-menu-branch][data-menu-open]')].pop();
    if (branch) {
        const toggle = branch.querySelector(':scope > .site-menu__row > [data-menu-submenu-toggle]');
        setSubmenuOpen(branch, false);
        toggle?.focus();
    } else {
        closeMenu(nav);
    }
});

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
    initializeMenus();
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
    initializeMenus();
    setCurrentMenuItem();
});
