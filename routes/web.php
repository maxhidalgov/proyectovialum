<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\ImportacionController;
use Illuminate\Support\Facades\File;

Route::get('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']);



Route::post('/importar-productos', [ImportacionController::class, 'importarProductos']);
Route::post('/importar-pcp', [ImportacionController::class, 'importarProductoColorProveedor']);

Route::get('/{any}', function (string $any = '') {
    // Un asset con hash que ya no existe (deploy nuevo) NO debe devolver el SPA:
    // eso genera el error de MIME "Expected a JavaScript module ... text/html".
    // Si existe lo servimos (por si el server no maneja estáticos); si no, 404
    // limpio y el front detecta el fallo y se recarga solo.
    if (str_starts_with($any, 'assets/')) {
        $path = public_path($any);
        if (is_file($path)) {
            return response()->file($path);
        }
        abort(404);
    }

    // index.html sin caché para que, al recargar, siempre tome los chunks nuevos.
    return response(File::get(public_path('index.html')))
        ->header('Content-Type', 'text/html')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
})->where('any', '.*');