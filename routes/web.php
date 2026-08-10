<?php

use App\Http\Controllers\ManagedMediaController;
use App\Http\Controllers\MediaUploadController;
use App\Support\MediaReferenceResolver;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/cms/media', MediaUploadController::class)
    ->middleware('auth')
    ->name('cms.media.upload');

Route::get('/'.app(MediaReferenceResolver::class)->basePath().'/{media}/{path}', ManagedMediaController::class)
    ->whereNumber('media')
    ->where('path', '.+')
    ->middleware('auth')
    ->name('cms.media.show');
