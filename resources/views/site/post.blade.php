<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->title }} — {{ $site->long_name }}</title>
    <meta name="description" content="{{ $post->keywords ?? $site->meta_description }}">
    
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
        header {
            margin-bottom: 40px;
            border-bottom: 1px solid var(--meta-color);
            padding-bottom: 20px;
        }
        h1 { color: #fff; font-size: 2.2rem; line-height: 1.2; margin-bottom: 10px; }
        .meta { color: var(--meta-color); font-size: 0.9rem; }
        .meta span { margin-right: 15px; }
        .badge { background: var(--code-bg); padding: 2px 8px; border-radius: 4px; color: var(--accent-color); }
        article { font-size: 1.1rem; }
        article h2, article h3 { color: #fff; margin-top: 30px; }
        article p { margin-bottom: 20px; }
        footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid var(--meta-color); }
        a { color: var(--accent-color); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <header>
        {{-- Botón para volver usando el subdirectorio dinámico --}}
        <p><a href="{{ $site->subdir ? '/' . trim($site->subdir, '/') : '' }}/">← Volver a la bitácora</a></p>
        <h1>{{ $post->title }}</h1>
        <div class="meta">
            <span>📅 {{ $post->created_at->format('d/m/Y') }}</span>
            <span>📂 Tipo: <span class="badge">{{ $post->type }}</span></span>
        </div>
    </header>

    <article>
        {{-- Parseamos el Markdown que viene de la base de datos a HTML real --}}
        {!! Illuminate\Support\Str::markdown($post->body ?? $post->content ?? '') !!}
    </article>

    <footer>
        <p class="meta">Etiquetas: {{ $post->keywords ?? 'Ninguna' }}</p>
        <p>© 2026 {{ $site->long_name }} — Compilado en estático a la velocidad de la luz.</p>
    </footer>

</body>
</html>
