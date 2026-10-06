# Ideas

## Pendientes

Bot de WhatsApp (proyecto aparte `bot-pendientes-whatsapp` + endpoints `/api/bot/*` en BotController):
- [ ] 2026-10-01 — Bot: consulta "cómo va la obra de X" (estado de producción, fecha de entrega, etapa; datos de Operaciones)
- [ ] 2026-10-01 — Bot: consulta de stock "¿cuánto hay de X?" (inventario_movimientos)
- [ ] 2026-10-01 — Bot: datos de un cliente (teléfono, dirección, link a Google Maps con lat/lng) para instaladores en terreno
- [ ] 2026-10-01 — Bot: consulta de cotización por número o última de un cliente (total, estado, link al PDF)
- [ ] 2026-10-01 — Bot: "ventas de hoy / del mes" con total facturado
- [ ] 2026-10-01 — Bot proactivo: agenda del día a las 8:30 (entregas, visitas, recordatorios) — recomendada como primera
- [ ] 2026-10-01 — Bot proactivo: pagos a proveedores de la semana (facturas por vencer, montos)
- [ ] 2026-10-01 — Bot proactivo: cobros atrasados de clientes con facturas impagas hace más de X días
- [ ] 2026-10-01 — Bot proactivo: alerta cuando una compra sube el costo de un producto (reusar `alertasPrecio` de CompraController)
- [ ] 2026-10-01 — Bot proactivo: alerta de stock bajo el mínimo
- [ ] 2026-10-01 — Bot: registrar desde el grupo ("Juan faltó hoy", "recuérdame llamar a Pérez el viernes") reusando las tools de IaProduccionService
- [ ] 2026-10-01 — Bot→Vialum: que los pendientes detectados en el grupo (cotizar, medir) entren solos al CRM y a la agenda
- [ ] 2026-10-01 — Bot con IA: leer fotos de croquis/medidas y transcribir audios (tiene costo de API)
- [ ] 2026-10-01 — Agente de Leads Fase 2: conectar el cerebro al canal WhatsApp del bot (con otro número)
- [ ] 2026-10-01 — Bot: permisos por número — lo sensible (costos, deudas de clientes, pagos) solo para el dueño y autorizados; hacerlo ANTES de sumar cobros/pagos (hoy cualquiera del grupo ve costos y montos)
- [ ] 2026-10-01 — Bot: limpiar el `BOT_API_TOKEN` real que quedó en `.env.example` del bot y cargarlo en Railway si falta

Créditos de API y seguridad (incidente: ver memoria `incidente-credito-api-y-env-expuesto`):
- [ ] 2026-10-05 — Confirmar que la clave nueva `Vialum-app` esté en las variables de Railway y probar el Agente de Leads / IA de Producción en app.vialum.cl (en local ya responde 200)
- [ ] 2026-10-05 — Revisar en la consola de Anthropic, Uso agrupado por clave: `Vialum-app` debe mostrar solo Sonnet 4.6; si aparece Opus, algo más la usa. Y buscar `invalid x-api-key` en logs de Railway (delata qué usaba la clave vieja FirstExito)
- [ ] 2026-10-05 — Rotar la clave del bot (`whatsapp-bot`) y sacar la carpeta del bot de OneDrive (el `.env` se sincroniza a la nube)
- [ ] 2026-10-05 — Cargar en Railway `BOT_API_TOKEN` (mismo del bot) y `BOT_VENDEDOR_ID` (tu id de usuario en prod; en local es 5) para los endpoints `/api/bot/*` y las cotizaciones del bot
- [ ] 2026-10-05 — Bot: probar "precio de compra ángulo revestimiento" en prod; si no aparece, cargar los XML pendientes de Compras (la factura Haustek 269051 de sep-2026 debe tener sus líneas)
- [ ] 2026-10-05 — Apache local sirve toda la carpeta htdocs (se tapó con `.htaccess` en la raíz): apuntarlo solo a `public/` o usar `artisan serve`; decidir si el `.htaccess` se commitea

Facturación:
- [ ] 2026-10-05 — Cotizaciones Winperfil: poder agregar un ítem adicional (ej. "Ángulos", precio con IVA) desde `cotizacion-ver` sin repartirlo entre las ventanas. Ojo: `update()` calcula el total sin las líneas `winperfil`, por eso el cotizador completo NO sirve para editar cotizaciones Winperfil (dejaría el total solo con extras)

## En curso

## Hechas
- [x] 2026-10-05 → 2026-10-06 — Facturación: botón "Emitir" visible mientras quede saldo por facturar aunque lo emitido esté todo cobrado (caso #158 Villanueva: 50% facturado y cobrado)
- [x] 2026-10-05 → 2026-10-06 — Facturación: el "Saldo" del modal de emisión usa el porcentaje exacto (con decimales) en vez del % entero redondeado
