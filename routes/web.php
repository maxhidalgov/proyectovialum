<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\ImportacionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']);


Route::get('/importar-productos', [ImportacionController::class, 'importarProductos']);
Route::get('/importar-producto-color-proveedor', [ImportacionController::class, 'importarProductoColorProveedor']);
