# Documentación Completa — Proyecto Vialum

> Última actualización: 17 Abril 2026

---

## 1. Stack Tecnológico

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Frontend | Vue 3.5 + Vite 5.4 + Vuetify 3.7 |
| Auth | JWT (`tymon/jwt-auth`) |
| Base de datos | MySQL (Railway en producción) |
| CSS Icons | Material Design Icons (`@mdi/font`) |
| PDF | DomPDF (`barryvdh/laravel-dompdf`) |
| PDF parsing | `smalot/pdfparser` (extracción texto de PDFs WINPERFIL) |
| File storage | Cloudflare R2 (S3-compatible, `league/flysystem-aws-s3-v3`) |
| Package manager (FE) | pnpm |
| Deploy | Railway (git push → autobuild) |
| ERP externo | Bsale (facturación electrónica Chile) |
| 3D / Canvas | Three.js 0.183, Konva 9.3, Vue-Konva 3.2 |
| Gráficos | ApexCharts 4.7, Chart.js 4.4 |
| CSV parsing | PapaParse 5.5 |
| State management | Pinia 2.3 |

---

## 2. Estructura de Directorios Clave

```
proyectovialum/
├── app/
│   ├── Http/
│   │   ├── Controllers/       ← 25 controladores Laravel
│   │   └── Middleware/        ← CheckPermission
│   ├── Models/                ← 22 modelos Eloquent
│   ├── Services/              ← CalculoVentanaService, CortesService, BsaleClientService
│   └── Console/Commands/      ← Comandos artisan (Bsale sync)
├── database/migrations/       ← 47 migraciones
├── routes/
│   ├── api.php                ← Todas las rutas API (protegidas con auth:api)
│   └── web.php                ← PDF, importación, catch-all SPA
├── public/assets/             ← Build del frontend (committado al repo)
└── vuexy-frontend/
    ├── src/
    │   ├── pages/             ← 40+ páginas Vue (file-based routing)
    │   ├── layouts/           ← default.vue, blank.vue
    │   ├── @core/stores/      ← Pinia store config (tema)
    │   ├── @layouts/stores/   ← Pinia store layout
    │   └── axiosInstance.js   ← Axios con interceptor JWT + auto-logout
    └── vite.config.js
```

---

## 3. Modelos y Relaciones

### `User`
- **Tabla:** `users`
- **Fillable:** `name`, `email`, `password`, `role_id`
- **Casts:** `email_verified_at`→datetime, `password`→hashed
- **Relaciones:** `role()` belongsTo `Role`
- Implementa `JWTSubject` (`getJWTIdentifier`, `getJWTCustomClaims`)

### `Role`
- **Tabla:** `roles`
- **Fillable:** `nombre`
- **Relaciones:** `permissions()` belongsToMany `Permission` · `users()` hasMany `User`
- **Métodos:** `hasPermission(string $nombre): bool`

### `Permission`
- **Tabla:** `permissions`
- **Fillable:** `nombre`, `descripcion`
- **Relaciones:** `roles()` belongsToMany `Role`

### `Cliente`
- **Tabla:** `clientes`
- **Fillable:** `bsale_id`, `tipo_cliente`, `first_name`, `last_name`, `email`, `identification`, `phone`, `address`, `razon_social`, `giro`, `ciudad`, `comuna`
- **Relaciones:** `cotizaciones()` hasMany `Cotizacion`

### `Cotizacion`
- **Tabla:** `cotizaciones`
- **Fillable:** `cliente_id`, `cliente_facturacion_id`, `vendedor_id`, `fecha`, `estado_cotizacion_id`, `observaciones`, `total`, `numero_documento_bsale`, `id_documento_bsale`, `fecha_documento_bsale`, `estado_facturacion`, `url_pdf_bsale`, `token_bsale`, `adjunto_winperfil`
- **`adjunto_winperfil`:** URL pública en Cloudflare R2 del PDF exportado desde WINPERFIL (VARCHAR 500, nullable)
- **Relaciones:**
  - `cliente()` belongsTo `Cliente`
  - `clienteFacturacion()` belongsTo `Cliente` (FK: `cliente_facturacion_id`)
  - `vendedor()` belongsTo `User`
  - `ventanas()` hasMany `Ventana`
  - `estado()` belongsTo `EstadoCotizacion`
  - `detalles()` / `cotizacionDetalles()` hasMany `CotizacionDetalle`

### `Ventana`
- **Tabla:** `ventanas`
- **Fillable:** `cotizacion_id`, `tipo_ventana_id`, `ancho`, `alto`, `color_id`, `producto_vidrio_proveedor_id`, `imagen`, `costo`, `precio`, `hojas_totales`, `hojas_moviles`, `hoja_movil_seleccionada`, `hoja1_al_frente`, `cantidad`, `costo_unitario`, `precio_unitario`, `config`, `ancho_izquierda`, `ancho_centro`, `ancho_derecha`, `tipo_ventana_izquierda`, `tipo_ventana_centro`, `tipo_ventana_derecha`
- **Casts:** `costo`, `precio`, `costo_unitario`, `precio_unitario` → decimal:2 · `config` → array · `hoja1_al_frente` → boolean · `tipo_ventana_izquierda`, `tipo_ventana_centro`, `tipo_ventana_derecha` → array (auto-decode JSON)
- **Relaciones:**
  - `tipoVentana()` / `tipo_ventana()` belongsTo `TipoVentana`
  - `cotizacion()` belongsTo `Cotizacion`
  - `color()` belongsTo `Color`
  - `productoVidrioProveedor()` belongsTo `ProductoColorProveedor`
  - `materiales()` belongsToMany `Producto` (tabla: `ventana_materiales`)

### `Producto`
- **Tabla:** `productos`
- **Fillable:** `nombre`, `tipo_producto_id`, `unidad_id`, `largo_total`, `peso_por_metro`, `codigo_proveedor`
- **Relaciones:**
  - `coloresPorProveedor()` hasMany `ProductoColorProveedor`
  - `productoColor()` hasMany `ProductoColorProveedor`
  - `unidad()` belongsTo `Unidad`
  - `tipoProducto()` belongsTo `TipoProducto`
  - `listaPrecios()` hasMany `ListaPrecio`
- **Boot:** Elimina en cascada `coloresPorProveedor` al borrar el producto

### `ProductoColorProveedor`
- **Tabla:** `producto_color_proveedor`
- **Fillable:** `producto_id`, `proveedor_id`, `color_id`, `codigo_proveedor`, `costo`, `stock`
- **Relaciones:** `producto()` · `proveedor()` · `color()`

### `ListaPrecio`
- **Tabla:** `lista_precios`
- **Fillable:** `producto_id`, `producto_color_proveedor_id`, `color_id`, `proveedor_sugerido_id`, `precio_costo`, `margen`, `precio_venta`, `vigencia_desde`, `vigencia_hasta`, `activo`
- **Casts:** `precio_costo`, `margen`, `precio_venta` → decimal:2 · fechas → date · `activo` → boolean
- **Relaciones:** `producto()` · `color()` · `proveedorSugerido()` · `productoColorProveedor()` · `cotizacionDetalles()` hasMany `CotizacionDetalle`
- **Static:** `calcularCostoMaximo($producto_id, $color_id)`

### `CotizacionDetalle`
- **Tabla:** `cotizacion_detalles`
- **Fillable:** `cotizacion_id`, `producto_id`, `tipo_item`, `producto_lista_id`, `lista_precio_id`, `descripcion`, `cantidad`, `precio_unitario`, `total`, `esVidrio`, `ancho_mm`, `alto_mm`, `m2`, `pulido`
- **`tipo_item`:** ENUM `'ventana' | 'producto' | 'winperfil'`
- **Casts:** numéricos → decimal · `esVidrio`, `pulido` → boolean · `m2` → decimal:4
- **Relaciones:** `cotizacion()` · `producto()` · `productoLista()` (FK: `producto_lista_id`) · `listaPrecio()`

### `Color`
- **Tabla:** `colores` · **Fillable:** `nombre`
- **Relaciones:** `productos()` belongsToMany `Producto`

### `Proveedor`
- **Tabla:** `proveedors` · **Fillable:** `nombre`, `contacto`
- **Relaciones:** `productosPorColor()` hasMany `ProductoColorProveedor`

### `TipoVentana`
- **Tabla:** `tipos_ventana` · **Fillable:** `nombre`, `material_id`, `descripcion`
- **Relaciones:** `ventanas()` hasMany `Ventana` · `material()` belongsTo `Material`

### `EstadoCotizacion`
- **Tabla:** `estados_cotizacion` · **Fillable:** `nombre`

### Modelos secundarios
`TipoProducto` · `Unidad` · `Material` · `TipoMaterial` · `AccesorioDependiente` · `VentanaAccesorio` · `VentanaMaterial` · `VentanaTipoApertura` (esqueletos, sin lógica activa)

---

## 4. Tipos de Ventana Soportados

| ID | Nombre | `calcularMateriales()` | Hoja de Cortes (`CortesService`) |
|---|---|---|---|
| 1 | Fija AL42 | ✅ | ✅ |
| 2 | Fija S60 | ✅ | ✅ |
| 3 | Corredera Sliding E15 | ✅ | ✅ |
| 45 | Proyectante S60 | ✅ | ❌ (omitida) |
| 46 | Corredera Andes | ✅ | ❌ (omitida) |
| 47 | Bay Window | ✅ | ❌ (omitida) |
| 49 | Abatir S60 | ✅ | ❌ (omitida) |
| 50 | Puerta S60 | ✅ | ❌ (omitida) |
| 51 | Puerta 2 Hojas S60 | ✅ | ❌ (omitida) |
| 52 | Corredera Sliding 98 | ✅ | ❌ (omitida) |
| 53 | Corredera Monorriel | ✅ | ❌ (omitida) |
| 55 | Corredera AL25 | ✅ | ✅ |
| 56 | Proyectante AL42 | ✅ | ✅ |
| 57 | Compuesta AL42 | ✅ | ❌ (omitida) |
| 58 | Universal (Armador) | ✅ | ❌ (omitida) |

> **Pendiente:** Ampliar `CortesService::extraerCortes()` para los tipos marcados con ❌.

---

## 5. Services

### `CalculoVentanaService`
**Archivo:** `app/Services/CalculoVentanaService.php`

**Método principal:** `calcularMateriales(array $ventana): array`

**Input esperado:**
```php
[
  'tipo'            => int,    // tipo_ventana_id
  'ancho'           => float,  // mm
  'alto'            => float,  // mm
  'color'           => int,    // color_id
  'cantidad'        => int,
  'productoVidrio'  => int,    // producto_id del vidrio
  'proveedorVidrio' => int,    // proveedor_id
  'tipoVidrio'      => int,    // 1=Monolítico, 2=Termopanel
  'manillon'        => bool,   // true=manillón, false=pestillo
  'hojas_totales'   => int,
  'hojas_moviles'   => int,
]
```

**Output por material:**
```php
[
  'producto_id'    => int,
  'nombre'         => string,
  'unidad'         => 'm' | 'm2' | 'unidad' | 'par',
  'cantidad'       => float,
  'costo_unitario' => int,
  'costo_total'    => int,
  'proveedor'      => string,
]
```

**Métodos internos por tipo:**
- `calcularFijaAL42()` — Perfiles IDs: 148, 151/153. Herrajes universales IDs: 36–44.
- `calcularFijaS60()` — Perfiles IDs: 32, 34, 35/33. Herrajes universales IDs: 36–44.
- `calcularCorrederaSliding()` — Perfiles IDs: 46–52. Herrajes cremonas, carros, manillas.
- `calcularProyectanteAL42()` — Brazos DT seleccionados por tabla peso/alto. IDs: 221–226.
- `calcularCorrederaAL25()` — 4 casos según `tipoVidrio` + `manillon`. Perfiles IDs: 154–176. Incluye burlete (ID según tipo/espesor vidrio) y zócalo termopanel ID 164 (cant=4).
- `calcularProyectanteS60()`, `calcularCorrederaAndes()`, `calcularCorrederaMonorriel()`, `calcularAbatirS60()`, `calcularPuertaS60()`, `calcularPuerta2HojasS60()`, `calcularCorrederaSliding98()`, `calcularBayWindow()`, `calcularCompuestaAL42()`, `calcularVentanaUniversal()`

**Helper methods:**
- `buscarCostoPorColor($producto, $colorId, $proveedorId=null)` — busca costo en `coloresPorProveedor`
- `buscarNombreProveedor($producto, $colorId)` — retorna nombre del proveedor
- `crearHerraje($producto, $alto, $ancho, $cantidad, $colorId)` — crea entrada de herraje
- `calcularCantidadTapaDesague($ancho)` — 2 tapas si ancho ≥ 1000, sino 1

---

### `CortesService`
**Archivo:** `app/Services/CortesService.php`

**Método principal:** `generarHojaCortes(int $cotizacionId): array`

**Constantes:**
- `KERF_MM = 4` — viruta por corte de sierra

**Algoritmo:** First Fit Decreasing (FFD) para optimización de barras.

**Output:**
```php
[
  'cotizacion'        => ['id', 'cliente', 'fecha'],
  'grupos'            => [
    [
      'producto_id'  => int,
      'nombre'       => string,
      'proveedor'    => string,
      'color'        => string,
      'largo_barra'  => int,    // mm
      'barras'       => [
        [
          'numero'    => int,
          'cortes'    => [
            ['largo_mm', 'ventana_ref', 'posicion', 'angulo_izq', 'angulo_der']
          ],
          'uso_mm'    => int,
          'retal_mm'  => int,
          'virutas_mm'=> int,
        ]
      ],
      'total_barras' => int,
    ]
  ],
  'ventanas_omitidas' => [
    ['ref', 'tipo', 'ancho', 'alto']
  ],
]
```

**Tipos soportados en `extraerCortes()`:** 1, 2, 3, 55, 56.
**Tipos no soportados (aparecen en `ventanas_omitidas`):** 45, 46, 47, 49, 50, 51, 52, 53, 57, 58.

---

### `BsaleClientService`
**Archivo:** `app/Services/BsaleClientService.php`

**Constructor:** Lee token y baseUrl de Bsale desde configuración de entorno.

**Métodos públicos:**
- `getClient($clientId)` — Obtiene cliente específico de Bsale por ID
- `getClients($limit=50, $offset=0)` — Obtiene página de clientes
- `crearCliente(array $data)` — Crea cliente en Bsale

**Métodos privados:**
- `getAllClients()` — Pagina automáticamente todos los clientes
- `processClientItems($items)` — Mapea array de clientes a formato local
- `processClientItem($item)` — Mapea un cliente individual

---

## 6. Controladores

### `AuthController`
- `login(Request)` — Autentica, retorna token JWT + datos de usuario + permisos del rol
- `me()` — Retorna usuario autenticado con rol y permisos

### `UserController`
- `index()` — Lista usuarios con roles
- `store(Request)` — Crea usuario con `role_id` válido
- `update(Request, $id)` — Actualiza nombre, email, password, rol
- `destroy($id)` — Elimina usuario (previene auto-eliminación)
- `getRoles()` — Lista todos los roles
- `getPermissions()` — Lista todos los permisos
- `getRolePermissions($roleId)` — Permisos de un rol específico
- `updateRolePermissions(Request, $roleId)` — Sincroniza permisos a un rol

### `CotizacionController`
- `index()` — Lista cotizaciones con relaciones (cliente, vendedor, ventanas, estado)
- `store(Request)` — Crea cotización completa con ventanas, productos e imágenes (transacción DB)
- `show($id)` — Cotización completa con todos los detalles
- `update(Request, $id)` — Actualiza cotización, ventanas y productos. Guarda `config`, `hojas_totales/moviles`. Recalcula `total` post-transacción
- `generarPDF($id)` — PDF con imágenes embebidas en base64
- `generarOrdenTrabajo($id)` — PDF de orden de trabajo
- `cambiarEstado(Request, $id)` — Cambia estado validando transiciones permitidas
- `duplicar($id)` — Copia cotización en estado "Evaluación"
- `getAprobadas()` — Cotizaciones en estado aprobada/facturada/pagada
- `parseWinperfil(Request)` — Recibe PDF, extrae texto con `smalot/pdfparser`, retorna JSON con items, cliente, fecha, total
- `parsearTextoWinperfil(string)` — *(privado)* Regex sobre texto plano: detecta ítems `Vx cant precio CLP$`, busca hacia atrás título/serie/color/medida/superficie. Filtra líneas de header/footer/atributos
- `importarWinperfil(Request)` — Crea cotización + detalles `tipo_item='winperfil'`, sube PDF a Cloudflare R2, guarda URL en `adjunto_winperfil`
- `actualizarWinperfil(Request, $id)` — Actualiza cotización WINPERFIL existente: reemplaza detalles winperfil, actualiza cliente/fecha/obs/total, opcionalmente reemplaza PDF en R2

**Recálculo total en `update()`:**
```php
$total = sum(ventanas.precio * cantidad) + sum(cotizacionDetalles.total)
cotizacion->update(['total' => $total])
```

### `ProduccionController`
- `hojaCortes($id)` — Llama a `CortesService::generarHojaCortes()`
- `resumenMateriales($id)` — Agrupa materiales por `producto_id + color_id`, calcula barras a pedir, ordena por categoría

**`resumenMateriales()` paso a paso:**
1. Carga cotización con relaciones (ventanas, cliente, vidrio)
2. Por cada ventana, reconstruye array para `CalculoVentanaService::calcularMateriales()`
3. Agrega materiales por `producto_id + color_id`
4. Inyecta `color_id` de la ventana (el service no lo devuelve)
5. Enriquece con `Producto::with(['tipoProducto','unidad'])` y `Color`
6. Calcula `barras = ceil(cantidad_metros / largo_total_m)` para `unidad === 'm'`
7. Ordena: perfiles → herrajes → vidrios → otros

### `ClienteController`
- `index()` — Lista clientes (con `bsale_id` no nulo)
- `show(Cliente)` — Cliente con cotizaciones y vendedores
- `store(Request)` — Crea cliente localmente
- `update(Request, Cliente)` — Actualiza cliente
- `buscar(Request)` — Búsqueda por `razon_social`, nombre, RUT (`?q=`)
- `importarTodos(BsaleClientService)` — Importa clientes de Bsale paginados
- `crearClienteBsale(Request, BsaleClientService)` — Crea en Bsale y localmente
- `sincronizarBsale()` — Ejecuta `bsale:sincronizar-clientes` y retorna estadísticas

### `ListaPrecioController`
- `index(Request)` — Lista precios con filtros: activo, producto, búsqueda
- `store(Request)` — Crea precio calculando margen y costo máximo
- `show($id)` — Precio con relaciones
- `update(Request, $id)` — Actualiza margen y recalcula precio de venta
- `destroy($id)` — Elimina precio
- `importarDesdeProductoColorProveedor(Request)` — Carga precios desde combos producto-color-proveedor
- `exportar()` — Exporta precios a JSON

### `ProductoController`
- `index()` — Productos con colores por proveedor, unidad, tipo
- `store(Request)` — Crea producto y combinaciones color-proveedor
- `show($id)` — Producto con relaciones
- `update(Request, $id)` — Actualiza producto y mantiene IDs de combos
- `destroy($id)` — Elimina con cascada a combos
- `getProveedoresPorProductoYColor($productoId, $colorId)` — Proveedores disponibles para esa combinación

### `BsaleController`
- `testConexion()` — Verifica conectividad con Bsale
- `getTiposDocumento()` — Tipos de documento disponibles en Bsale
- `getOficinas()` — Oficinas/sucursales en Bsale
- `getClientes()` — Clientes en Bsale
- `getClientesSincronizados()` — Clientes locales con `bsale_id`
- `crearCliente(Request)` — Crea cliente en Bsale
- `crearDocumentoDesdeCotzacion(Request)` — Genera factura/boleta en Bsale desde cotización
- `getDocumento($id)` — Obtiene documento de Bsale
- `descargarPdf($id)` — Descarga PDF desde Bsale
- `enviarEmail(Request, $id)` — Envía documento por email vía Bsale

### `BsaleClientController`
- `index()` — Lista clientes de Bsale
- `search(Request)` — Busca clientes en Bsale (`?q=`)
- `getOffices()` — Oficinas
- `getDocumentTypes()` — Tipos de documento
- `store(Request)` — Crea cliente en Bsale

### `DashboardController`
- `ventasMensuales(Request)` — Agrega ventas por cliente desde Bsale (`?mes=&anio=`)
- `comprasTercerosMensuales(Request)` — Agrega compras a terceros (`?mes=&anio=`)

### `ProveedorController`
- `index()` · `store(Request)` · `update(Request, Proveedor)` · `destroy(Proveedor)`

### `ImportacionController`
- `importarProductos(Request)` — Importa CSV de productos
- `importarProductoColorProveedor(Request)` — Importa CSV de combos color-proveedor

### `VentanaController`
- `update(Request, $id)` — Actualiza ventana individual

### Controladores menores (CRUD básico)
`ColorController` (index, store) · `UnidadController` (index) · `TipoVentanaController` (index) · `EstadoCotizacionController` (index) · `CotizadorController` (tiposMaterial, tiposProducto, calcularMateriales)

### Controladores esqueleto (sin implementación activa)
`MaterialController` · `VentanaAccesorioController` · `VentanaMaterialController` · `VentanaTipoAperturaController` · `CotizacionDetalleController`

---

## 7. Rutas API Completas

```
POST   /api/login
GET    /api/me

// Admin (permiso: gestionar_usuarios)
GET    /api/admin/users
POST   /api/admin/users
PUT    /api/admin/users/{id}
DELETE /api/admin/users/{id}
GET    /api/admin/roles
GET    /api/admin/permissions
GET    /api/admin/roles/{id}/permissions
PUT    /api/admin/roles/{id}/permissions

// Cotizaciones
GET    /api/cotizaciones
POST   /api/cotizaciones
GET    /api/cotizaciones/{id}
PUT    /api/cotizaciones/{id}
DELETE /api/cotizaciones/{id}
GET    /api/cotizaciones/aprobadas
GET    /api/cotizaciones/{id}/pdf
GET    /api/cotizaciones/{id}/orden-trabajo
GET    /api/cotizaciones/{id}/hoja-cortes       ← ProduccionController
GET    /api/cotizaciones/{id}/materiales        ← ProduccionController
POST   /api/cotizaciones/{id}/duplicar
PATCH  /api/cotizaciones/{id}/estado
POST   /api/cotizaciones/parse-winperfil           ← parseo PDF WINPERFIL (sin guardar)
POST   /api/cotizaciones/importar-winperfil        ← crear cotización desde WINPERFIL
POST   /api/cotizaciones/{id}/actualizar-winperfil ← editar cotización WINPERFIL existente

// Catálogos
GET    /api/estados-cotizacion
PUT    /api/ventanas/{id}
GET    /api/productos
GET    /api/productos/{id}
POST   /api/productos                           (permiso: gestionar_productos)
PUT    /api/productos/{id}                      (permiso: gestionar_productos)
DELETE /api/productos/{id}                      (permiso: gestionar_productos)
GET    /api/proveedores/{productoId}/{colorId}
GET    /api/proveedores
POST   /api/proveedores
PUT    /api/proveedores/{proveedor}
DELETE /api/proveedores/{proveedor}
GET    /api/colores
POST   /api/colores
GET    /api/unidades
GET    /api/tipos_ventana
GET    /api/tipos_material
GET    /api/tipos_producto

// Cotizador
POST   /api/cotizador/calcular-materiales

// Clientes
GET    /api/clientes
GET    /api/clientes/{cliente}
POST   /api/clientes
PUT    /api/clientes/{cliente}
GET    /api/clientes/buscar?q=
POST   /api/clientes/importar-todos
POST   /api/clientes/sincronizar-bsale
POST   /api/bsale-clientes/crear

// Bsale clientes
GET    /api/bsale-clientes
GET    /api/bsale-clientes/buscar?q=
GET    /api/bsale-oficinas
GET    /api/bsale-tipos-documento
POST   /api/bsale-clientes

// Bsale operaciones
GET    /api/bsale/test-conexion
GET    /api/bsale/tipos-documento
GET    /api/bsale/oficinas
GET    /api/bsale/clientes
GET    /api/bsale/clientes-sincronizados
POST   /api/bsale/clientes
POST   /api/bsale/documento
GET    /api/bsale/documento/{id}
GET    /api/bsale/documento/{id}/pdf
POST   /api/bsale/documento/{id}/enviar-email

// Lista de Precios
GET    /api/lista-precios
POST   /api/lista-precios
GET    /api/lista-precios/{id}
PUT    /api/lista-precios/{id}
DELETE /api/lista-precios/{id}
POST   /api/lista-precios/importar
GET    /api/lista-precios/exportar

// Importación
POST   /api/importar-productos
POST   /api/importar-pcp

// Dashboard
GET    /api/dashboard/ventas-mensuales?mes=&anio=
GET    /api/compras-terceros-mensuales?mes=&anio=
```

---

## 8. Rutas Web (`routes/web.php`)

```
GET  /cotizaciones/{id}/pdf      → CotizacionController@generarPDF
POST /importar-productos         → ImportacionController@importarProductos
POST /importar-pcp               → ImportacionController@importarProductoColorProveedor
GET  /{any}                      → catch-all SPA (retorna public/index.html)
```

---

## 9. Middleware

### `CheckPermission`
**Archivo:** `app/Http/Middleware/CheckPermission.php`

- Verifica autenticación JWT (`auth:api`)
- Verifica que el usuario tenga rol asignado
- Verifica que el rol tenga el permiso requerido vía `$user->role->hasPermission($permission)`
- Retorna 401 (sin auth), 403 (sin permiso) o permite continuar

**Uso en rutas:**
```php
Route::middleware(['auth:api', 'permission:gestionar_usuarios'])
Route::middleware(['auth:api', 'permission:gestionar_productos'])
```

---

## 10. Comandos Artisan

### `bsale:sincronizar-clientes`
**Clase:** `SincronizarClientesBsale`

- **Signature:** `bsale:sincronizar-clientes {--limit= : Número máximo}`
- Pagina automáticamente (50 por página)
- Busca existentes por `bsale_id` o `identification`
- Crea o actualiza clientes localmente
- Reporta: total procesados, nuevos, actualizados, errores

### Comandos esqueleto (sin implementación)
- `ActualizarRazonSocialClientes`
- `MigrarClientesLocalesABsale`

---

## 11. Páginas Frontend (Vue — File-based routing)

| Archivo | Ruta | Descripción |
|---|---|---|
| `pages/index.vue` | `/` | Home / Dashboard |
| `pages/login.vue` | `/login` | Login JWT |
| `pages/cotizador/index.vue` | `/cotizador` | Creador de cotizaciones |
| `pages/cotizaciones/index.vue` | `/cotizaciones` | Listado de cotizaciones |
| `pages/cotizaciones/importar-pvc.vue` | `/cotizaciones/importar-pvc` | Importar/editar cotización WINPERFIL (aluminio o PVC) |
| `pages/cotizacion-ver.vue` | `/cotizacion-ver` | Detalle de cotización |
| `pages/clientes/index.vue` | `/clientes` | Listado clientes |
| `pages/clientes/[id].vue` | `/clientes/:id` | Detalle cliente |
| `pages/productos/index.vue` | `/productos` | Gestión productos |
| `pages/listado-productos.vue` | `/listado-productos` | Listado simple productos |
| `pages/proveedores/index.vue` | `/proveedores` | Gestión proveedores |
| `pages/lista-precios/index.vue` | `/lista-precios` | Gestión lista de precios |
| `pages/produccion/index.vue` | `/produccion` | Control de producción |
| `pages/produccion/[id].vue` | `/produccion/:id` | Hoja de cortes |
| `pages/produccion/materiales/[id].vue` | `/produccion/materiales/:id` | Resumen materiales |
| `pages/facturacion/index.vue` | `/facturacion` | Facturación Bsale |
| `pages/dashboardventas/index.vue` | `/dashboardventas` | Dashboard ventas (Bsale) |
| `pages/comprasmensuales/index.vue` | `/comprasmensuales` | Compras mensuales |
| `pages/importador/index.vue` | `/importador` | Importación CSV |
| `pages/visor3d/index.vue` | `/visor3d` | Visor 3D (Konva) |
| `pages/admin-secret-panel.vue` | `/admin-secret-panel` | Panel administración |

---

## 12. Módulo de Producción

### `produccion/index.vue`
- Lista cotizaciones aprobadas
- Dos botones por fila:
  - **Hoja de Cortes** → `/produccion/{id}`
  - **Materiales** → `/produccion/materiales/{id}`

### `produccion/[id].vue` — Hoja de Cortes
- Llama a `GET /api/cotizaciones/{id}/hoja-cortes?_t={timestamp}`
- Muestra grupos de perfiles con diagrama FFD (barras con bloques de colores)
- Alerta de `ventanas_omitidas` si hay tipos no soportados
- Botones: Actualizar, Imprimir
- `watch(route.params.id, fetchData)` para re-fetch automático

### `produccion/materiales/[id].vue` — Resumen de Materiales
- Llama a `GET /api/cotizaciones/{id}/materiales?_t={timestamp}`
- 4 secciones basadas en `unidad`:
  - **Perfiles/Barras** → `unidad === 'm'` → muestra BARRAS A PEDIR (chip)
  - **Vidrios** → `unidad === 'm2'`
  - **Herrajes** → todo lo demás
  - **Otros** → vacío (siempre)
- Stats cards: tipos de perfil, barras totales, m² vidrio, costo estimado
- Botones: Actualizar, Imprimir

---

## 13. Módulo de Facturación (Bsale)

### `facturacion/index.vue`
- Lista cotizaciones aprobadas/facturadas/pagadas
- Integración con Bsale para emitir documentos electrónicos
- Flujo: seleccionar cotización → seleccionar tipo documento → emitir → guardar ID documento Bsale

### Campos Bsale en `Cotizacion`
| Campo | Descripción |
|---|---|
| `numero_documento_bsale` | Número del documento emitido |
| `id_documento_bsale` | ID interno de Bsale |
| `fecha_documento_bsale` | Fecha de emisión |
| `estado_facturacion` | Estado del documento en Bsale |
| `url_pdf_bsale` | URL del PDF del documento |
| `token_bsale` | Token de acceso al documento |

---

## 14. Sistema de Roles y Permisos

| Rol | Permisos |
|---|---|
| `admin` | Todo |
| `vendedor` | Ver/crear/editar cotizaciones, ver clientes |
| `produccion` | Ver cotizaciones aprobadas, hoja de cortes, materiales |
| `bodega` | Por definir |

**Permisos definidos:** `gestionar_usuarios`, `gestionar_productos`

---

## 15. Estados de Cotización y Transiciones

| Estado | Puede cambiar a |
|---|---|
| **Evaluación** | Aprobada, Rechazada |
| **Aprobada** | En Producción, Rechazada |
| **En Producción** | Entregada |
| **Entregada** | Facturada |
| **Facturada** | Pagada |
| **Rechazada** | — |
| **Producción Entregada** | — (estado especial, migración 2026-04-01) |

---

## 16. IDs de Productos Clave (Perfiles y Herrajes)

### Herrajes Universales (S60 y AL42)
| ID | Nombre |
|---|---|
| 36 | Puente |
| 37 | Calzo Amarillo |
| 38 | Calzo Celeste |
| 39 | Calzo Rojo |
| 40 | Tornillo Autoperforante |
| 41 | Tornillo Amortiguador |
| 42 | Tapa Tornillo |
| 43 | Tapa Desagüe |
| 44 | Silicona |

### Perfiles Fija AL42
| ID | Descripción |
|---|---|
| 148 | Marco superior / Jamba |
| 150 | Hoja (Proyectante) |
| 151 | Junquillo Monolítico |
| 152 | Marco inferior / Cámara de agua |
| 153 | Junquillo Termopanel |

### Perfiles Fija S60
| ID | Descripción |
|---|---|
| 32 | Marco S60 |
| 33 | Junquillo Termopanel S60 |
| 34 | Refuerzo S60 |
| 35 | Junquillo Monolítico S60 |

### Perfiles Corredera AL25
| ID | Descripción |
|---|---|
| 154 | Marco Superior |
| 157 | Marco Inferior |
| 160 | Jamba |
| 164 | Hoja |
| 165 | Junquillo Horizontal |
| 166 | Junquillo Lateral (Termopanel+Manillón) |
| 176 | Junquillo Lateral (Termopanel+Pestillo) |

### Brazos Proyectante AL42
| ID | Modelo |
|---|---|
| 221 | DT 20 |
| 222 | DT 8 |
| 223 | DT 12 |
| 224 | DT 10 |
| 225 | DT 16 |
| 226 | DT 24 |
| 227 | Manilla Proyectante |
| 228 | Burlete Redondo |
| 229 | Burlete Base |
| 230 | Burlete Cuña |
| 220 | Escuadra |

### Herrajes Corredera AL25
| ID | Descripción |
|---|---|
| 231 | Carro Termopanel |
| 232 | Carro Monolítico |
| 233 | Pestillo < 1800mm |
| 234 | Pestillo ≥ 1800mm |
| 68 | Manillón |
| 55, 57–64 | Cremonas (varios tamaños) |

### Burletes Corredera AL25
| ID | Nombre | Uso |
|---|---|---|
| 235 | Burlete 2218 | Vidrio Termopanel (tipo_vidrio=2) |
| 237 | Burlete 305 | Monolítico 5mm (producto_id=240) |
| 238 | Burlete 329 | Monolítico 4mm (producto_id=31) |
| 239 | Burlete 306 | Monolítico 6mm (producto_id=241) |

> Cantidad burlete: `(4 × anchoHoja + 4 × altoHoja) / 1000` metros (donde `anchoHoja = ancho/2 + 3`, `altoHoja = alto - 58`)

### Esquineros Bay Window (tipo 47)
| ID | Nombre | Uso |
|---|---|---|
| 253 | ESQUINERO 90° 60mm S60 | Frontal NO corredera (Fija / Proyectante) |
| 255 | ESQUINERO 90° 70mm CORREDERA | Frontal es Corredera Sliding (tipo=3) |
| 256 | Refuerzo interior S60 | Acompañante de ID 253 |
| 257 | Refuerzo interior CORREDERA | Acompañante de ID 255 |

**Lógica de cantidad:**
- Bay Window forma U (`ancho_izquierda > 0` **y** `ancho_derecha > 0`): 2 esquineros
- Bay Window forma L (solo uno de los dos > 0): 1 esquinero
- Cantidad final = `numEsquineros × cantidad` de la ventana
- Afectados por color (pasan por `crearHerraje()` con `$colorId`)
- Aparecen en lista de materiales como `[Esquineros] nombre_producto`
- Detección corredera: `$tipoVentanaCentro['tipo'] === 3`

---

## 17. Configuración de Ventana (`config` JSON)

La columna `config` en `ventanas` almacena:
```json
{
  "tipo_vidrio": 1,        // 1=Monolítico, 2=Termopanel
  "manillon": true,        // true=Manillón, false=Pestillo
  "proveedor_vidrio": 5    // proveedor_id del vidrio
}
```

El frontend envía en el payload:
```js
tipo_vidrio_id: v.tipoVidrio,  // para backend legacy
tipo_vidrio: v.tipoVidrio,     // para CortesService / CalculoVentanaService
manillon: v.manillon ?? null,
```

---

## 18. Lógica de Cálculo de Barras

```
barras = ceil(metros_necesarios / largo_total_barra_m)
```

- `metros_necesarios` = suma de todos los cortes del mismo perfil/color en una cotización
- `largo_total_barra_m` = campo `largo_total` del modelo `Producto` (en metros)
- Se muestra en la columna **BARRAS A PEDIR** en la página de materiales

---

## 19. Frontend — Axios y Autenticación

**Archivo:** `vuexy-frontend/src/axiosInstance.js`

- BaseURL: `localhost:8000` (dev) / `proyectovialum-production.up.railway.app` (prod)
- Interceptor de request: agrega `Authorization: Bearer {token}` desde localStorage
- Interceptor de response: si recibe 401, elimina token y redirige a `/login` (auto-logout)

**Stores Pinia:**
- `@core/stores/config.js` — Tema: light/dark, colores, RTL
- `@layouts/stores/config.js` — Layout: tipo navbar, ancho contenido, skin

---

## 20. Migraciones Importantes

| Migración | Cambio |
|---|---|
| `0001_01_01_000000` | Tabla `users` base |
| `2025_03_17` | Tablas: `colores`, `productos`, `proveedors`, `clientes` |
| `2025_03_21` | Tabla pivot `producto_color_proveedor` |
| `2025_03_25` | Tabla `tipos_ventana` |
| `2025_03_27` | Campos Bsale en `clientes` (`bsale_id`, empresa, contacto) |
| `2025_05_15` | Tabla `cotizaciones` (versión final) |
| `2025_05_19` | Tabla `estados_cotizacion` |
| `2025_05_31` | Columnas `hojas_totales`, `hojas_moviles` en `ventanas` |
| `2025_06_05` | Columnas `costo_unitario`, `precio_unitario` en `ventanas` |
| `2025_06_23` | Tabla `roles`, FK `role_id` en `users` |
| `2025_10_04` | `url_pdf_bsale` en `cotizaciones` |
| `2025_10_06` | `cliente_facturacion_id` en `cotizaciones` |
| `2025_10_08` | Tabla `lista_precios`, `cotizacion_detalles` (reestructura) |
| `2025_10_10` | Campos vidrio en `cotizacion_detalles` (`esVidrio`, `ancho_mm`, `alto_mm`, `m2`, `pulido`) |
| `2025_10_11` | `color_id`, `proveedor_sugerido_id` en `lista_precios` |
| `2025_12_16` | Tabla `permissions`, pivot `role_permission` |
| `2026_04_01` | Estado `produccion_entregada` en `estados_cotizacion` |
| `2026_04_06` | Columna `config` (JSON) en `ventanas` |
| `2026_04_10` | Columna `adjunto_winperfil` VARCHAR(500) en `cotizaciones` |
| `2026_04_10` | ENUM `tipo_item` en `cotizacion_detalles` ampliado a `'winperfil'` |
| `2026_04_17` | Columnas Bay Window en `ventanas`: `hoja_movil_seleccionada`, `hoja1_al_frente`, `ancho_izquierda`, `ancho_centro`, `ancho_derecha`, `tipo_ventana_izquierda` (JSON), `tipo_ventana_centro` (JSON), `tipo_ventana_derecha` (JSON) |

---

## 21. Deploy

1. Hacer cambios en el código fuente Vue en `vuexy-frontend/src/`
2. Buildear: `cd vuexy-frontend && npm run build`
   - Los assets se generan en `vuexy-frontend/dist/` y se copian a `public/assets/`
3. Commitear todo incluyendo assets buildeados:
   ```bash
   git add -A
   git commit -m "feat/fix: descripción"
   git push
   ```
4. Railway detecta el push y redeploya automáticamente el backend Laravel.
5. El frontend corre como archivos estáticos servidos por Laravel desde `public/`.

---

## 22. Pendientes / Trabajo Futuro

- [ ] **CortesService:** Agregar soporte de hoja de cortes para tipos: 45 (Proyectante S60), 46 (Corredera Andes), 47 (Bay Window), 49 (Abatir S60), 50 (Puerta S60), 51 (Puerta 2 Hojas), 52 (Sliding 98), 53 (Monorriel), 57 (Compuesta AL42), 58 (Universal)
- [ ] **produccion/[id].vue:** Verificar si se debe quitar la tabla inline de "Resumen de Materiales" (ya existe página separada)
- [ ] **Roles producción/bodega:** Definir permisos específicos
- [ ] **Módulo Bodega:** Control de stock de perfiles (barras disponibles vs. a pedir)
- [ ] **Integración Bsale:** Revisar flujo completo de facturación
- [ ] **Token Bsale:** Regenerar en Railway (token fue expuesto en git history)
- [ ] **CotizacionController:** Sin validación de request en `store()` / `update()`
- [ ] **Performance:** 44 `Log::info` de debug en `CotizacionController` (remover o bajar a nivel debug)
- [ ] **Imágenes:** Base64 dentro del JSON de cotización (payloads gigantes — migrar a storage)
- [ ] **Menú nav:** No reactivo post-login (permisos leídos una vez al cargar módulo)
- [ ] **Modelos:** Sin SoftDeletes en modelos principales
- [ ] **Comandos:** Implementar `ActualizarRazonSocialClientes` y `MigrarClientesLocalesABsale`
- [ ] **Archivos muertos:** `cotizacion-editarELIMINARPARECE.vue`, `routereliminarparece/`, `cotizador2.vue`

---

## 23. Archivos Modificados en Últimas Sesiones

### Sesión 2026-04-10 — WINPERFIL + Cloudflare R2 + Burlete AL25

| Archivo | Cambio |
|---|---|
| `routes/api.php` | +3 rutas WINPERFIL (parse, importar, actualizar) |
| `app/Http/Controllers/CotizacionController.php` | +`parseWinperfil()`, `parsearTextoWinperfil()`, `importarWinperfil()`, `actualizarWinperfil()` |
| `app/Models/Cotizacion.php` | +`adjunto_winperfil` en fillable |
| `app/Services/CalculoVentanaService.php` | +burlete en `calcularCorrederaAL25()`, fix zócalo termopanel cant=4 |
| `config/filesystems.php` | +disco `r2` (Cloudflare R2, S3-compatible) |
| `database/migrations/2026_04_10_...` | Columna `adjunto_winperfil` VARCHAR(500) en cotizaciones |
| `database/migrations/2025_10_08_...` | ENUM `tipo_item` + `'winperfil'` |
| `vuexy-frontend/src/pages/cotizaciones/importar-pvc.vue` | NUEVO — importar + editar cotizaciones WINPERFIL |
| `vuexy-frontend/src/pages/cotizaciones/index.vue` | +botón importar WINPERFIL, +ícono PDF adjunto, editar detecta winperfil |

### Sesión 2026-04-17 — Bay Window completo (cálculo + visual + persistencia + PDF)

| Archivo | Cambio |
|---|---|
| `app/Services/CalculoVentanaService.php` | `calcularVentanaCompuestaBay()`: fallback `alto / numPartes` cuando `parte.alto` es null. `calcularBayWindow()`: esquineros U/L con IDs 253/255/256/257 según tipo del frontal |
| `app/Models/Ventana.php` | +`hoja_movil_seleccionada`, `hoja1_al_frente`, `ancho_izquierda`, `ancho_centro`, `ancho_derecha`, `tipo_ventana_izquierda`, `tipo_ventana_centro`, `tipo_ventana_derecha` en `$fillable`; casts boolean + array (JSON auto-decode) |
| `app/Http/Controllers/CotizacionController.php` | `store()`, `update()` (ventana existente) y `update()` (ventana nueva): eliminado `json_encode()` manual; agregados 6 campos Bay Window en los 3 lugares |
| `database/migrations/2026_04_17_000001_add_bay_window_fields_to_ventanas_table.php` | NUEVO — agrega las 8 columnas Bay Window a la tabla `ventanas` |
| `vuexy-frontend/src/components/VistaBayWindow.vue` | `exportarImagen()` vía Konva (`stageRef`/`layerRef`); sin separación entre secciones; esquinero visual (rect 12px coloreado); sección derecha oculta si `ancho_derecha === 0`; `escalaGlobal` descuenta espacio de esquineros |
| `vuexy-frontend/src/pages/AgregarVentanaModal2.vue` | Campo `ancho` readonly + auto-calculado como suma de secciones (watch); todos los `v-select` tipo Bay Window con `:rules` y `hide-details="auto"`; regla derecha condicional; `onGuardar` → async con `formRef.validate()` |
| `resources/views/cotizaciones/pdf.blade.php` | Imagen de ventana: `max-height: 220px; width: auto; height: auto; margin: 0 auto` para que Bay Window no se vea aplastado |

**SQL para ejecutar en producción:**
```sql
ALTER TABLE ventanas
  ADD COLUMN hoja_movil_seleccionada INT NULL AFTER hojas_moviles,
  ADD COLUMN hoja1_al_frente TINYINT(1) NULL AFTER hoja_movil_seleccionada,
  ADD COLUMN ancho_izquierda INT NULL AFTER hoja1_al_frente,
  ADD COLUMN ancho_centro INT NULL AFTER ancho_izquierda,
  ADD COLUMN ancho_derecha INT NULL AFTER ancho_centro,
  ADD COLUMN tipo_ventana_izquierda JSON NULL AFTER ancho_derecha,
  ADD COLUMN tipo_ventana_centro JSON NULL AFTER tipo_ventana_izquierda,
  ADD COLUMN tipo_ventana_derecha JSON NULL AFTER tipo_ventana_centro;
```

---

### Sesión anterior — Producción y CortesService

| Archivo | Cambio |
|---|---|
| `routes/api.php` | +2 rutas producción |
| `app/Http/Controllers/ProduccionController.php` | Métodos `hojaCortes()` + `resumenMateriales()` |
| `app/Http/Controllers/CotizacionController.php` | Fix `update()`: config, hojas, total |
| `app/Services/CortesService.php` | +`ventanas_omitidas` |
| `vuexy-frontend/src/pages/cotizador/index.vue` | Fix payload: `tipo_vidrio` + `manillon` |
| `vuexy-frontend/src/pages/produccion/index.vue` | Dos botones por cotización |
| `vuexy-frontend/src/pages/produccion/[id].vue` | `fetchData()`, `watch`, actualizar, warning |
| `vuexy-frontend/src/pages/produccion/materiales/[id].vue` | NUEVO — página de materiales |
