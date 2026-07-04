<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $site->long_name ?? $site->name ?? 'Archivo') — {{ $site->long_name ?? $site->name ?? 'Sitio' }}</title>
    <meta name="description" content="{{ $site->description ?? $site->meta_description ?? 'Archivo histórico estático' }}">
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
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-200 antialiased">
    <div class="min-h-screen border-x border-slate-900/80 bg-slate-950">
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
