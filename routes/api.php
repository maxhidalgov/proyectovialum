<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VentanaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\BsaleClientController;
use App\Http\Controllers\ImportacionController;
use App\Http\Controllers\TipoVentanaController;
use App\Http\Controllers\TipoProductoController;
use App\Http\Controllers\Api\CotizadorController;
use App\Http\Controllers\EstadoCotizacionController;
use App\Http\Controllers\ListaPrecioController;
use App\Http\Controllers\UserController;


Route::post('/login', [AuthController::class, 'login']);
// ⛔ REGISTRO PÚBLICO DESHABILITADO - Solo admin puede crear usuarios
// Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:api')->get('/me', [AuthController::class, 'me']);

// 🔐 RUTAS DE ADMINISTRACIÓN (Solo Admin)
Route::middleware(['auth:api', 'permission:gestionar_usuarios'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'getRoles']);
    
    // Gestión de permisos por rol
    Route::get('/permissions', [UserController::class, 'getPermissions']);
    Route::get('/roles/{id}/permissions', [UserController::class, 'getRolePermissions']);
    Route::put('/roles/{id}/permissions', [UserController::class, 'updateRolePermissions']);
});

Route::middleware('api')->group(function () {
        // Ruta específica para facturación
    Route::get('/cotizaciones/aprobadas', [CotizacionController::class, 'getAprobadas']);
    // Rutas resource (deben ir DESPUÉS de las rutas específicas)
    Route::apiResource('cotizaciones', CotizacionController::class);
    
    // 🔐 PRODUCTOS - Requiere permiso para crear/editar/eliminar
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::middleware(['auth:api', 'permission:gestionar_productos'])->group(function () {
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    });
    Route::get('/productos/{id}', [ProductoController::class, 'show']);
    Route::get('/proveedores', [ProveedorController::class, 'index']);
    Route::post('/proveedores', [ProveedorController::class, 'store']);
    Route::get('/colores', [ColorController::class, 'index']);
    Route::post('/colores', [ColorController::class, 'store']);
    Route::get('/unidades', [UnidadController::class, 'index']);
    Route::get('/tipos_material', [CotizadorController::class, 'tiposMaterial']);
    Route::get('/colores', [CotizadorController::class, 'colores']);
    Route::get('/tipos_producto', [CotizadorController::class, 'tiposProducto']);
    Route::get('/tipos_ventana', [TipoVentanaController::class, 'index']);
    Route::post('/cotizador/calcular-materiales', [App\Http\Controllers\CotizadorController::class, 'calcularMateriales']);
    Route::get('/bsale-clientes', [BsaleClientController::class, 'index']);
    Route::get('/bsale-clientes/buscar', [BsaleClientController::class, 'search']);
    Route::get('/bsale-oficinas', [BsaleClientController::class, 'getOffices']);
    Route::get('/bsale-tipos-documento', [BsaleClientController::class, 'getDocumentTypes']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::post('/clientes/importar-todos', [\App\Http\Controllers\ClienteController::class, 'importarTodos']);
    Route::post('/clientes/sincronizar-bsale', [ClienteController::class, 'sincronizarBsale']);
    Route::post('/bsale-clientes/crear', [ClienteController::class, 'crearClienteBsale']);
    Route::post('/bsale-clientes', [BsaleClientController::class, 'store']);
    Route::get('/clientes/buscar', [ClienteController::class, 'buscar']);
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::get('proveedores/{productoId}/{colorId}', [ProductoController::class, 'getProveedoresPorProductoYColor']);
    
    // Ruta temporal para debug de colores en productos
    Route::get('/debug/producto-colores/{id}', function ($id) {
        $producto = \App\Models\Producto::with('coloresPorProveedor.proveedor', 'coloresPorProveedor.color')->find($id);
        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }
        return response()->json([
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo,
            'largo_total' => $producto->largo_total,
            'colores_disponibles' => $producto->coloresPorProveedor->map(fn($cpp) => [
                'id' => $cpp->id,
                'color_id' => $cpp->color_id,
                'color_nombre' => $cpp->color->nombre ?? 'N/A',
                'proveedor_id' => $cpp->proveedor_id,
                'proveedor_nombre' => $cpp->proveedor->nombre ?? 'N/A',
                'costo' => $cpp->costo,
            ])
        ]);
    });
    
    Route::get('/cotizaciones', [CotizacionController::class, 'index']);
    Route::post('/cotizaciones', [CotizacionController::class, 'store']);
    route::get('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']);
    Route::get('/cotizaciones/{id}', [CotizacionController::class, 'show']);
    Route::post('/cotizaciones/{id}/duplicar', [CotizacionController::class, 'duplicar']);
    Route::get('/estados-cotizacion', [EstadoCotizacionController::class, 'index']);
    Route::put('/cotizaciones/{id}', [CotizacionController::class, 'update']);
    Route::put('/ventanas/{id}', [VentanaController::class, 'update']);
    Route::post('/importar-productos', [ImportacionController::class, 'importarProductos']);
    Route::post('/importar-pcp', [ImportacionController::class, 'importarProductoColorProveedor']);

    // Rutas Lista de Precios - Las específicas ANTES del resource
    Route::post('/lista-precios/importar', [ListaPrecioController::class, 'importarDesdeProductoColorProveedor']);
    Route::get('/lista-precios/exportar', [ListaPrecioController::class, 'exportar']);
    Route::apiResource('lista-precios', ListaPrecioController::class);

    // Rutas BSALE
    Route::prefix('bsale')->group(function () {
        Route::get('/test-conexion', [\App\Http\Controllers\BsaleController::class, 'testConexion']);
        Route::get('/tipos-documento', [\App\Http\Controllers\BsaleController::class, 'getTiposDocumento']);
        Route::get('/oficinas', [\App\Http\Controllers\BsaleController::class, 'getOficinas']);
        Route::get('/clientes', [\App\Http\Controllers\BsaleController::class, 'getClientes']);
        Route::get('/clientes-sincronizados', [\App\Http\Controllers\BsaleController::class, 'getClientesSincronizados']);
        Route::post('/clientes', [\App\Http\Controllers\BsaleController::class, 'crearCliente']);
        Route::post('/documento', [\App\Http\Controllers\BsaleController::class, 'crearDocumentoDesdeCotzacion']);
        Route::get('/documento/{id}', [\App\Http\Controllers\BsaleController::class, 'getDocumento']);
        Route::get('/documento/{id}/pdf', [\App\Http\Controllers\BsaleController::class, 'descargarPdf']);
        Route::post('/documento/{id}/enviar-email', [\App\Http\Controllers\BsaleController::class, 'enviarEmail']);
    });

    // routes/api.php
    Route::get('/dashboard/ventas-mensuales', [DashboardController::class, 'ventasMensuales']);
    Route::get('/compras-terceros-mensuales', [DashboardController::class, 'comprasTercerosMensuales']);






    

});
