<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $site->long_name ?? $site->name ?? 'Archivo') — {{ $site->long_name ?? $site->name ?? 'Sitio' }}</title>
    <meta name="description" content="{{ $site->description ?? $site->meta_description ?? 'Archivo histórico estático' }}">
    <style>
        :root {
            --bg-color: #1a1b26;
            --text-color: #a9b1d6;
            --accent-color: #7aa2f7;
            --meta-color: #565f89;
            --code-bg: #24283c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            max-width: 680px;
            margin: 40px auto;
            padding: 0 20px;
        }
        section { margin: 0; padding: 0; }
        header {
            margin-bottom: 40px;
            border-bottom: 1px solid var(--meta-color);
            padding-bottom: 20px;
        }
        h1 {
            color: #fff;
            font-size: 2.2rem;
            line-height: 1.2;
            margin-bottom: 10px;
        }
        h2 { color: #fff; }
        p { color: var(--meta-color); }
        a {
            color: var(--accent-color);
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
        main > section > ol,
        main > section > ul {
            list-style: none;
            padding: 0;
            margin: 0;
            border-top: 1px solid var(--code-bg);
        }
        main > section > nav { border-top: 1px solid var(--code-bg); }
        main > section > ol > li,
        main > section > ul > li {
            border-bottom: 1px solid var(--code-bg);
        }
        main > section > nav > a {
            border-bottom: 1px solid var(--code-bg);
        }
        main > section > ol > li > a,
        main > section > ul > li > a,
        main > section > nav > a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px;
            color: var(--text-color);
        }
        main > section > ol > li > a:hover,
        main > section > ul > li > a:hover,
        main > section > nav > a:hover {
            background: var(--code-bg);
            color: var(--accent-color);
            text-decoration: none;
        }
        article {
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--code-bg);
        }
        article h2, article h3 {
            color: #fff;
            margin-top: 30px;
        }
        article p { margin-bottom: 20px; color: var(--text-color); }
        .meta { color: var(--meta-color); font-size: 0.9rem; }
        .badge {
            background: var(--code-bg);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--accent-color);
        }
        .site-footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid var(--meta-color);
        }
    </style>
    @php
        $useAbsoluteUrls = $useAbsoluteUrls ?? false;
        $assetBaseUrl = rtrim($fullBaseUrl ?? $subdirUrl ?? '', '/');
        $viteManifestPath = public_path('build/manifest.json');
        $viteManifest = ($useAbsoluteUrls && file_exists($viteManifestPath))
            ? json_decode(file_get_contents($viteManifestPath), true)
            : [];
        $viteCss = $viteManifest['resources/css/app.css']['file'] ?? null;
        $viteJs = $viteManifest['resources/js/app.js']['file'] ?? null;
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
<body class="min-h-screen bg-slate-950 text-slate-200 antialiased">
    <main>
        @yield('content')
    </main>
</body>
</html>
