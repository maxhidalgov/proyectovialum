# Registro de trabajo

Entradas más recientes arriba. Formato:

## AAAA-MM-DD — Título corto
- **Qué se hizo:** 1-3 líneas.
- **Archivos principales:** rutas clave.
- **Pendiente / ojo con:** lo que quedó abierto, o "nada".

---

## 2026-10-06 — Facturación: poder emitir el saldo de una cotización ya cobrada
- **Qué se hizo:** El estado pasa a "Cobrada" apenas todo lo emitido está cobrado, aunque solo se haya facturado una parte, y el botón Emitir se ocultaba (caso #158 Villanueva: 50% facturado y cobrado). Ahora `puedeEmitir()` lo muestra mientras queden más de $1.000 por facturar y el proceso no esté cerrado a mano. Además el "Saldo" del modal de emisión usa el % exacto con decimales (antes el % entero redondeado podía facturar de más, ej. +$19.344).
- **Archivos principales:** `vuexy-frontend/src/pages/facturacion/index.vue`, `vuexy-frontend/src/components/facturacion/ModalBsale.vue`, `public/` (build). El build se hizo desde una copia limpia de HEAD para NO incluir el Optimizador de Corte ni la ayuda de Operaciones (siguen solo en local).
- **Pendiente / ojo con:** Cotizaciones antiguas pagadas con boletas y sin "Cerrar proceso" podrían mostrar Emitir (se cierran a mano). El backend rotula como "Anticipo" todo documento ≤ 50% aunque ya haya emitidos previos (`BsaleController` ~L79/L505); no se tocó. Para sumar un ítem (ej. ángulos) a una cotización Winperfil, hoy solo sirve "Ajustar precio" en `cotizacion-ver`.

## 2026-10-01 — Bot de WhatsApp: el precio también busca en facturas de compra
- **Qué se hizo:** `GET /api/bot/precio` solo miraba `lista_precios` por el nombre del producto en Vialum, así que "precio de compra ángulo revestimiento" no encontraba nada (en la lista es "Angulo 50 x 50" y la factura de Haustek dice "ANGULO REVESTIMIENTO 50/50 mm - NOGAL"). Ahora, si la lista no tiene el producto, o si se pasa `compras=1` (el bot lo manda cuando la frase dice compra/costo/proveedor), busca también en `compra_items` por nombre o código del proveedor y muestra la última compra (fecha, proveedor, factura, neto c/u después del descuento, cantidad). Limpia nombres que la factura repite entre paréntesis.
- **Archivos principales:** `app/Http/Controllers/BotController.php`.
- **Pendiente / ojo con:** Depende de que la factura tenga sus líneas cargadas (XML) en producción. Las palabras deben estar todas en la descripción del proveedor (ej. "50/50" no equivale a "50 x 50").

## 2026-10-01 — Bot de WhatsApp: cotización rápida por chat privado
- **Qué se hizo:** Endpoints `GET /api/bot/clientes` y `POST /api/bot/cotizacion` (token `BOT_API_TOKEN`; `confirmar=1` crea, sin él solo vista previa). Admiten ítems de lista (`lista_precio_id`, con descuento del cliente) e ítems libres (nombre + precio manual, `incluye_iva` convierte a neto). Crea la cotización por `VentaExpressController::guardarCotizacion` (la misma de Cotización Rápida) y devuelve el link público del PDF; el vendedor sale de `BOT_VENDEDOR_ID`. `precio` ahora acepta `n` y `compacto=1` y devuelve `lista_precio_id`. En el bot (`src/cotizador.js`) Claude arma el borrador con herramientas pero NO crea: solo el código, al recibir "SI" del dueño por privado, confirma.
- **Archivos principales:** `app/Http/Controllers/BotController.php`, `routes/api.php`, `config/services.php`.
- **Pendiente / ojo con:** Cargar `BOT_VENDEDOR_ID` en Railway (en local el usuario 1 es otra persona; verificar el id real en prod). Solo productos de lista e ítems libres (sin ventanas a medida ni vidrios por m²). Funciona solo por privado con el número del dueño. Requiere saldo de la API de Anthropic para la parte con IA.

## 2026-10-01 — Bot de WhatsApp: últimas N facturas de un proveedor/cliente
- **Qué se hizo:** Nuevo endpoint `GET /api/bot/facturas?q=&n=&lado=` (token `BOT_API_TOKEN`): últimas N (máx. 15, por defecto 5) facturas de compra de un proveedor (por nombre o RUT) y/o de venta de un cliente, con estado de pago por línea y total por pagar/cobrar. Solo facturas 33/34 (no NC ni boletas). El bot entiende "asistente, últimas 5 facturas de haustek" y el comando `facturas haustek`.
- **Archivos principales:** `app/Http/Controllers/BotController.php`, `routes/api.php`.
- **Pendiente / ojo con:** Si el nombre coincide con varios proveedores, mezcla sus facturas y rotula cada línea con el nombre.

## 2026-10-01 — Bot de WhatsApp: info de factura/boleta por folio
- **Qué se hizo:** Nuevo endpoint `GET /api/bot/factura?q=&lado=` (token `BOT_API_TOKEN`). Busca por folio en compras (proveedor, fecha, neto/IVA/total, estado de pago, hasta 6 líneas, PDF) y en ventas (facturas/NC con cobrado y pendiente vía `registroVentas`; boletas aparte). Los folios se repiten entre proveedores/tipos, así que devuelve hasta 3 por lado. `CuentasPorPagarController::efectivoPagadoSub` pasó a `public` para reusar el cálculo de pagado. El bot detecta "asistente, factura de compra 457307" / "boleta 8037" y el comando privado `factura N`.
- **Archivos principales:** `app/Http/Controllers/BotController.php`, `app/Http/Controllers/CuentasPorPagarController.php`, `routes/api.php`.
- **Pendiente / ojo con:** Los datos de facturas (montos, proveedores, cobrado) los ve todo el grupo (decisión del dueño). El link PDF de compras es un link firmado de Bsale.

## 2026-10-01 — Bot de WhatsApp: consulta de precios y última compra
- **Qué se hizo:** Nuevo endpoint `GET /api/bot/precio?q=` (token `BOT_API_TOKEN`) que busca en `lista_precios` (cada palabra en producto o color) y devuelve hasta 3 coincidencias con precio de venta (neto y c/IVA), costo de lista y última compra (fecha, proveedor, factura) vía `compra_items`→`producto_color_proveedor`. Si no hay compra del mismo color, muestra la última del producto indicando el color. En el bot, "asistente, precio de la silicona negra" en el grupo se responde sin IA. También se corrigió que `ausentes-hoy` usaba fecha UTC en vez de Chile.
- **Archivos principales:** `app/Http/Controllers/BotController.php`, `routes/api.php`.
- **Pendiente / ojo con:** El costo y las compras se ven en el grupo para todos los miembros (decisión del dueño). La última compra depende de que las facturas tengan líneas cargadas (XML) y `pcp_id` asignado.

## 2026-09-30 — Endpoint para bot de WhatsApp: ausentes del día
- **Qué se hizo:** Nuevo endpoint `GET /api/bot/ausentes-hoy` (protegido por token `BOT_API_TOKEN`, sin login, patrón del cron) que reutiliza `AsistenciaController::diario` (Workera) y devuelve texto listo para WhatsApp con quién no marcó y los atrasos. Lo consume el bot de pendientes (proyecto aparte) que lo postea al grupo cada mañana 9:30.
- **Archivos principales:** `app/Http/Controllers/BotController.php` (nuevo), `routes/api.php`, `config/services.php`.
- **Pendiente / ojo con:** Falta cargar `BOT_API_TOKEN` (mismo valor en Railway y en el bot) y las credenciales `WORKERA_API_USER`/`WORKERA_API_KEY` en Railway (sin ellas el endpoint responde 422). El bot corre aparte (no es este repo).

## 2026-09-30 — Prompt caching en servicios de IA
- **Qué se hizo:** Se agregó `cache_control` (ephemeral) al system prompt + tools de `AgenteLeadsService` e `IaProduccionService` para bajar el gasto de API directa (la consola de Anthropic marcó "low cache hit rate", ahorro estimado hasta ~46%). En Producción el system se dividió en dos bloques: estático (cacheado, incluye las tools por prefijo) y contexto en vivo (sin cachear, va después del breakpoint).
- **Archivos principales:** `app/Services/AgenteLeadsService.php`, `app/Services/IaProduccionService.php`.
- **Pendiente / ojo con:** Solo surte efecto en Railway (prod es quien gasta). Falta deploy. TTL de caché es 5 min; el mayor ahorro es dentro del loop de tools y en conversaciones activas.
