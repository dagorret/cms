<?php

use App\Http\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/cms/media', MediaUploadController::class)
    ->middleware('auth')
    ->name('cms.media.upload');
