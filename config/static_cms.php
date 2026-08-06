<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Motor Estático Tipo NASA
    |--------------------------------------------------------------------------
    |
    | Este archivo centraliza el comportamiento del pipeline de generación
    | estática y la interfaz de Filament.
    |
    */

    // 🎨 Apariencia (Apunta a resources/views/themes/{theme_id}/)
    'theme' => env('STATIC_THEME', 'default'),

    // 📝 Editor por Defecto en Filament (markdown, rich_editor, editorjs))
    'default_editor' => env('STATIC_EDITOR', 'editorjs'),

    'media' => [
        'type_storage' => 'copy',           // Opciones: 'symlink' o 'copy' (definido por usuario)
        'base_path' => '/assets/media',  // Variable 1: El destino fijo anclado (raíz)
        'subfolder' => '',          // Variable 2: Palabra clave o categoría (puede ser vacía o nula)
        'date_format' => 'Y/m',            // Variable 3: Estructura temporal basada en date() (puede ser 'Y', 'm' o vacía)
        'optimize' => true,             // Flag de optimización al compilar
        'driver' => env('STATIC_MEDIA_DRIVER', 'none'), // none, gd, cwebp
        'cwebp_path' => env('STATIC_MEDIA_CWEBP_PATH', 'cwebp'),
    ],

    // 🚀 Automatización del Pipeline
    'rebuild_on_publish' => env('STATIC_REBUILD_ON_PUBLISH', true), // true = compila al guardar en Filament / false = manual por cron

    'build' => [
        'php_binary' => env('STATIC_BUILD_PHP_BINARY'),
    ],

    // Directorio portable de salida y build compilado consumido por el exportador.
    'dist_root' => env('STATIC_DIST_ROOT', 'dist'),

    'vite' => [
        'build_path' => env('STATIC_VITE_BUILD_PATH'),
    ],

    'menu_locations' => [
        'primary' => 'Principal',
        'secondary' => 'Secundario',
        'footer' => 'Pie',
        'footer_legal' => 'Legal del pie',
        'mobile' => 'Móvil',
        'social' => 'Social',
    ],

    'menu_max_depth' => env('STATIC_MENU_MAX_DEPTH', 3),

    // ⚡ Rendimiento de Construcción Masiva (Etapa 1)
    'build_chunk_size' => env('STATIC_BUILD_CHUNK_SIZE', env('STATIC_BUILD_CHUNK', 1000)),

    // 📄 Límites de la Portada HTML (Etapa 2)
    'home_first_page_posts' => env('STATIC_HOME_FIRST_PAGE_POSTS', 10),
    'max_home_pages' => env('STATIC_MAX_HOME_PAGES', 20),
    'posts_per_home_page' => env('STATIC_HOME_PER_PAGE', 20),

    // 📡 Límites de Feeds y Sitemaps Masivos
    'max_feed_items' => env('STATIC_MAX_FEED_ITEMS', 50),
    'sitemap_urls_per_file' => env('STATIC_SITEMAP_URLS_PER_FILE', 1000),

    // Tipo técnico. Las categorías editoriales viven en la tabla categories.
    'content_types' => [
        'post' => 'Post',
        'page' => 'Página',
    ],
];
