<?php

namespace App\Providers;

use App\EditorJs\MarkdownBlockRenderer;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Post;
use App\Observers\CategoryObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\PostObserver;
use Athphane\FilamentEditorjs\FilamentEditorjs;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('editorjs-markdown-tool', resource_path('js/editorjs-markdown-tool.js')),
            Css::make('editorjs-markdown-tool', resource_path('css/editorjs-markdown-tool.css')),
        ], package: 'faro-cms');

        FilamentEditorjs::addRenderer(new MarkdownBlockRenderer);

        Post::observe(PostObserver::class);
        Category::observe(CategoryObserver::class);
        Menu::observe(MenuObserver::class);
        MenuItem::observe(MenuItemObserver::class);
    }
}
