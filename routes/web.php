<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/debug-index', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/{any}', function () {
    return File::get(public_path('index.html'));
})->where('any', '.*');
