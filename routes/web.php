<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']);