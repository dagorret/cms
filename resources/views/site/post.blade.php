<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->title }} — {{ $site->long_name }}</title>
    <meta name="description" content="{{ $post->keywords ?? $site->meta_description }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="mx-auto my-10 max-w-[680px] bg-[#1a1b26] px-5 font-[system-ui,-apple-system,sans-serif] leading-[1.6] text-[#a9b1d6] [&_a]:text-[#7aa2f7] [&_a]:no-underline [&_a:hover]:underline">

    <header class="mb-10 border-b border-[#565f89] pb-5">
        {{-- Botón para volver usando el subdirectorio dinámico --}}
        <p><a href="{{ $site->subdir ? '/' . trim($site->subdir, '/') : '' }}/">← Volver a la bitácora</a></p>
        <h1 class="mb-[10px] text-[2.2rem] leading-[1.2] text-white">{{ $post->title }}</h1>
        <div class="text-[.9rem] text-[#565f89] [&_span]:mr-[15px]">
            <span>📅 {{ $post->created_at->format('d/m/Y') }}</span>
            <span>📄 Tipo: <span class="rounded bg-[#24283c] px-2 py-0.5 text-[#7aa2f7]">{{ $post->type === \App\Models\Post::TYPE_PAGE ? 'Página' : 'Post' }}</span></span>
            @if($post->category)
                <span>📂 Categoría: <a href="{{ $site->subdir ? '/' . trim($site->subdir, '/') : '' }}/category/{{ $post->category->slug }}/">{{ $post->category->name }}</a></span>
            @endif
        </div>
    </header>

    <article class="text-[1.1rem] [&_h2]:mt-[30px] [&_h2]:text-white [&_h3]:mt-[30px] [&_h3]:text-white [&_p]:mb-5">
        {!! method_exists($post, 'renderedBodyHtml') ? $post->renderedBodyHtml() : App\Support\PostBodyRenderer::render($post->body ?? $post->content ?? '') !!}
    </article>

    <footer class="mt-[60px] border-t border-[#565f89] pt-5">
        <p class="text-[.9rem] text-[#565f89]">Etiquetas: {{ $post->keywords ?? 'Ninguna' }}</p>
        <p>© 2026 {{ $site->long_name }} — Compilado en estático a la velocidad de la luz.</p>
    </footer>

</body>
</html>
