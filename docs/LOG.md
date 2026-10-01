# Registro de trabajo

Entradas más recientes arriba. Formato:

## AAAA-MM-DD — Título corto
- **Qué se hizo:** 1-3 líneas.
- **Archivos principales:** rutas clave.
- **Pendiente / ojo con:** lo que quedó abierto, o "nada".

---

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
