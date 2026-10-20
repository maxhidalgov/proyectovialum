# Ideas

## Pendientes

Contactos por cliente (seguimiento de la cotización express con contacto, 2026-10-09):
- [ ] 2026-10-09 — Contactos: selector "Contacto / Atención" también en el cotizador completo (`pages/cotizador/index.vue`) y en la edición de cotizaciones; hoy `update()` conserva el contacto pero no permite cambiarlo
- [ ] 2026-10-09 — Contactos: al enviar la cotización por WhatsApp/correo usar el teléfono/email del contacto como destino por defecto (hoy usa el del cliente) y mostrar el contacto en Seguimiento
- [ ] 2026-10-09 — Contactos: administrar los contactos desde la ficha del cliente (hoy solo se crean desde la cotización express) y que el bot de WhatsApp pueda cotizar "para Juanito de Guindo Santo"

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

Créditos de API y seguridad (incidente: ver memoria `incidente-credito-api-y-env-expuesto`):
- [ ] 2026-10-06 — URGENTE: `public/info.php` (phpinfo) estuvo público en producción y expuso TODOS los secretos de Railway. Archivo eliminado (f1ab711c). ROTAR en este orden: DB_PASSWORD (MySQL Railway, proxy público), BSALE_TOKEN/BSALE_ACCESS_TOKEN, CHIPAX_SECRET_KEY/APP_ID/COOKIE, R2_ACCESS_KEY_ID/R2_SECRET_ACCESS_KEY, WORKERA_API_KEY, BCH_CLIENT_SECRET, FTP_PASSWORD, MAIL_PASSWORD, REDIS_PASSWORD, CRON_TOKEN, BOT_API_TOKEN (también en el bot), JWT_SECRET (cierra sesiones), APP_KEY (cuidado con datos encriptados). Revisar Bsale/Chipax/DB por actividad que no reconozcas
- [ ] 2026-10-05 — Confirmar que la clave nueva `Vialum-app` esté en las variables de Railway y probar el Agente de Leads / IA de Producción en app.vialum.cl (en local ya responde 200)
- [ ] 2026-10-05 — Revisar en la consola de Anthropic, Uso agrupado por clave: `Vialum-app` debe mostrar solo Sonnet 4.6; si aparece Opus, algo más la usa. Y buscar `invalid x-api-key` en logs de Railway (delata qué usaba la clave vieja FirstExito)
- [ ] 2026-10-05 — Rotar la clave del bot (`whatsapp-bot`) y sacar la carpeta del bot de OneDrive (el `.env` se sincroniza a la nube)
- [ ] 2026-10-05 — Cargar en Railway `BOT_API_TOKEN` (mismo del bot) y `BOT_VENDEDOR_ID` (tu id de usuario en prod; en local es 5) para los endpoints `/api/bot/*` y las cotizaciones del bot
- [ ] 2026-10-05 — Bot: probar "precio de compra ángulo revestimiento" en prod; si no aparece, cargar los XML pendientes de Compras (la factura Haustek 269051 de sep-2026 debe tener sus líneas)
- [ ] 2026-10-05 — Apache local sirve toda la carpeta htdocs (se tapó con `.htaccess` en la raíz): apuntarlo solo a `public/` o usar `artisan serve`; decidir si el `.htaccess` se commitea

Producción y bot (ideas del dueño, 2026-10-20):
- [ ] 2026-10-20 — Servicio compartido "aprobar y pedir material" (app + bot): botón de un solo paso en la web y, sobre eso, el bot ("aprueba la 196") con vista previa, preguntas necesarias (total, anticipo, material/EETT, proveedor) y confirmación. Antes: permisos por número y rotar el token del bot
- [ ] 2026-10-20 — Bot: seguimiento de obras aprobadas sin cambio de estado en N días (umbral por etapa), aviso diario con resumen y respuesta en lenguaje natural que actualiza estado/nota/pausa; columna "Días sin movimiento" en Operaciones
- [ ] 2026-10-20 — Bot → calendario: "mañana vamos a medir donde Juanito Pérez / instalar donde Pedrito" crea el evento (calendario/recordatorios/visitas_cliente existentes) vinculado a la obra, y pregunta después si se hizo; también anotar cosas en el calendario
- [ ] 2026-10-20 — Bot: seguimiento de cotizaciones sin aprobar, parte 2: aviso escalonado diario/por vendedor y responder en lenguaje natural ("llamé, quedó en responder el viernes" → nota + recordatorio; "rechazó" → Rechazada con motivo; "aprobó" → inicia la aprobación); limpieza guiada de las +30 días (112 en prod)
- [ ] 2026-10-20 — Ciclo semanal de proveedor (Winhouse viene los miércoles): recordar el martes en la mañana las obras con `pedido_proveedor` sin marcar, y armar el pedido consolidado

Winperfil:
- [ ] 2026-10-17 — Winperfil: control de costo/margen por obra con PERFCOSTE/ACCCOSTE/VIDCOSTE/MANOCOSTE de la cabecera de oferta
- [ ] 2026-10-09 — Winperfil: las rutas que buscan cotización solo por `winperfil_numero` (pedidos, re-sync/plano/PDF de una cotización) siguen ambiguas si el número se repite; guardar un id único (cliente Winperfil + NUMFACTURA) en la cotización

Correo:
- [ ] 2026-10-20 — Cargar el SMTP (Workspace contacto@vialum.cl) en las variables de Railway: sin eso no funciona el envío de cotizaciones ni de órdenes de compra por correo; probar con un envío real. Después: copia (CC) al vendedor y envío por correo también desde Seguimiento

Facturación:
- [ ] 2026-10-06 — Botón "Anular" (`DocumentoFacturacionController::anular`) solo cambia el estado local a `anulado` y no llama a Bsale; avisar o impedir anular desde la app una factura que no tenga NC en Bsale
- [ ] 2026-10-05 — Cotizaciones Winperfil: poder agregar un ítem adicional (ej. "Ángulos", precio con IVA) desde `cotizacion-ver` sin repartirlo entre las ventanas. Ojo: `update()` calcula el total sin las líneas `winperfil`, por eso el cotizador completo NO sirve para editar cotizaciones Winperfil (dejaría el total solo con extras)

## En curso

## Hechas
- [x] 2026-10-20 → 2026-10-20 — Cotizaciones: enviar por correo con el PDF adjunto (no el link), con el correo del contacto/cliente por defecto; requiere cargar el SMTP en Railway
- [x] 2026-10-20 → 2026-10-20 — Bot: resumen comercial semanal al grupo (comando `comercial` + cron lunes 9:00): cotizaciones por llamar a 3/7/15 días por vendedor y presupuestos de Winperfil sin aceptar; además se vació el token real de `.env.example` del bot (la rotación sigue pendiente)
- [x] 2026-10-20 → 2026-10-20 — Historial de modificaciones de la cotización (cambio de oferta / ajuste de precio, con total antes/después y motivo) y orden de compra complementaria (pestaña "Falta pedir": lo que Winperfil calcula hoy menos lo ya pedido)
- [x] 2026-10-17 → 2026-10-17 — Materiales Winperfil: lista de compra y hoja de cortes con las barras exactas de pedbarra (largo real, color, rollos, sin mano de obra) y acomodo de cortes que cuadra con Winperfil
- [x] 2026-10-17 → 2026-10-17 — Winperfil: botón "Cambiar oferta" para que una cotización pase a la oferta nueva cuando el cliente modifica el presupuesto aceptado (sin duplicar)
- [x] 2026-10-09 → 2026-10-09 — Winperfil: cotizaciones de distintos clientes con el mismo NUMFACTURA (725 Ar Alena vs PESA) ya no se pisan; #181 restaurada a Ar Alena por migración
- [x] 2026-10-09 → 2026-10-09 — Cotización express: elegir una persona de contacto de la empresa (cliente) a quien va dirigida; aparece como "Atención" en el PDF y la vista previa
- [x] 2026-10-06 → 2026-10-06 — Facturas de anticipo/saldo en Bsale: ahora salen con UNA línea con el monto real del documento (sin precio completo + descuento oculto); el rótulo Anticipo/Saldo depende de si ya hay un documento emitido de la cotización. Monto, IVA y pago no cambian. Aplica a facturas nuevas (la 5235 ya emitida no se toca)
- [x] 2026-10-06 → 2026-10-06 — Registro de Ventas / Cuentas por Cobrar / buscador de Ventas: una factura marcada `anulado` que tiene NC asignada ahora se muestra junto a su NC (se neutralizan) en vez de ocultarse. Caso Villanueva: antes 5108 + NC 89 daban $0, ahora $3.172.239. Verificado contra producción: único documento afectado
- [x] 2026-10-05 → 2026-10-06 — Facturación: botón "Emitir" visible mientras quede saldo por facturar aunque lo emitido esté todo cobrado (caso #158 Villanueva: 50% facturado y cobrado)
- [x] 2026-10-05 → 2026-10-06 — Facturación: el "Saldo" del modal de emisión usa el porcentaje exacto (con decimales) en vez del % entero redondeado
