<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\SiteBuildCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ⚡ Esto registra tu comando de forma directa y obligatoria
Artisan::command('site:build {site_code}', function ($site_code) {
    $this->call(SiteBuildCommand::class, ['site_code' => $site_code]);
})->purpose('Compila un sitio específico del CMS');
