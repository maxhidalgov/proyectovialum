<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\ImportacionController;
use Illuminate\Support\Facades\File;

Route::get('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']);


Route::get('/importar-productos', [ImportacionController::class, 'importarProductos']);
Route::get('/importar-producto-color-proveedor', [ImportacionController::class, 'importarProductoColorProveedor']);

Route::get('/debug-index', function () {
    return response()->file(public_path('index.html'));
});