@php
    $hasMath = (bool) ($hasMath ?? ($post->has_math ?? false));

    // Los @section de más abajo pueden llegar pre-escapados (forma inline "@section('x', $valor)"
    // pasa por e() al capturarse) o crudos (forma de bloque). Se normaliza decodificando una vez;
    // Blade vuelve a escapar al emitir con {{ }}, así que el resultado final es siempre consistente.
    $seoRaw = fn (string $name): string => trim(html_entity_decode((string) ($__env->getSection($name) ?? ''), ENT_QUOTES, 'UTF-8'));

    $seoBrand = 'Carlos Dagorret';
    $seoSiteName = $site->long_name ?? $site->short_name ?? 'Notas';
    $seoDefaultDescription = trim((string) ($site->meta_description ?? $site->slogan ?? '')) ?: 'Archivo técnico-humanista sobre tecnología, sistemas, sociedad, estrategia e infraestructura.';

    $seoTitleRaw = $seoRaw('title');
    $seoTitle = ($seoTitleRaw !== '' ? $seoTitleRaw : $seoSiteName).' — '.$seoBrand;
    $seoDescription = \Illuminate\Support\Str::limit($seoRaw('description') ?: $seoDefaultDescription, 160, '…');
    $seoRobots = $seoRaw('robots') ?: 'index, follow';
    $seoOgType = $seoRaw('og_type') ?: 'website';
    $seoOgImage = $seoRaw('og_image');
    $seoCanonicalOverride = $seoRaw('canonical');
    $seoCanonical = $seoCanonicalOverride !== '' ? $seoCanonicalOverride : (isset($canonicalPath) ? $site->publicUrl($canonicalPath) : '');
    $seoArticlePublished = $seoRaw('article_published_time');
    $seoArticleModified = $seoRaw('article_modified_time');
    $seoArticleSection = $seoRaw('article_section');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="author" content="{{ $seoBrand }}">
    <meta name="robots" content="{{ $seoRobots }}">
    @if($seoCanonical !== '')
    <link rel="canonical" href="{{ $seoCanonical }}">
    @endif

    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:site_name" content="{{ $seoSiteName }}">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    @if($seoCanonical !== '')
    <meta property="og:url" content="{{ $seoCanonical }}">
    @endif
    @if($seoOgImage !== '')
    <meta property="og:image" content="{{ $seoOgImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $seoOgImage }}">
    @else
    <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">

    @if($seoOgType === 'article')
    @if($seoArticlePublished !== '')
    <meta property="article:published_time" content="{{ $seoArticlePublished }}">
    @endif
    @if($seoArticleModified !== '')
    <meta property="article:modified_time" content="{{ $seoArticleModified }}">
    @endif
    @if($seoArticleSection !== '')
    <meta property="article:section" content="{{ $seoArticleSection }}">
    @endif
    <meta property="article:author" content="{{ $seoBrand }}">
    @endif

    @yield('jsonld')

    <script>
        (() => { document.documentElement.classList.add('js'); let value = null; try { value = localStorage.getItem('cms-faro-theme'); } catch {} const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false; const dark = value === 'dark' || (value !== 'light' && systemDark); document.documentElement.classList.toggle('dark', dark); document.documentElement.style.colorScheme = dark ? 'dark' : 'light'; })();
    </script>

    @php
        $configuredSubdir = trim((string) ($site->subdir ?? ''), '/');
        $publicPath = ($configuredSubdir === '' || $configuredSubdir === 'dist') ? '' : '/' . $configuredSubdir;
        $subdirUrl = $subdirUrl ?? ($subdir ?? $publicPath);
        $homeUrl = $subdirUrl === '' ? '/' : rtrim($subdirUrl, '/') . '/';
        $generatedMenu = trim((string) ($generatedMenu ?? ''));
        if ($generatedMenu === '' && isset($site)) {
            $generatedMenu = app(\App\Services\MenuRenderer::class)->render($site, 'primary', $publicPath);
        }
    @endphp

    @if(isset($staticAssets) && $staticAssets instanceof \App\Support\StaticViteAssets)
        @foreach($staticAssets->stylesheetUrls() as $stylesheetUrl)
            <link rel="stylesheet" href="{{ $stylesheetUrl }}">
        @endforeach
        @foreach($staticAssets->scriptUrls() as $scriptUrl)
            <script type="module" src="{{ $scriptUrl }}"></script>
        @endforeach
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @if($hasMath)
        @include('site.partials.mathjax')
    @endif
</head>
<body data-public-base-path="{{ $subdirUrl }}" data-has-math="{{ $hasMath ? 'true' : 'false' }}" class="m-0 bg-[#f7f3eb] font-serif leading-[1.68] text-[#171717] antialiased [text-rendering:optimizeLegibility] [&_a]:text-inherit [&_a]:decoration-[#0f4c5c]/35 [&_a]:underline-offset-[3px] dark:bg-[#171717] dark:text-[#e8e1d5] dark:[&_a]:decoration-[#8fc3cf]/50">
    <header class="border-b border-[#d8d0c3] bg-[#f7f3eb]/95 px-6 pb-[18px] pt-[22px] backdrop-blur dark:border-[#4a4640] dark:bg-[#171717]/95">
        <div class="mx-auto flex max-w-[1180px] items-end justify-between gap-6 max-[900px]:block">
            <div>
                <a href="{{ $homeUrl }}" class="font-serif text-[clamp(2rem,5vw,4.5rem)] font-bold leading-[.9] tracking-[-.06em] text-[#171717] decoration-[#0f4c5c]/35 underline-offset-[3px] dark:text-[#f5f0e7]">
                    Carlos Dagorret
                </a>
            </div>

            <p class="max-w-[520px] font-sans text-[.95rem] leading-6 text-[#66615a] max-[900px]:mt-4 dark:text-[#aaa298]">
                {{ $site->description ?? $site->meta_description ?? 'Archivo técnico-humanista sobre tecnología, sistemas, sociedad, estrategia e infraestructura.' }}
            </p>
        </div>

        <div class="mx-auto mt-[14px] flex max-w-[1180px] items-start justify-between gap-6 max-[900px]:gap-4">
            <div class="min-w-0 flex-1">
                <button type="button" class="site-menu__main-toggle" data-menu-main-toggle aria-expanded="false" aria-controls="site-menu-tree" aria-label="Abrir menú principal"><span aria-hidden="true">☰</span> Menú</button>
                <nav id="spa-menu" data-site-menu aria-label="Navegación principal" class="site-menu font-sans text-[.86rem] uppercase tracking-[.08em] text-[#171717] dark:text-[#e8e1d5]">
                @if($generatedMenu !== '')
                    {!! $generatedMenu !!}
                @else
                    <ul id="site-menu-tree" class="site-menu__root" data-menu-root>
                        <li class="site-menu__item"><div class="site-menu__row"><a href="{{ $homeUrl }}">Inicio</a></div></li>
                        <li class="site-menu__item"><div class="site-menu__row"><a href="{{ $subdirUrl }}/sobre/">Sobre</a></div></li>
                    </ul>
                @endif
                </nav>
            </div>

            <button id="theme-toggle" type="button" class="shrink-0 border border-[#d8d0c3] px-2 py-1 font-sans text-[.78rem] text-[#66615a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c] dark:border-[#5d5750] dark:text-[#c5bdb2]" aria-label="Usar modo oscuro" aria-pressed="false">◐</button>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
        @yield('content')
    </main>

    <footer class="border-t border-[#d8d0c3] px-6 py-[30px] font-sans text-[.9rem] text-[#66615a] dark:border-[#4a4640] dark:text-[#aaa298]">
        <div class="mx-auto max-w-[1180px]">
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
