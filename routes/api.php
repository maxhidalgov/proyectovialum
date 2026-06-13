<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VentanaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardFinancieroController;
use App\Http\Controllers\BancochilePortalController;
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
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\OperacionesController;
use App\Http\Controllers\DocumentoFacturacionController;
use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\WinperfilController;


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

Route::middleware('auth:api')->group(function () {
    // Agente cotizador IA
    Route::post('/agente/cotizar', [AgenteController::class, 'cotizar']);

    // Rutas específicas ANTES del apiResource para evitar conflicto con {id}
    Route::get('/cotizaciones/aprobadas', [CotizacionController::class, 'getAprobadas']);
    Route::post('/cotizaciones/parse-winperfil', [CotizacionController::class, 'parseWinperfil']);
    Route::post('/cotizaciones/importar-winperfil', [CotizacionController::class, 'importarWinperfil']);
    Route::post('/cotizaciones/{id}/actualizar-winperfil', [CotizacionController::class, 'actualizarWinperfil']);
    // Rutas resource
    Route::apiResource('cotizaciones', CotizacionController::class);

    Route::get('/cotizaciones/{id}/pdf',  [CotizacionController::class, 'generarPDF']);
    Route::post('/cotizaciones/{id}/pdf', [CotizacionController::class, 'generarPDF']); // con graficos PNG del frontend (fallback)
    Route::post('/cotizaciones/{id}/guardar-graficos-png', [CotizacionController::class, 'guardarGraficosPng']);
    Route::get('/cotizaciones/{id}/orden-trabajo', [CotizacionController::class, 'generarOrdenTrabajo']);
    Route::get('/cotizaciones/{id}/hoja-cortes', [ProduccionController::class, 'hojaCortes']);
    Route::get('/cotizaciones/{id}/materiales', [ProduccionController::class, 'resumenMateriales']);
    Route::post('/cotizaciones/{id}/duplicar', [CotizacionController::class, 'duplicar']);
    Route::patch('/cotizaciones/{id}/estado', [CotizacionController::class, 'cambiarEstado']);
    Route::post('/cotizaciones/{id}/imagenes', [CotizacionController::class, 'subirImagenes']);
    Route::get('/estados-cotizacion', [EstadoCotizacionController::class, 'index']);
    Route::put('/ventanas/{id}', [VentanaController::class, 'update']);
    Route::post('/importar-productos', [ImportacionController::class, 'importarProductos']);
    Route::post('/importar-pcp', [ImportacionController::class, 'importarProductoColorProveedor']);

    // PRODUCTOS - Requiere permiso para crear/editar/eliminar
    Route::get('/perfiles-constructor', [ProductoController::class, 'perfilesConstructor']);
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos/{id}', [ProductoController::class, 'show']);
    Route::middleware(['permission:gestionar_productos'])->group(function () {
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    });

    Route::get('/proveedores', [ProveedorController::class, 'index']);
    Route::post('/proveedores', [ProveedorController::class, 'store']);
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update']);
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy']);
    Route::get('/colores', [ColorController::class, 'index']);
    Route::post('/colores', [ColorController::class, 'store']);
    Route::get('/unidades', [UnidadController::class, 'index']);
    Route::get('/tipos_material', [CotizadorController::class, 'tiposMaterial']);
    Route::put('/tipos_material/{id}/margen', [CotizadorController::class, 'updateMargenMaterial']);
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
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show']);
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update']);
    Route::get('proveedores/{productoId}/{colorId}', [ProductoController::class, 'getProveedoresPorProductoYColor']);

    // Rutas Lista de Precios - Las específicas ANTES del resource
    Route::post('/lista-precios/importar', [ListaPrecioController::class, 'importarDesdeProductoColorProveedor']);
    Route::get('/lista-precios/exportar', [ListaPrecioController::class, 'exportar']);
    Route::apiResource('lista-precios', ListaPrecioController::class);

    // Rutas COMPRAS
    Route::prefix('compras')->group(function () {
        Route::get('/estadisticas',                 [\App\Http\Controllers\CompraController::class, 'estadisticas']);
        Route::get('/buscar-producto',              [\App\Http\Controllers\CompraController::class, 'buscarProducto']);
        Route::get('/alertas-precio',              [\App\Http\Controllers\CompraController::class, 'alertasPrecio']);
        Route::get('/sin-codigo',                  [\App\Http\Controllers\CompraController::class, 'sinCodigo']);
        Route::post('/matchear',                   [\App\Http\Controllers\CompraController::class, 'matchear']);
        Route::patch('/actualizar-costo',          [\App\Http\Controllers\CompraController::class, 'actualizarCosto']);
        Route::patch('/asignar-codigo',            [\App\Http\Controllers\CompraController::class, 'asignarCodigo']);
        Route::post('/sincronizar',                [\App\Http\Controllers\CompraController::class, 'sincronizar']);
        Route::post('/cargar-xmls-pendientes',     [\App\Http\Controllers\CompraController::class, 'cargarXmlsPendientes']);
        Route::post('/vincular-ncs',               [\App\Http\Controllers\CompraController::class, 'vincularNcsPendientes']);
        Route::post('/aplicar-ncs-revision',       [\App\Http\Controllers\CompraController::class, 'aplicarNcsPendientesRevision']);
        Route::patch('/{compraId}/categoria',      [\App\Http\Controllers\ReglaProveedorController::class, 'asignarCategoria']);
        Route::post('/{compra}/cargar-xml',        [\App\Http\Controllers\CompraController::class, 'cargarXml']);
        Route::get('/{compra}',                    [\App\Http\Controllers\CompraController::class, 'show']);
        Route::get('/',                            [\App\Http\Controllers\CompraController::class, 'index']);
    });

    Route::prefix('reglas-proveedor')->group(function () {
        Route::get('/',           [\App\Http\Controllers\ReglaProveedorController::class, 'index']);
        Route::get('/categorias', [\App\Http\Controllers\ReglaProveedorController::class, 'categorias']);
        Route::post('/',          [\App\Http\Controllers\ReglaProveedorController::class, 'store']);
        Route::put('/{id}',       [\App\Http\Controllers\ReglaProveedorController::class, 'update']);
        Route::delete('/{id}',    [\App\Http\Controllers\ReglaProveedorController::class, 'destroy']);
        Route::post('/aplicar',   [\App\Http\Controllers\ReglaProveedorController::class, 'aplicar']);
    });

    // Rutas BSALE
    Route::prefix('bsale')->group(function () {
        Route::get('/test-conexion', [\App\Http\Controllers\BsaleController::class, 'testConexion']);
        Route::get('/dynamic-attributes', [\App\Http\Controllers\BsaleController::class, 'getDynamicAttributes']);
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

    // Documentos de facturación
    Route::get('/cotizaciones/{id}/documentos-facturacion', [DocumentoFacturacionController::class, 'index']);
    Route::post('/cotizaciones/{id}/documentos-facturacion', [DocumentoFacturacionController::class, 'store']);
    Route::patch('/documentos-facturacion/{id}/emitir', [DocumentoFacturacionController::class, 'marcarEmitido']);
    Route::patch('/documentos-facturacion/{id}/vincular', [DocumentoFacturacionController::class, 'vincular']);
    Route::get('/documentos-facturacion/huerfanos', [DocumentoFacturacionController::class, 'huerfanos']);
    Route::delete('/documentos-facturacion/{id}', [DocumentoFacturacionController::class, 'destroy']);

    // Conciliación bancaria
    Route::prefix('conciliacion')->group(function () {
        Route::get('/test-conexion',      [\App\Http\Controllers\ConciliacionController::class, 'testConexion']);
        Route::get('/saldo',              [\App\Http\Controllers\ConciliacionController::class, 'saldo']);
        Route::post('/importar',          [\App\Http\Controllers\ConciliacionController::class, 'importar']);
        Route::post('/importar-cartola',  [\App\Http\Controllers\ConciliacionController::class, 'importarCartola']);
        Route::get('/movimientos',        [\App\Http\Controllers\ConciliacionController::class, 'index']);
        Route::patch('/movimientos/{id}', [\App\Http\Controllers\ConciliacionController::class, 'update']);
        Route::post('/auto-concilar',     [\App\Http\Controllers\ConciliacionController::class, 'autoConcilar']);
        Route::get('/flujo-caja',         [\App\Http\Controllers\ConciliacionController::class, 'flujoCaja']);
        Route::get('/cuentas',            [\App\Http\Controllers\ConciliacionController::class, 'cuentas']);
        Route::get('/sugerencias',        [\App\Http\Controllers\ConciliacionController::class, 'sugerencias']);
        // Reglas de categorización
        Route::get('/reglas',             [\App\Http\Controllers\ReglaConciliacionController::class, 'index']);
        Route::post('/reglas',            [\App\Http\Controllers\ReglaConciliacionController::class, 'store']);
        Route::put('/reglas/{id}',        [\App\Http\Controllers\ReglaConciliacionController::class, 'update']);
        Route::delete('/reglas/{id}',     [\App\Http\Controllers\ReglaConciliacionController::class, 'destroy']);
        Route::post('/reglas/aplicar',    [\App\Http\Controllers\ReglaConciliacionController::class, 'aplicar']);

        // Conciliar movimiento ↔ facturas (perspectiva desde movimiento)
        Route::get('/movimientos/{id}/compras',              [\App\Http\Controllers\CompraMovimientoController::class, 'indexPorMovimiento']);
        Route::get('/movimientos/{id}/compras-disponibles',  [\App\Http\Controllers\CompraMovimientoController::class, 'disponiblesPorMovimiento']);
        Route::post('/movimientos/{id}/compras',             [\App\Http\Controllers\CompraMovimientoController::class, 'storePorMovimiento']);
        Route::delete('/movimientos/{id}/compras/{pivotId}', [\App\Http\Controllers\CompraMovimientoController::class, 'destroyPorMovimiento']);
        // Conciliar movimiento ↔ gastos (perspectiva desde movimiento)
        Route::get('/movimientos/{id}/gastos',              [\App\Http\Controllers\GastoMovimientoController::class, 'indexPorMovimiento']);
        Route::get('/movimientos/{id}/gastos-disponibles',  [\App\Http\Controllers\GastoMovimientoController::class, 'disponiblesPorMovimiento']);
        Route::post('/movimientos/{id}/gastos',             [\App\Http\Controllers\GastoMovimientoController::class, 'storePorMovimiento']);
        Route::delete('/movimientos/{id}/gastos/{pivotId}', [\App\Http\Controllers\GastoMovimientoController::class, 'destroyPorMovimiento']);
        // Conciliar movimiento ↔ sueldos
        Route::get('/movimientos/{id}/sueldos',             [\App\Http\Controllers\SueldoMovimientoController::class, 'indexPorMovimiento']);
        Route::get('/movimientos/{id}/sueldos-disponibles', [\App\Http\Controllers\SueldoMovimientoController::class, 'disponiblesPorMovimiento']);
        Route::post('/movimientos/{id}/sueldos',            [\App\Http\Controllers\SueldoMovimientoController::class, 'storePorMovimiento']);
        Route::delete('/movimientos/{id}/sueldos/{pagoId}', [\App\Http\Controllers\SueldoMovimientoController::class, 'destroyPorMovimiento']);
        // Conciliar movimiento crédito ↔ ventas/facturas (excluye boletas)
        Route::get('/movimientos/{id}/ventas',              [\App\Http\Controllers\VentaMovimientoController::class, 'indexPorMovimiento']);
        Route::get('/movimientos/{id}/ventas-disponibles',  [\App\Http\Controllers\VentaMovimientoController::class, 'disponiblesPorMovimiento']);
        Route::post('/movimientos/{id}/ventas',             [\App\Http\Controllers\VentaMovimientoController::class, 'storePorMovimiento']);
        Route::delete('/movimientos/{id}/ventas/{pivotId}', [\App\Http\Controllers\VentaMovimientoController::class, 'destroyPorMovimiento']);
        // Conciliar movimiento crédito ↔ boletas (un documento agregado por mes)
        Route::get('/movimientos/{id}/boletas',              [\App\Http\Controllers\BoletaResumenController::class, 'asignadosPorMovimiento']);
        Route::get('/movimientos/{id}/boletas-disponibles',  [\App\Http\Controllers\BoletaResumenController::class, 'disponiblesPorMovimiento']);
        Route::post('/movimientos/{id}/boletas',             [\App\Http\Controllers\BoletaResumenController::class, 'vincularPorMovimiento']);
        Route::delete('/movimientos/{id}/boletas/{pivotId}', [\App\Http\Controllers\BoletaResumenController::class, 'destroyPorMovimiento']);
        // Conciliar movimiento crédito ↔ ingresos manuales (sin doc SII)
        Route::get('/movimientos/{id}/ingresos',              [\App\Http\Controllers\IngresoManualController::class, 'indexPorMovimiento']);
        Route::post('/movimientos/{id}/ingresos',             [\App\Http\Controllers\IngresoManualController::class, 'storePorMovimiento']);
        Route::delete('/movimientos/{id}/ingresos/{pivotId}', [\App\Http\Controllers\IngresoManualController::class, 'destroyPorMovimiento']);
    });

    // Ingresos manuales (para EERR y módulo)
    Route::get('/ingresos-manuales',         [\App\Http\Controllers\IngresoManualController::class, 'index']);
    Route::get('/ingresos-manuales-detalle', [\App\Http\Controllers\IngresoManualController::class, 'detalle']);
    Route::post('/ingresos-manuales',        [\App\Http\Controllers\IngresoManualController::class, 'store']);
    Route::put('/ingresos-manuales/{id}',    [\App\Http\Controllers\IngresoManualController::class, 'update']);
    Route::delete('/ingresos-manuales/{id}', [\App\Http\Controllers\IngresoManualController::class, 'destroy']);

    // Empleados
    Route::prefix('empleados')->group(function () {
        Route::get('/',                         [\App\Http\Controllers\EmpleadoController::class, 'index']);
        Route::post('/',                        [\App\Http\Controllers\EmpleadoController::class, 'store']);
        Route::put('/{id}',                     [\App\Http\Controllers\EmpleadoController::class, 'update']);
        Route::delete('/{id}',                  [\App\Http\Controllers\EmpleadoController::class, 'destroy']);
        Route::get('/{id}/pagos',               [\App\Http\Controllers\EmpleadoController::class, 'pagos']);
        Route::post('/{id}/pagos',              [\App\Http\Controllers\EmpleadoController::class, 'storePago']);
        Route::put('/pagos/{pagoId}',           [\App\Http\Controllers\EmpleadoController::class, 'updatePago']);
        Route::delete('/pagos/{pagoId}',        [\App\Http\Controllers\EmpleadoController::class, 'destroyPago']);
        Route::post('/generar-sueldos',         [\App\Http\Controllers\EmpleadoController::class, 'generarSueldos']);
        Route::get('/resumen-mensual',          [\App\Http\Controllers\EmpleadoController::class, 'resumenMensual']);
        Route::get('/pagos-por-periodo',        [\App\Http\Controllers\EmpleadoController::class, 'pagosPorPeriodo']);
    });

    // Cuentas por Pagar
    Route::get('/cuentas-por-pagar',                   [\App\Http\Controllers\CuentasPorPagarController::class, 'index']);
    Route::get('/cuentas-por-pagar/por-revisar',       [\App\Http\Controllers\CuentasPorPagarController::class, 'porRevisar']);
    Route::get('/cuentas-por-pagar/{rut}/facturas',    [\App\Http\Controllers\CuentasPorPagarController::class, 'facturas']);
    Route::get('/cuentas-por-pagar/{rut}/ncs',         [\App\Http\Controllers\CuentasPorPagarController::class, 'ncsDisponibles']);

    // Estado de Resultados
    Route::get('/eerr', [\App\Http\Controllers\EERRController::class, 'index']);

    // Sync ventas desde Bsale → documentos_facturacion
    Route::post('/ventas/sincronizar',            [\App\Http\Controllers\BsaleVentaSyncController::class, 'sincronizar']);
    Route::post('/ventas/backfill-comprobantes',  [\App\Http\Controllers\BsaleVentaSyncController::class, 'backfillComprobantes']);
    Route::post('/ventas/backfill-forma-pago',    [\App\Http\Controllers\BsaleVentaSyncController::class, 'backfillFormaPago']);

    // Boletas — resúmenes mensuales por forma de pago
    Route::prefix('boletas')->group(function () {
        Route::get('/resumenes',                                    [\App\Http\Controllers\BoletaResumenController::class, 'index']);
        Route::get('/resumenes/{id}/boletas',                       [\App\Http\Controllers\BoletaResumenController::class, 'boletas']);
        Route::get('/resumenes/{id}/estado',                        [\App\Http\Controllers\BoletaResumenController::class, 'estado']);
        Route::get('/resumenes/{id}/movimientos-disponibles',       [\App\Http\Controllers\BoletaResumenController::class, 'movimientosDisponibles']);
        Route::post('/resumenes/{id}/conciliar',                    [\App\Http\Controllers\BoletaResumenController::class, 'conciliar']);
        Route::delete('/resumenes/movimiento/{pivotId}',            [\App\Http\Controllers\BoletaResumenController::class, 'desvincular']);
        Route::post('/resumenes/recalcular',                        [\App\Http\Controllers\BoletaResumenController::class, 'recalcular']);
        Route::patch('/resumenes/{id}/conciliar-transbank',         [\App\Http\Controllers\BoletaResumenController::class, 'toggleConciliadoTransbank']);
    });

    // Cuentas por Cobrar
    Route::get('/cuentas-por-cobrar',                        [\App\Http\Controllers\CuentasPorCobrarController::class, 'index']);
    Route::get('/cuentas-por-cobrar/por-revisar',            [\App\Http\Controllers\CuentasPorCobrarController::class, 'porRevisar']);
    Route::get('/cuentas-por-cobrar/{clienteId}/facturas',   [\App\Http\Controllers\CuentasPorCobrarController::class, 'facturas']);
    Route::put('/cuentas-cobrar/{id}/cobro-manual',          [\App\Http\Controllers\CuentasPorCobrarController::class, 'marcarCobradoManual']);
    Route::delete('/cuentas-cobrar/{id}/cobro-manual',       [\App\Http\Controllers\CuentasPorCobrarController::class, 'desmarcarCobradoManual']);
    Route::get('/registro-ventas',                           [\App\Http\Controllers\CuentasPorCobrarController::class, 'registroVentas']);
    Route::get('/registro-compras',                          [\App\Http\Controllers\CuentasPorPagarController::class,  'registroCompras']);
    Route::get('/ventas/{id}/movimientos', [\App\Http\Controllers\VentaMovimientoController::class, 'index']);
    Route::get('/ventas/{id}/movimientos-disponibles', [\App\Http\Controllers\VentaMovimientoController::class, 'disponibles']);
    Route::post('/ventas/{id}/movimientos', [\App\Http\Controllers\VentaMovimientoController::class, 'store']);
    Route::delete('/ventas/{id}/movimientos/{pivotId}', [\App\Http\Controllers\VentaMovimientoController::class, 'destroy']);

    // Gastos Generales
    Route::prefix('gastos')->group(function () {
        Route::get('/',        [\App\Http\Controllers\GastoController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\GastoController::class, 'store']);
        Route::put('/{id}',    [\App\Http\Controllers\GastoController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\GastoController::class, 'destroy']);
        Route::get('/{id}/movimientos',              [\App\Http\Controllers\GastoMovimientoController::class, 'index']);
        Route::get('/{id}/movimientos-disponibles',  [\App\Http\Controllers\GastoMovimientoController::class, 'disponibles']);
        Route::post('/{id}/movimientos',             [\App\Http\Controllers\GastoMovimientoController::class, 'store']);
        Route::delete('/{id}/movimientos/{pivotId}', [\App\Http\Controllers\GastoMovimientoController::class, 'destroy']);
    });

    // Conciliación factura ↔ movimiento (pivot many-to-many)
    Route::get('/compras/{id}/movimientos', [\App\Http\Controllers\CompraMovimientoController::class, 'index']);
    Route::get('/compras/{id}/movimientos-disponibles', [\App\Http\Controllers\CompraMovimientoController::class, 'disponibles']);
    Route::post('/compras/{id}/movimientos', [\App\Http\Controllers\CompraMovimientoController::class, 'store']);
    Route::delete('/compras/{id}/movimientos/{pivotId}', [\App\Http\Controllers\CompraMovimientoController::class, 'destroy']);

    // ── Notas de Crédito ─────────────────────────────────────────────────────
    Route::prefix('nc')->group(function () {
        // Badge global
        Route::get('/compra/badge',                       [\App\Http\Controllers\NcController::class, 'badgeCompra']);
        Route::get('/venta/badge',                        [\App\Http\Controllers\NcController::class, 'badgeVenta']);
        // Compras (DTE 61)
        Route::post('/compra/{nc_id}/vincular',           [\App\Http\Controllers\NcController::class, 'vincularCompra']);
        Route::delete('/compra/{nc_id}/vincular',         [\App\Http\Controllers\NcController::class, 'desvincularCompra']);
        Route::post('/compra/{nc_id}/aplicar',            [\App\Http\Controllers\NcController::class, 'aplicarCompra']);
        Route::delete('/compra/aplicacion/{id}',          [\App\Http\Controllers\NcController::class, 'eliminarAplicacionCompra']);
        Route::patch('/compra/factura/{id}/estado',       [\App\Http\Controllers\NcController::class, 'estadoFacturaCompra']);
        // Ventas (tipo Bsale 2)
        Route::post('/venta/{nc_id}/vincular',            [\App\Http\Controllers\NcController::class, 'vincularVenta']);
        Route::delete('/venta/{nc_id}/vincular',          [\App\Http\Controllers\NcController::class, 'desvincularVenta']);
        Route::post('/venta/{nc_id}/aplicar',             [\App\Http\Controllers\NcController::class, 'aplicarVenta']);
        Route::delete('/venta/aplicacion/{id}',           [\App\Http\Controllers\NcController::class, 'eliminarAplicacionVenta']);
        Route::patch('/venta/factura/{id}/estado',        [\App\Http\Controllers\NcController::class, 'estadoFacturaVenta']);
    });

    // Conciliación: créditos bancarios ↔ NCs de proveedores (DTE 61)
    // Se usa el CompraMovimientoController ya existente, pero el frontend filtra por tipo_dte=61
    Route::get('/conciliacion/movimientos/{id}/nc-compras-disponibles',
        [\App\Http\Controllers\CompraMovimientoController::class, 'ncDisponiblesPorMovimiento']);

    // Operaciones
    Route::get('/operaciones', [OperacionesController::class, 'index']);
    Route::patch('/operaciones/{id}', [OperacionesController::class, 'update']);
    Route::post('/operaciones/{id}/abonos', [OperacionesController::class, 'storeAbono']);
    Route::delete('/operaciones/abonos/{abonoId}', [OperacionesController::class, 'destroyAbono']);

    // routes/api.php
    Route::get('/dashboard/ventas-mensuales', [DashboardController::class, 'ventasMensuales']);
    Route::get('/dashboard-financiero', [DashboardFinancieroController::class, 'index']);
    Route::get('/compras-terceros-mensuales', [DashboardController::class, 'comprasTercerosMensuales']);

    // DEBUG temporal Bsale (eliminar después)
    Route::get('/bsale/debug/payments/{id}', [\App\Http\Controllers\BsaleController::class, 'debugPayments']);

    // ── Winperfil API Integration ──────────────────────────────────────────────
    Route::prefix('winperfil')->group(function () {
        // Debug (temporal — ver estructura real de respuesta)
        Route::get('/debug-raw',                [WinperfilController::class, 'debugRaw']);

        // Conectividad
        Route::get('/test',                     [WinperfilController::class, 'testConexion']);

        // Proxy (lectura directa desde Winperfil, sin persistir)
        Route::get('/presupuestos',             [WinperfilController::class, 'getPresupuestos']);
        Route::get('/presupuesto',              [WinperfilController::class, 'getPresupuesto']);
        Route::get('/pedidos',                  [WinperfilController::class, 'getPedidos']);
        Route::get('/clientes',                 [WinperfilController::class, 'getClientes']);

        // Sincronización (persiste en BD)
        Route::post('/sync/clientes',           [WinperfilController::class, 'syncClientes']);
        Route::post('/sync/presupuestos',       [WinperfilController::class, 'syncPresupuestos']);
        Route::post('/sync/pedidos',            [WinperfilController::class, 'syncPedidos']);
        Route::post('/sync/todo',               [WinperfilController::class, 'syncTodo']);
        Route::post('/sync/resync',             [WinperfilController::class, 'resyncSincronizados']);

        // Listado de cotizaciones sincronizadas
        Route::get('/cotizaciones',             [WinperfilController::class, 'cotizacionesSincronizadas']);
    });

    // Banco de Chile — importadores
    Route::post('/banco/importar-portal', [BancochilePortalController::class, 'importar']);
    Route::post('/banco/importar-json',   [BancochilePortalController::class, 'importarJson']);

    // Transbank
    Route::prefix('transbank')->group(function () {
        Route::get('/',                [\App\Http\Controllers\TransbankController::class, 'index']);
        Route::post('/subir',          [\App\Http\Controllers\TransbankController::class, 'subir']);
        Route::delete('/{id}',         [\App\Http\Controllers\TransbankController::class, 'destroy']);
        Route::get('/{id}/abonos',     [\App\Http\Controllers\TransbankController::class, 'abonos']);
        Route::get('/depositos',       [\App\Http\Controllers\TransbankController::class, 'depositos']);
        Route::get('/resumen-sii',                  [\App\Http\Controllers\TransbankController::class, 'resumenSii']);
        Route::get('/documentos',                   [\App\Http\Controllers\TransbankController::class, 'documentos']);
        Route::get('/resumen-documentos',           [\App\Http\Controllers\TransbankController::class, 'resumenDocumentos']);
        Route::post('/auto-link',                   [\App\Http\Controllers\TransbankController::class, 'autoLink']);
        Route::get('/facturas-disponibles',         [\App\Http\Controllers\TransbankController::class, 'facturasDisponibles']);
        Route::post('/transaccion/{id}/link',       [\App\Http\Controllers\TransbankController::class, 'linkDocumento']);
        Route::delete('/transaccion/{id}/link',     [\App\Http\Controllers\TransbankController::class, 'unlinkDocumento']);
        Route::get('/transacciones-sin-doc',               [\App\Http\Controllers\TransbankController::class, 'transaccionesSinDoc']);
        Route::post('/transaccion/{id}/ingreso-manual',    [\App\Http\Controllers\IngresoManualController::class, 'storePorTransaccion']);
        Route::post('/auto-match',     [\App\Http\Controllers\TransbankController::class, 'autoMatch']);
        Route::post('/deposito/match', [\App\Http\Controllers\TransbankController::class, 'matchDeposito']);
        Route::post('/chipax-csv',     [\App\Http\Controllers\TransbankController::class, 'importarChipaxCsv']);
    });






    

});
