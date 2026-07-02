<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $site->long_name }}</title>
<meta name="description" content="{{ $site->meta_description }}">
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
article { margin-bottom: 20px; }
a { color: var(--accent-color); text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>

<header style="margin-bottom: 40px;">
<h1>{{ $site->long_name }}</h1>
<p style="color: var(--meta-color)">{{ $site->slogan }}</p>
</header>

{{-- 1. CONTENEDOR DE POSTS: Clave para que JS sepa qué actualizar --}}
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

<nav class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 50px; padding-top: 20px; border-top: 1px solid var(--meta-color);">

{{-- Botón Anterior (Flecha) --}}
@if ($currentPage > 1)
<a href="{{ $currentPage == 2 ? $subdirUrl . '/' : $subdirUrl . '/page/' . ($currentPage - 1) . '/' }}" style="padding: 6px 12px;">⬅️</a>
@endif

{{-- Lógica de Números con Puntos Suspendidos --}}
@for ($i = 1; $i <= $totalPages; $i++)
{{-- Siempre mostramos: la primera página (1), la última, la actual, y sus dos vecinas inmediatas --}}
@if ($i == 1 || $i == $totalPages || abs($i - $currentPage) <= 1)

@if ($i == $currentPage)
{{-- Página Activa --}}
<span style="background: #24283c; color: #fff; padding: 6px 12px; border-radius: 4px; font-weight: bold;">{{ $i }}</span>
@else
{{-- Enlace normal --}}
<a href="{{ $i == 1 ? $subdirUrl . '/' : $subdirUrl . '/page/' . $i . '/' }}" style="padding: 6px 12px;">{{ $i }}</a>
@endif

{{-- Si no cumple la condición anterior, pero está justo antes o después de la zona visible, metemos los '...' --}}
@elseif ($i == 2 && $currentPage > 3)
<span style="color: var(--meta-color); padding: 0 4px;">...</span>
@elseif ($i == $totalPages - 1 && $currentPage < $totalPages - 2)
<span style="color: var(--meta-color); padding: 0 4px;">...</span>
@endif
@endfor

{{-- Botón Siguiente (Flecha) --}}
@if ($currentPage < $totalPages)
<a href="{{ $subdirUrl }}/page/{{ $currentPage + 1 }}/" style="padding: 6px 12px;">➡️</a>
@endif

</nav>

{{-- 3. EL SCRIPT MAGICO: Intercepta los clicks y navega en modo AJAX --}}
<script>
document.addEventListener('click', async function(e) {
    // Buscamos si el click fue en un enlace adentro de la paginación
    const link = e.target.closest('.pagination a');
    if (!link) return;

    // Frenamos la recarga dura del navegador
    e.preventDefault();
    const targetUrl = link.href;

    try {
        // Traemos el HTML de la página destino de forma silenciosa
        const response = await fetch(targetUrl);
        if (!response.ok) return;

        const htmlText = await response.text();

        // Parseamos el texto a HTML real en memoria
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');

        // Reemplazamos quirúrgicamente solo los posts y la botonera
        document.querySelector('.posts-container').innerHTML = doc.querySelector('.posts-container').innerHTML;
        document.querySelector('.pagination').innerHTML = doc.querySelector('.pagination').innerHTML;

        // Cambiamos la URL en la barra del navegador sin recargar
        history.pushState(null, '', targetUrl);

        // Scroll suave arriba para dar feedback visual de cambio de página
        window.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (error) {
        // Si algo falla, el navegador va por la ruta tradicional (de respaldo)
        window.location.href = targetUrl;
    }
});
</script>

</body>
</html>
