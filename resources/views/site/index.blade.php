<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Ensayos</title>
    <style>
        body { background-color: #121214; color: #e1e1e6; font-family: system-ui, sans-serif; line-height: 1.6; max-width: 700px; margin: 40px auto; padding: 0 20px; }
        header { margin-bottom: 50px; border-bottom: 1px solid #29292e; padding-bottom: 20px; }
        h1 { color: #fff; font-size: 2rem; margin: 0; }
        p.subtitle { color: #8d8d99; margin: 5px 0 0 0; }
        .post-list { list-style: none; padding: 0; margin: 0; }
        .post-item { margin-bottom: 30px; }
        .post-date { color: #7c7c8a; font-size: 0.85rem; display: block; margin-bottom: 5px; }
        .post-title { font-size: 1.4rem; margin: 0; }
        .post-title a { color: #4ade80; text-decoration: none; }
        .post-title a:hover { text-decoration: underline; }
        .post-excerpt { color: #c4c4cc; margin: 5px 0 0 0; font-size: 1rem; }
    </style>
</head>
<body>
    <header>
        <h1>Bitácora de Ensayos</h1>
        <p class="subtitle">Pensamientos desde la trinchera de desarrollo.</p>
    </header>

    <main>
        <ul class="post-list">
            @foreach($posts as $post)
                <li class="post-item">
                    <span class="post-date">{{ $post->created_at->format('d \d\e F, Y') }}</span>
                    <h2 class="post-title">
                        <a href="/{{ $post->slug }}/">{{ $post->title }}</a>
                    </h2>
                    @if($post->keywords)
                        <p class="post-excerpt">🏷️ {{ $post->keywords }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </main>
</body>
</html>
