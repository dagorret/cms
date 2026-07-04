<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Motor Estático Tipo NASA
    |--------------------------------------------------------------------------
    */

    // 🎨 Apariencia (Apunta a resources/views/themes/{theme_id}/)
    'theme' => env('STATIC_THEME', 'default'),

    // 📝 Editor por Defecto en Filament (markdown, rich_editor)
    'default_editor' => env('STATIC_EDITOR', 'markdown'), 

    // 🚀 Automatización del Pipeline
    'rebuild_on_publish' => env('STATIC_REBUILD_ON_PUBLISH', true), // true = compila al guardar en Filament / false = manual por cron

    // ⚡ Rendimiento de Construcción Masiva (Etapa 1)
    'build_chunk_size' => env('STATIC_BUILD_CHUNK', 2000), 

    // 📄 Límites de la Portada HTML (Etapa 2)
    'home_first_page_posts' => env('STATIC_HOME_FIRST_PAGE_POSTS', 10),
    'max_home_pages' => env('STATIC_MAX_HOME_PAGES', 20),
    'posts_per_home_page' => env('STATIC_HOME_PER_PAGE', 20),

    // 📡 Límites de Feeds y Sitemaps Masivos
    'max_feed_items' => env('STATIC_MAX_FEED_ITEMS', 50), 
    'sitemap_per_page' => env('STATIC_SITEMAP_PER_PAGE', 1000), 

    // 📝 Tipos de Contenido Disponibles en el CMS
    'types' => [
        'notebook'     => 'Cuaderno',
        'essay'        => 'Ensayo',
        'source'       => 'Fuente',
        'map'          => 'Mapa',
        'conversation' => 'Conversación', // 🔥 Sumamos conversaciones
    ],
];
