<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $site->long_name ?? $site->name ?? 'Notas') — Carlos Dagorret</title>
    <meta name="description" content="{{ $site->description ?? $site->meta_description ?? 'Archivo técnico-humanista sobre tecnología, sistemas, sociedad, estrategia e infraestructura.' }}">

    <style>
        :root {
            --bg: #f7f3eb;
            --paper: #fffaf2;
            --ink: #171717;
            --muted: #66615a;
            --line: #d8d0c3;
            --accent: #0f4c5c;
            --accent-soft: #dfeff0;
            --gold: #8a6f2a;
            --max: 1180px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
            line-height: 1.68;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        a {
            color: inherit;
            text-decoration-color: rgba(15, 76, 92, .35);
            text-underline-offset: 3px;
        }
        .site-header {
            border-bottom: 1px solid var(--line);
            padding: 22px 24px 18px;
            background: rgba(247, 243, 235, .96);
            backdrop-filter: blur(6px);
        }
        .header-inner {
            max-width: var(--max);
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: end;
        }
        .brand {
            font-size: clamp(2rem, 5vw, 4.5rem);
            line-height: .9;
            letter-spacing: -.06em;
            font-weight: 700;
        }
        .tagline {
            max-width: 520px;
            color: var(--muted);
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            font-size: .95rem;
        }
        .main-nav {
            max-width: var(--max);
            margin: 14px auto 0;
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            font-size: .86rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .container {
            max-width: 768px;
            margin: 0 auto;
            padding: 28px 24px;
        }
        .kicker {
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--accent);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .article-list {
            margin-top: 0;
            border-top: 3px solid var(--ink);
            padding-top: 18px;
        }
        .article-item {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 24px;
            padding: 18px 0;
            border-bottom: 1px solid var(--line);
        }
        .article-item h2 {
            margin: 0 0 4px;
            font-size: 1.55rem;
            line-height: 1.12;
            letter-spacing: -.03em;
        }
        .feed-item {
            padding: 2.25rem 0;
            border-bottom: 1px solid var(--line);
        }
        .feed-item h2 {
            margin: .65rem 0 .85rem;
            font-size: clamp(1.875rem, 4vw, 2.25rem);
            line-height: 1.04;
            letter-spacing: -.045em;
        }
        .archive-item {
            padding: 18px 0;
            border-bottom: 1px solid var(--line);
        }
        .archive-item a,
        a.archive-item {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: center;
        }
        .meta {
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--muted);
            font-size: .86rem;
        }
        .site-footer {
            border-top: 1px solid var(--line);
            padding: 30px 24px;
            color: var(--muted);
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            font-size: .9rem;
        }
        .footer-inner {
            max-width: var(--max);
            margin: 0 auto;
        }
        .page-btn {
            border: 1px solid var(--line);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            font-size: .86rem;
            min-width: 2.25rem;
            padding: .45rem .7rem;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }
        .page-btn:hover {
            background: var(--accent-soft);
            border-color: rgba(15, 76, 92, .35);
            color: var(--accent);
        }
        .page-btn.active {
            background: var(--ink);
            border-color: var(--ink);
            color: var(--paper);
            cursor: default;
        }
        .page-btn:disabled {
            color: #a19a90;
            cursor: not-allowed;
            opacity: .62;
        }
        .page-btn:disabled:hover {
            background: transparent;
            border-color: var(--line);
        }
        @media (max-width: 900px) {
            .header-inner,
            .article-item {
                display: block;
            }
            .archive-item a,
            a.archive-item {
                align-items: flex-start;
            }
        }
    </style>

    @php
        $subdirUrl = $subdirUrl ?? ($subdir ?? ($site->subdir ? '/' . trim($site->subdir, '/') : ''));
        $useAbsoluteUrls = $useAbsoluteUrls ?? false;
        $assetBaseUrl = rtrim($fullBaseUrl ?? $subdirUrl ?? '', '/');
        $viteManifestPath = public_path('build/manifest.json');
        $viteManifest = ($useAbsoluteUrls && file_exists($viteManifestPath))
            ? json_decode(file_get_contents($viteManifestPath), true)
            : [];
        $viteCss = $viteManifest['resources/css/app.css']['file'] ?? null;
        $viteJs = $viteManifest['resources/js/app.js']['file'] ?? null;
        $menuFile = base_path('dist' . ($site->subdir ? '/' . trim($site->subdir, '/') : '') . '/menu.html');
        $generatedMenu = file_exists($menuFile) ? trim(file_get_contents($menuFile)) : '';
    @endphp

    @if($useAbsoluteUrls && $assetBaseUrl && $viteCss)
        <link rel="stylesheet" href="{{ $assetBaseUrl }}/build/{{ $viteCss }}">
        @if($viteJs)
            <script type="module" src="{{ $assetBaseUrl }}/build/{{ $viteJs }}"></script>
        @endif
    @elseif(!app()->runningInConsole())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="m-0 bg-[#f7f3eb] font-serif text-[#171717] antialiased [text-rendering:optimizeLegibility]">
    <header class="site-header border-b border-[#d8d0c3] bg-[#f7f3eb]/95 px-6 pb-[18px] pt-[22px] backdrop-blur">
        <div class="header-inner mx-auto flex max-w-[1180px] items-end justify-between gap-6 max-[900px]:block">
            <div>
                <a href="{{ $subdirUrl ?: '/' }}/" class="brand font-serif text-[clamp(2rem,5vw,4.5rem)] font-bold leading-[.9] tracking-[-.06em] text-[#171717] decoration-[#0f4c5c]/35 underline-offset-[3px]">
                    Carlos Dagorret
                </a>
            </div>

            <p class="tagline max-w-[520px] font-sans text-[.95rem] leading-6 text-[#66615a] max-[900px]:mt-4">
                {{ $site->description ?? $site->meta_description ?? 'Archivo técnico-humanista sobre tecnología, sistemas, sociedad, estrategia e infraestructura.' }}
            </p>
        </div>

        <div class="mx-auto mt-[14px] flex max-w-[1180px] items-center justify-between gap-6 max-[900px]:items-start max-[900px]:gap-4">
            <nav id="spa-menu" class="main-nav flex flex-wrap gap-[18px] font-sans text-[.86rem] uppercase tracking-[.08em] text-[#171717] [&_a]:decoration-[#0f4c5c]/35 [&_a]:underline-offset-[3px] [&_a:hover]:text-[#0f4c5c]">
                @if($generatedMenu !== '')
                    {!! $generatedMenu !!}
                @else
                    <a href="{{ $subdirUrl ?: '/' }}/" data-tag="">Inicio</a>
                    <a href="{{ $subdirUrl }}/?tag=essay" data-tag="essay">Ensayos</a>
                    <a href="{{ $subdirUrl }}/?tag=notebook" data-tag="notebook">Cuadernos</a>
                    <a href="{{ $subdirUrl }}/?tag=conversation" data-tag="conversation">Conversaciones</a>
                    <a href="{{ $subdirUrl }}/?tag=map" data-tag="map">Mapas</a>
                    <a href="{{ $subdirUrl }}/?tag=source" data-tag="source">Fuentes</a>
                    <a href="{{ $subdirUrl }}/sobre/">Sobre</a>
                @endif
            </nav>

            <nav class="flex shrink-0 items-center gap-3 font-sans text-[.78rem] uppercase tracking-[.08em] text-[#66615a] max-[700px]:hidden">
                <a href="https://github.com/dagorret" rel="me noopener" class="hover:text-[#0f4c5c]">GitHub</a>
                <a href="{{ $subdirUrl }}/feed.xml" class="hover:text-[#0f4c5c]">RSS</a>
                <button type="button" class="border border-[#d8d0c3] px-2 py-1 text-[#66615a]" aria-label="Modo oscuro">◐</button>
            </nav>
        </div>
    </header>

    <main class="container mx-auto max-w-3xl px-6 py-8">
        @yield('content')
    </main>

    <footer class="site-footer border-t border-[#d8d0c3] px-6 py-[30px] font-sans text-[.9rem] text-[#66615a]">
        <div class="footer-inner mx-auto max-w-[1180px]">
            <p>¿Comentarios, correcciones o referencias? <a href="mailto:dagorret@gmail.com" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">Escribime por correo</a>.</p>
            <p class="mt-3">
                <a href="https://github.com/dagorret" class="hover:text-[#0f4c5c]">GitHub</a> ·
                <a href="https://www.linkedin.com/in/carlos-dagorret-59b4a49" class="hover:text-[#0f4c5c]">LinkedIn</a> ·
                <a href="https://x.com/Dagorret_" class="hover:text-[#0f4c5c]">X</a> ·
                <a href="{{ $subdirUrl }}/archive/index.html" class="hover:text-[#0f4c5c]">Archivo</a> ·
                <a href="{{ $subdirUrl }}/feed.xml" class="hover:text-[#0f4c5c]">RSS</a>
            </p>
        </div>
    </footer>
</body>
</html>
