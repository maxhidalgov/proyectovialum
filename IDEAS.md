# Ideas

## Pendientes

Agente cotizador (ideas del 2026-11-26) — ORDEN DE TRABAJO: 1 errores de precio (En curso) → 2 casos de evaluación → 3 servicios y borrador → 4 pantalla de revisión → 5 Excel y otros archivos → 6 solo vidrio/termopanel → 7 Gmail. El set de evaluación crece en cada etapa. El chat actual (`/agente`) se mantiene sin ampliarlo; la pantalla de revisión será el flujo principal.
- [ ] 2026-11-26 — Etapa 1b · Verificar una primera línea de ventanas contra Winperfil (casos de prueba; comparar materiales, cortes, costo y precio) y habilitar para el equipo solo las líneas y medidas verificadas (el cotizador propio es central: más personas del equipo deben poder cotizar, hoy solo 2 usan Winperfil)
- [ ] 2026-11-26 — Bsale: probar con un documento de prueba (sin emitir real) cómo redondea Bsale el neto por línea con `netUnitValue` de 4 decimales; además `construirPayloadBsale` castea `(int)$detalle->cantidad` y trunca cantidades fraccionarias
- [ ] 2026-11-26 — Impacto en facturas ya emitidas (requiere autorización de consulta): documentos Bsale de cotizaciones con varias ventanas de distinta cantidad, cuyas líneas salían con valor unitario distorsionado antes de la corrección (el total siempre cuadró)
- [ ] 2026-11-26 — Al desplegar la corrección de precios: reconstruir el frontend (`npm run build` y copiar a public/) por los cambios en `facturacion/index.vue` y `DetalleCotizacion.vue`; las filas antiguas con unitarios nulos (170 de 202 en local) se derivan al leer, sin tocar la BD
- [ ] 2026-11-26 — Etapa 2 · Set de evaluación: empezar con 10 casos reales anonimizados con su resultado esperado y crecer a 30-50 en cada etapa, midiendo sobre todo errores no alertados
- [ ] 2026-11-26 — Etapa 3 · Servicios independientes del chat: extracción, validación y cálculo ejecutables en cola, para recibir entradas desde la app, Gmail o WhatsApp
- [ ] 2026-11-26 — Etapa 3 · Borrador persistente que admita distintos tipos de ítem desde el inicio (ventana, solo vidrio/termopanel, ítem libre…); por campo: valor, texto original, archivo/fila/página, origen (explícito / deducido / por defecto / faltante) y estado. Reglas: un dato explícito TAMBIÉN pasa por las validaciones y al comienzo se revisa la cotización completa; lo deducido es una sugerencia pendiente de confirmación; los valores por defecto solo se aplican si el usuario los autoriza y quedan visibles; lo que no viene queda vacío. `crear_cotizacion` recibe solo `borrador_id` y PHP recalcula costo y precio
- [ ] 2026-11-26 — Etapa 3 · Validación PHP del borrador con tests: unidades mm/cm/m, posible ancho/alto invertido (preguntar, nunca corregir solo), cantidades contra el total declarado, tipo de medida (vano/fabricación/aproximada), material, color, apertura, vidrio y alerta de vidrio de seguridad, combinaciones con costo en catálogo y límites configurables por tipo de ventana; tipos no soportados quedan pendientes
- [ ] 2026-11-26 — Etapa 3 · Tabla o config de tipos de ventana soportados por el agente, con sus rangos de medidas (mín/máx ancho y alto, hojas, etc.); hoy `calcularMateriales` despacha por ID con una cadena de `if` y no hay lista declarativa
- [ ] 2026-11-26 — Etapa 3 · Guardar las correcciones del vendedor en la revisión (valor propuesto → valor final, por campo) para alimentar el set de evaluación y detectar patrones de error
- [ ] 2026-11-26 — Etapa 4 · Pantalla de revisión del borrador (flujo principal): original junto a tabla editable, dudas destacadas, dibujo por ítem, cambios masivos de color/vidrio y preguntas agrupadas, separando las del vendedor de las del cliente, listas para copiar
- [ ] 2026-11-26 — Etapa 5 · Entradas con archivo: primero Excel, después PDF y fotos, al final planos; el PDF Winperfil reutiliza el parser existente
- [ ] 2026-11-26 — Etapa 6 · Soportar en el agente ítems de solo vidrio o termopanel (medidas, composición, pulido), sin tratarlos como ventanas
- [ ] 2026-11-26 — Etapa 7 · Integración con Gmail (Workspace vialum.cl, app OAuth Internal, gmail.readonly + gmail.compose): correos con etiqueta "Cotizar" al mismo borrador; tras aprobación en la app, crear en el mismo hilo un borrador con el PDF o con preguntas si faltan datos; nunca enviar automáticamente, evitar duplicados, actualizar con las respuestas, guardar el token cifrado, avisar si falla y tratar correo y adjuntos como contenido no confiable

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
- [ ] 2026-10-22 — Bot → calendario de obra: "iremos a medir donde X el jueves 25", "hoy fuimos a medir con Juanito las ventanas de X", "hoy se instaló donde X", "el 12 de noviembre hay que instalar donde X" crea o registra el evento en el calendario vinculado a la obra (medición/instalación, hecho o por hacer)
- [ ] 2026-10-26 — Pedido a proveedor: completar el flujo (agregar líneas extra como manillas desde catálogo, traducir códigos Winperfil→Haustek y cajas al generar la orden), confirmar equivalencias nuevas desde la pantalla de validación, comparar precios con compras anteriores y recibir el PDF por el bot ("validar cotización"). Excel/PDF aceptados por Haustek
- [ ] 2026-10-20 — Servicio compartido "aprobar y pedir material" (app + bot): botón de un solo paso en la web y, sobre eso, el bot ("aprueba la 196") con vista previa, preguntas necesarias (total, anticipo, material/EETT, proveedor) y confirmación. Antes: permisos por número y rotar el token del bot
- [ ] 2026-10-20 — Bot: seguimiento de obras aprobadas sin cambio de estado en N días (umbral por etapa), aviso diario con resumen y respuesta en lenguaje natural que actualiza estado/nota/pausa; columna "Días sin movimiento" en Operaciones
- [ ] 2026-10-20 — Bot: seguimiento de cotizaciones sin aprobar, parte 2: aviso escalonado diario/por vendedor y responder en lenguaje natural ("llamé, quedó en responder el viernes" → nota + recordatorio; "rechazó" → Rechazada con motivo; "aprobó" → inicia la aprobación); limpieza guiada de las +30 días (112 en prod)
- [ ] 2026-10-20 — Ciclo semanal de proveedor (Winhouse viene los miércoles): recordar el martes en la mañana las obras con `pedido_proveedor` sin marcar, y armar el pedido consolidado

Guía:
- [ ] 2026-10-21 — Guía de uso: agregar capturas de pantalla, una versión para el taller/producción y la parte de conciliación bancaria; mantenerla al día cuando cambie un flujo

Winperfil:
- [ ] 2026-10-21 — Pendientes técnicos, lo que quedó: aviso por WhatsApp/bot (y en el resumen de producción) cuando se registra una venta parcial o algo lleva +2 días; mostrar el dibujo de las ventanas vendidas en la lista; sugerir el vínculo de boletas/facturas de Bsale con la obra
- [ ] 2026-10-17 — Winperfil: control de costo/margen por obra con PERFCOSTE/ACCCOSTE/VIDCOSTE/MANOCOSTE de la cabecera de oferta
- [ ] 2026-10-09 — Winperfil: las rutas que buscan cotización solo por `winperfil_numero` (pedidos, re-sync/plano/PDF de una cotización) siguen ambiguas si el número se repite; guardar un id único (cliente Winperfil + NUMFACTURA) en la cotización

Correo:
- [ ] 2026-10-21 — WhatsApp Business oficial (Meta Cloud API) para enviar cotizaciones/boletas con el PDF adjunto de forma automática desde el número de la empresa: ver tarifa exacta en CLP en el Administrador de WhatsApp (Chile), necesita número dedicado, cuenta verificada y plantillas aprobadas; evaluar si el volumen lo justifica
- [ ] 2026-10-20 — Cargar el SMTP (Workspace contacto@vialum.cl) en las variables de Railway: sin eso no funciona el envío de cotizaciones ni de órdenes de compra por correo; probar con un envío real. Después: copia (CC) al vendedor y envío por correo también desde Seguimiento

Facturación:
- [ ] 2026-10-06 — Botón "Anular" (`DocumentoFacturacionController::anular`) solo cambia el estado local a `anulado` y no llama a Bsale; avisar o impedir anular desde la app una factura que no tenga NC en Bsale
- [ ] 2026-10-05 — Cotizaciones Winperfil: poder agregar un ítem adicional (ej. "Ángulos", precio con IVA) desde `cotizacion-ver` sin repartirlo entre las ventanas. Ojo: `update()` calcula el total sin las líneas `winperfil`, por eso el cotizador completo NO sirve para editar cotizaciones Winperfil (dejaría el total solo con extras)

- [ ] 2026-10-26 — RUT de clientes: corregir en Bsale y en la app los ya mal guardados (ids 1929 Municipalidad Cementerio `691701034`, 1930 `0`, 1932 `13804467K`, 1937 duplicado de 1938) y revisar si se emitieron facturas con RUT mal; además la factura 5280 necesita NC + re-emisión. Opcional: unificar el formato (hoy 354 con puntos y 671 sin) para que las búsquedas por RUT no dupliquen clientes

## En curso
- [ ] 2026-11-26 — Etapa 1 · Agente cotizador: `toolCalcularPrecio` usa `array_column` sobre el retorno completo de `calcularMateriales` (que es `{materiales, costo_total, costo_unitario}`) y da precio 0; al corregirlo NO volver a multiplicar por cantidad (costo_total ya la incluye). Verificar con tests por tipo de ventana
- [ ] 2026-11-26 — Etapa 1 · Unificar `ventanas.precio`/`costo` = TOTAL de la línea y `precio_unitario`/`costo_unitario` = valor por unidad, entre AgenteController, CotizacionController (store/update/duplicar), PDF, Bsale/facturación y frontend; probar con cantidad 3. Antes de corregir: inventario de lectores/escritores, tests que reproduzcan y plan aprobado; NO modificar datos existentes (solo detectar las cotizaciones del agente mal guardadas)
- [ ] 2026-10-22 — (Fase 1 construida el 22-10: endpoints /api/bot/aprobar/* + aprobador.js en el bot; falta probarla con la IA real y por WhatsApp; Fase 2 medidas/material/recordatorios y Fase 3 calendario siguen pendientes) Bot: "aprobar cotización" por WhatsApp (solo el dueño, con confirmación y link a la cotización para verla). Ej: "cotización de winperfil de marianela becker aceptada, todas las ventanas, EETT: …, pago con tarjeta crédito 200.000 voucher 123456 y transferencia 250.000, hicimos boleta N° X / no hay documento". Decisiones: (1) la IA solo convierte el mensaje en datos, el resto es código, con comando fijo de respaldo; (2) busca primero en la app y si no en Winperfil, importa con venta parcial y precio opcional ("cerramos en $X" neto/bruto, se prorratea); (3) pagos como líneas con forma de pago (efectivo/transferencia/tarjeta crédito/débito/cheque), monto, fecha (hoy por defecto) y N° de transacción = voucher Transbank (para cruzar con transbank_transacciones); varios pagos y multipago; (4) pregunta si se hizo documento: con boleta/factura se enlaza por folio (si no está sincronizado se busca en Bsale) y los pagos quedan informativos para NO duplicar el abono; sin documento cada pago es una nota de venta que sí suma como abono; (5) estado final Aprobada; pedir material queda fuera de esta fase y el bot lo recuerda. Después: preguntar si hay medidas definitivas o hay que ir a medir, si se pidió el material y recordatorios de pedirlo

## Hechas
- [x] 2026-11-18 → 2026-11-18 — Cotizaciones: las hechas solo con ítems libres aparecían con total neto $0 en el listado (el PDF sí mostraba el monto); store() ahora suma los ítems libres y una migración corrige las ya creadas (205, 220, 232)
- [x] 2026-11-18 → 2026-11-18 — Webpay: ahora se trata como tarjeta (pide N° de voucher, marca pagado_con_tarjeta para la conciliación Transbank) y se muestra como "Webpay" en Registro de Ventas en vez de "Sin forma"
- [x] 2026-11-09 → 2026-11-09 — PDF de cotización: el tipo de ventana 55 decía "CORRDERAAL25"; corregido a "Corredera AL25" con una migración
- [x] 2026-11-03 → 2026-11-03 — Menú: tras dejar la app abierta y haber un deploy, al hacer clic en un módulo cargaba el módulo anterior; ahora la recarga automática va al módulo clickeado
- [x] 2026-11-03 → 2026-11-03 — Cuentas por Cobrar: seleccionar varias facturas de un cliente y bajar el "detalle de cobro" en Excel o PDF (folio, fecha, monto total, saldo, método de pago y glosa de cada factura). Falta lo mismo desde Registro de Ventas si se necesita
- [x] 2026-11-03 → 2026-11-03 — Producción: tiempos en "m² por mes": cola de corte (Lista para Corte → En Fabricación), fabricación, espera hasta instalar (Fabricadas OK → Instalada), m²/día de fabricación y panel de cuánto llevan hoy las obras en cada estado (indicador de atraso / faltan instaladores)
- [x] 2026-11-03 → 2026-11-03 — Producción: reporte de m² fabricados e instalados por mes (botón "m² por mes" en Taller) con promedio de 3 meses como línea base. Siguiente: registro por ventana y metas mensuales
- [ ] 2026-11-03 — Producción: tras tener 2-3 meses de datos confiables, fijar metas mensuales de m² y mostrar avance vs meta; registrar fabricado por ventana (fecha y quién)
- [x] 2026-11-03 → 2026-11-03 — Conciliación (movimientos crédito): la tabla "Facturas Bsale" ahora se ordena por saldo/total/fecha/folio y muestra paginación
- [x] 2026-11-02 → 2026-11-02 — Boletas: el resumen mensual se recalcula en cada sincronización con Bsale (septiembre mostraba 1 de 52 boletas) y ya no cuenta boletas anuladas
- [x] 2026-11-02 → 2026-11-02 — Banco: avisos para no dejar huecos al importar la cartola del portal (último movimiento cargado, desde qué fecha importar, alerta de hueco y de días hábiles sin movimientos)
- [x] 2026-11-02 → 2026-11-02 — Transbank: instrucciones paso a paso (panel desplegable) en la pestaña Documentos
- [x] 2026-11-02 → 2026-11-02 — Transbank: depósitos que el banco abona en varios movimientos se concilian (auto-match por combinación + asignación múltiple) y el modal de asignar se arregló (fecha, botón cortado, ya no ofrece movimientos asignados)
- [x] 2026-10-20 — Bot → calendario: "mañana vamos a medir donde Juanito Pérez / instalar donde Pedrito" crea el evento (calendario/recordatorios/visitas_cliente existentes) vinculado a la obra, y pregunta después si se hizo; también anotar cosas en el calendario (hecho 2026-10-27 con comandos fijos, sin IA: agenda medición/instalación/postventa, rangos de días, cierre "se instaló/se midió" y resumen 8:00; falta preguntar tras aprobar y postventa/otros en lenguaje libre)
- [x] 2026-10-26 → 2026-10-26 — Validar la cotización del proveedor (PDF Haustek) contra la orden de compra: faltantes, cantidades, sustituciones, descuentos, aritmética y numeración
- [x] 2026-10-26 → 2026-10-26 — Sync Bsale: los documentos anulados directamente en Bsale (state=1) quedan `anulado` en la app (casos 5280 y 5273)
- [x] 2026-10-26 → 2026-10-26 — Clientes: el RUT se normaliza (12345678-9) y se valida el dígito verificador al crear (Venta Express, ficha de cliente y Bsale)
- [x] 2026-10-22 → 2026-10-22 — Bot: aprobar cotizaciones por WhatsApp, Fase 1 (probada con un caso real: Marianela Becker #207); el abono en Operaciones muestra forma de pago y voucher
- [x] 2026-10-22 → 2026-10-22 — Winperfil: el total importado descuenta el descuento general del presupuesto (DTOTARIFA); antes las ofertas con descuento quedaban más caras que en Winperfil. Falta revisar las ya importadas con descuento
- [x] 2026-10-22 → 2026-10-22 — Venta Express: el modal de documento emitido ya no se cierra con clic fuera (reinicia solo con Nueva) y suma botón Enviar (correo/WhatsApp)
- [x] 2026-10-22 → 2026-10-22 — Cotización: descargar materiales y costos de la obra completa (botón en cotizacion-ver hacia el Resumen de Materiales + corrección de config en el cálculo)
- [x] 2026-10-21 → 2026-10-21 — Bot WhatsApp: "suma las facturas de haustek 270123, 270456 y 270789" (suma neto/IVA/total por folio, NC restan, avisa folios no encontrados o ambiguos)
- [x] 2026-10-21 → 2026-10-21 — Boletas/Facturas: botón Enviar con el PDF oficial de Bsale como archivo, por correo (adjunto) o WhatsApp (compartir / descargar y adjuntar), con registro del envío
- [x] 2026-10-21 → 2026-10-21 — Operaciones: el nombre del Elemento muestra un cuadro al pasar el mouse y un detalle completo del proyecto al hacer clic (cliente, contacto, qué se vendió, montos, notas)
- [x] 2026-10-21 → 2026-10-21 — Cotizaciones: enviar el PDF por WhatsApp como archivo (menú de compartir en celular; descarga + chat en computador), canal "WhatsApp · PDF"
- [x] 2026-10-21 → 2026-10-21 — Guía de uso para administración dentro de la app (/guia): flujo de venta, venta parcial, cobro, cambios del cliente, semáforo técnico, materiales y preguntas frecuentes
- [x] 2026-10-21 → 2026-10-21 — Semáforo técnico 🔴🟡🟢 de proyectos Winperfil: columna en Operaciones, pestaña "Pendientes técnicos" en Winperfil (buscar y vincular la oferta técnica conservando el precio, "pauta definitiva lista") y etiqueta + lista estimada en Materiales WP para ventas parciales
- [x] 2026-10-21 → 2026-10-21 — Venta parcial de un presupuesto Winperfil (elegir qué ventanas se vendieron al importar) y botón "Aprobar y cobrar" que abre la emisión en Facturación
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
