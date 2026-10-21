<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'

definePage({ meta: { layout: 'default' } })

const route = useRoute()

// Cada sección tiene un id: la guía se puede abrir directo en una sección (/guia#venta-parcial)
const abiertas = ref(['recorrido'])

const pasos = [
  { n: 1, titulo: 'Cotizar', detalle: 'Cotización o presupuesto de Winperfil' },
  { n: 2, titulo: 'Aprobar', detalle: 'El cliente compra' },
  { n: 3, titulo: 'Cobrar anticipo', detalle: 'Boleta o factura' },
  { n: 4, titulo: 'Pedir material', detalle: 'Orden de compra' },
  { n: 5, titulo: 'Medidas y pauta', detalle: 'Medidas definitivas' },
  { n: 6, titulo: 'Fabricar e instalar', detalle: 'Producción' },
  { n: 7, titulo: 'Cobrar saldo', detalle: 'Documento final' },
]

onMounted(async () => {
  const id = (route.hash || '').replace('#', '')
  if (id) {
    abiertas.value = [id]
    await nextTick()
    setTimeout(() => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 250)
  }
})
</script>

<template>
  <div class="guia">
    <div class="mb-6">
      <h4 class="text-h4 mb-1">Guía de uso · Administración</h4>
      <div class="text-body-2 text-medium-emphasis">
        Cómo se cotiza, se aprueba, se cobra y se pide el material. Pensada para quien lleva la parte administrativa.
        Última actualización: 21 de octubre de 2026.
      </div>
    </div>

    <VExpansionPanels v-model="abiertas" multiple variant="accordion">
      <!-- ── 1. Recorrido ──────────────────────────────────────────────── -->
      <VExpansionPanel id="recorrido" value="recorrido">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-map-marker-path</VIcon>El recorrido de una venta
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-4">
            Toda venta sigue los mismos pasos. En cada paso hay una pantalla del menú donde se hace. Si algo se sale
            de lo normal (el cliente compra solo una parte, o cambia algo), más abajo están los casos.
          </p>
          <div class="d-flex flex-wrap gap-3 mb-4">
            <VCard v-for="p in pasos" :key="p.n" variant="tonal" color="primary" style="min-width:140px; flex:1 1 140px">
              <VCardText class="py-3">
                <div class="text-caption">Paso {{ p.n }}</div>
                <div class="font-weight-medium">{{ p.titulo }}</div>
                <div class="text-caption text-medium-emphasis">{{ p.detalle }}</div>
              </VCardText>
            </VCard>
          </div>
          <VTable density="compact">
            <thead>
              <tr><th>Qué quieres hacer</th><th>Dónde (menú)</th></tr>
            </thead>
            <tbody>
              <tr><td>Hacer una cotización rápida con productos de la lista</td><td><strong>Ventas → Cotización rápida</strong> o <strong>Venta Express</strong></td></tr>
              <tr><td>Cotizar un proyecto con ventanas hechas en la app</td><td><strong>Ventas → Cotizar proyecto</strong></td></tr>
              <tr><td>Traer un presupuesto hecho en Winperfil</td><td><strong>Ventas → Winperfil</strong></td></tr>
              <tr><td>Ver, enviar o aprobar una cotización</td><td><strong>Ventas → Cotizaciones</strong></td></tr>
              <tr><td>Saber a quién llamar (cotizaciones sin respuesta)</td><td><strong>Ventas → Seguimiento</strong></td></tr>
              <tr><td>Emitir boleta o factura, ver lo cobrado</td><td><strong>Ventas → Facturación</strong></td></tr>
              <tr><td>Ver el estado de cada obra</td><td><strong>Producción → Operaciones</strong></td></tr>
              <tr><td>Materiales y hoja de cortes de una obra</td><td><strong>Producción → Producción</strong> (botón "Materiales WP")</td></tr>
              <tr><td>Órdenes de compra a proveedores</td><td><strong>Producción → Órdenes de Compra</strong></td></tr>
            </tbody>
          </VTable>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 2. Cotizar y enviar ───────────────────────────────────────── -->
      <VExpansionPanel id="cotizar-enviar" value="cotizar-enviar">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-send</VIcon>Enviar una cotización al cliente
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <ol class="ps-5 mb-4">
            <li class="mb-1">Entra a <strong>Ventas → Cotizaciones</strong> y busca la cotización.</li>
            <li class="mb-1">Pulsa el botón de enviar de esa fila.</li>
            <li class="mb-1">Elige <strong>WhatsApp</strong> (se abre WhatsApp con un mensaje y el link al PDF) o <strong>Correo (PDF adjunto)</strong> (llega un correo con el PDF como archivo adjunto, no un link).</li>
            <li class="mb-1">El destinatario aparece solo si el cliente tiene teléfono o correo guardado, pero <strong>puedes cambiarlo</strong> para mandarlo a otro.</li>
            <li class="mb-1">Pulsa <strong>Enviar</strong>.</li>
          </ol>
          <VAlert type="info" variant="tonal" density="compact" class="mb-3">
            Al enviar, la cotización queda como <strong>Enviada</strong> y se crea un recordatorio para volver a llamar al cliente en 3 días.
            Si el cliente no tenía correo y escribes uno, queda guardado en su ficha para la próxima vez.
          </VAlert>
          <p class="mb-0">
            El correo sale desde <strong>contacto@vialum.cl</strong>. Si la cotización fue entregada en persona, usa el botón
            <strong>"Entregada en persona"</strong> de esa misma ventana para dejar el registro.
          </p>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 3. Aprobar ────────────────────────────────────────────────── -->
      <VExpansionPanel id="aprobar" value="aprobar">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-check-decagram</VIcon>El cliente compró: aprobar la cotización
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <h6 class="text-h6 mb-2">A) Si el presupuesto está hecho en Winperfil</h6>
          <ol class="ps-5 mb-4">
            <li class="mb-1">Entra a <strong>Ventas → Winperfil</strong>. Arriba dice <em>Conectado</em> si la conexión funciona.</li>
            <li class="mb-1">
              Ajusta los filtros y pulsa <strong>Previsualizar</strong>: <strong>Desde</strong> y <strong>Hasta</strong>, la serie
              (normalmente A) y, si quieres, el nombre del cliente (puedes escribir solo una parte, en cualquier orden).
            </li>
            <li class="mb-1">Busca la fila del presupuesto y pulsa <strong>Importar</strong>.</li>
            <li class="mb-1">
              Se abre una ventana para <strong>confirmar</strong>: el estado, el color o material (PVC, Aluminio…), las especificaciones (EETT)
              y el <strong>total</strong> (neto y con IVA, puedes corregirlo).
            </li>
            <li class="mb-1">
              Pulsa <strong>Aprobar y cobrar</strong>: la cotización queda <strong>Aprobada</strong>, el proyecto aparece en Operaciones
              esperando las medidas, y se abre Facturación para emitir el anticipo.
            </li>
          </ol>
          <VAlert type="warning" variant="tonal" density="compact" class="mb-4">
            <strong>¿No aparece el presupuesto?</strong> La lista filtra por la fecha en que se creó el presupuesto <em>original</em>, no la de una oferta
            posterior. Si buscas una oferta nueva, pon un <strong>Desde</strong> anterior (por ejemplo, un par de meses atrás).
          </VAlert>
          <p class="text-body-2 mb-6">
            <strong>"Confirmar"</strong> deja la cotización con el estado que elijas sin abrir Facturación.
            <strong>"Dejar como está"</strong> cierra la ventana sin cambios (el presupuesto ya quedó importado).
          </p>

          <h6 class="text-h6 mb-2">B) Si la cotización se hizo en la app</h6>
          <ol class="ps-5 mb-0">
            <li class="mb-1">En <strong>Ventas → Cotizaciones</strong>, abre la cotización con el botón de <strong>ver</strong> (el ojo) de su fila.</li>
            <li class="mb-1">En la parte de arriba de la cotización pulsa <strong>Aprobar</strong> (o <strong>Rechazar</strong> si el cliente no compró).</li>
            <li>Luego sigue en <strong>Ventas → Facturación</strong> para cobrar.</li>
          </ol>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 4. Venta parcial ──────────────────────────────────────────── -->
      <VExpansionPanel id="venta-parcial" value="venta-parcial">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-window-closed-variant</VIcon>El cliente compró solo una parte del presupuesto
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-3">
            Pasa seguido: el presupuesto tiene 3 ventanas y el cliente pide solo 1. <strong>No hace falta volver a dibujar nada en Winperfil
            para vender</strong>: la venta se registra en la app en un minuto.
          </p>
          <ol class="ps-5 mb-4">
            <li class="mb-1">En <strong>Ventas → Winperfil</strong>, busca el presupuesto y pulsa <strong>Importar</strong>.</li>
            <li class="mb-1">
              En la ventana aparece <strong>"¿Qué ventanas se vendieron?"</strong> con todas las ventanas y su precio.
              <strong>Desmarca</strong> las que el cliente <em>no</em> compró.
            </li>
            <li class="mb-1">El total se recalcula solo con lo vendido. Revísalo (lo puedes ajustar si se acordó otro precio).</li>
            <li class="mb-1">Pulsa <strong>Aprobar y cobrar</strong> y emite la boleta o factura en Facturación.</li>
          </ol>
          <VAlert type="info" variant="tonal" density="compact" class="mb-3">
            Las ventanas que desmarcaste se quitan de la cotización, y el cambio queda anotado en el historial
            ("Venta parcial: se vendió v1 de 3 ventanas…"), con el total de antes y de después.
          </VAlert>
          <p class="mb-2"><strong>¿Y lo técnico?</strong> El proyecto queda marcado con el semáforo <span class="text-error font-weight-bold">rojo</span>:
            falta que alguien con acceso a Winperfil cree la oferta con solo lo vendido, para que los materiales y los cortes sean exactos.
            Eso no frena la venta ni el cobro. Está explicado en <a href="#semaforo" @click.prevent="abiertas = ['semaforo']">El semáforo técnico</a>.</p>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 5. Cobrar ─────────────────────────────────────────────────── -->
      <VExpansionPanel id="cobrar" value="cobrar">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-cash-register</VIcon>Cobrar: anticipo, saldo, boleta o factura
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-3">
            Todo se hace en <strong>Ventas → Facturación</strong>. Ahí aparecen las cotizaciones <strong>aprobadas</strong>
            (si una cotización no aparece, probablemente todavía no está aprobada).
          </p>
          <ol class="ps-5 mb-4">
            <li class="mb-1">En la fila de la cotización pulsa <strong>Emitir</strong>.</li>
            <li class="mb-1">
              En <strong>"¿Cuánto vas a facturar?"</strong> elige <strong>Total (100%)</strong>, <strong>Anticipo 50%</strong>,
              <strong>Anticipo 30%</strong> u <strong>Otro monto</strong>. Si ya hay un anticipo emitido, aparece la opción
              <strong>Saldo</strong> con el monto exacto que falta.
            </li>
            <li class="mb-1">Elige el <strong>tipo de documento</strong> (factura electrónica o boleta) y, si es factura, el cliente al que va.</li>
            <li class="mb-1">Indica la <strong>forma de pago</strong> (transferencia, tarjeta…). Puedes dividir el pago en varias formas con "Dividir".</li>
            <li class="mb-1">Si quieres, agrega una <strong>glosa</strong> (el documento sale con una sola línea con ese texto) y las <strong>referencias</strong> que necesite el cliente.</li>
            <li class="mb-1">Pulsa <strong>Generar</strong>.</li>
          </ol>
          <VAlert type="error" variant="tonal" density="compact" class="mb-3">
            <strong>El documento se emite de verdad en Bsale y queda declarado al SII.</strong> Revisa el monto, el tipo y el cliente antes de pulsar Generar.
          </VAlert>
          <p class="mb-2">
            Al expandir una fila verás el <strong>historial de facturación y cobro</strong>: qué se emitió, qué se ha cobrado y el saldo.
            El cobro se registra cuando el pago se concilia con el movimiento del banco (<strong>Finanzas → Conciliación</strong>).
          </p>
          <p class="mb-0">
            Los montos de las listas están en <strong>neto</strong> salvo que diga "con IVA". Los documentos siempre se emiten con IVA (19%).
          </p>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 6. Boleta directa en Bsale ────────────────────────────────── -->
      <VExpansionPanel id="bsale-directo" value="bsale-directo">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-link-variant</VIcon>Ya emití la boleta o factura directo en Bsale
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-3">
            Es normal cuando hay que atender rápido al cliente. Lo que pasa: los documentos de Bsale llegan a la app
            <strong>una vez al día, a las 07:00</strong>, pero llegan <strong>sin cotización</strong>. Hay que unirlos a la obra:
          </p>
          <ol class="ps-5 mb-4">
            <li class="mb-1">Asegúrate de que la venta ya esté registrada y <strong>aprobada</strong> en la app (ver "El cliente compró").</li>
            <li class="mb-1">Al día siguiente, entra a <strong>Ventas → Facturación</strong> y busca la cotización.</li>
            <li class="mb-1">Pulsa <strong>Vincular doc Bsale</strong> (o en el menú de la fila, <strong>"Vincular boleta/documento…"</strong>).</li>
            <li class="mb-1">Busca el documento por cliente, folio o fecha y elígelo. El porcentaje se calcula solo respecto al total de la cotización.</li>
          </ol>
          <VAlert type="info" variant="tonal" density="compact">
            Si la boleta fue a <strong>"Consumidor Final"</strong> no trae nombre del cliente: búscala por <strong>folio</strong>, monto o fecha.
            Para evitarlo, lo ideal es emitir desde <strong>Facturación</strong> (queda unida a la obra automáticamente).
          </VAlert>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 7. Cambios del cliente ────────────────────────────────────── -->
      <VExpansionPanel id="cambios" value="cambios">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-swap-horizontal</VIcon>El cliente cambia o agrega algo después de aprobar
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-3">
            Si el cliente agrega una ventana o cambia algo (por ejemplo, que corran 2 hojas en vez de 1) y el precio cambia, quien maneja Winperfil
            crea una <strong>oferta nueva</strong> del mismo presupuesto. En la app se <strong>reemplaza la oferta de la misma cotización</strong>
            (no se crea una cotización duplicada):
          </p>
          <ol class="ps-5 mb-4">
            <li class="mb-1">Entra a <strong>Ventas → Winperfil</strong> y busca el presupuesto (recuerda poner un <strong>Desde</strong> anterior).</li>
            <li class="mb-1">En la fila de la oferta nueva aparece el botón <strong>Cambiar oferta</strong>. Púlsalo.</li>
            <li class="mb-1">Elige la cotización que reemplaza, escribe el <strong>motivo</strong> ("el cliente agregó una ventana") y confirma.</li>
            <li class="mb-1">En la ventana que se abre, fija el <strong>total final</strong> acordado.</li>
          </ol>
          <p class="mb-2">
            <strong>Qué queda igual:</strong> el estado de producción, el pedido a proveedor, el material y el cliente.
            <strong>Qué cambia:</strong> las ventanas y el total.
          </p>
          <p class="mb-3">
            <strong>Facturación se ajusta sola:</strong> el saldo por emitir pasa a ser <em>nuevo total − lo ya emitido</em>.
            Por ejemplo, si ya se emitió un anticipo del 50% y el total sube, el saldo sube.
            Todos los cambios quedan en <strong>"Modificaciones del proyecto"</strong>, que se ve en Facturación (fila expandida) y en la ficha de la cotización, con el total antes y después y el motivo.
          </p>
          <VAlert type="warning" variant="tonal" density="compact">
            Si el total <strong>baja</strong> por debajo de lo que ya se facturó, hay que emitir una <strong>nota de crédito en Bsale</strong>.
            La app la reconoce y la vincula en la sincronización del día siguiente.
          </VAlert>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 8. Semáforo ───────────────────────────────────────────────── -->
      <VExpansionPanel id="semaforo" value="semaforo">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-traffic-light</VIcon>El semáforo técnico (🔴 🟡 🟢)
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <p class="mb-3">
            Indica, para cada obra de Winperfil ya aprobada, <strong>si las cantidades de material son exactas o todavía no</strong>.
            Se ve en <strong>Producción → Operaciones</strong> (columna <em>Técnica</em>), en la pestaña
            <strong>Ventas → Winperfil → Pendientes técnicos</strong> y en la ventana <em>Materiales WP</em>.
          </p>
          <VTable density="comfortable" class="mb-4">
            <thead><tr><th style="width:150px">Color</th><th>Qué significa</th><th>Quién hace algo</th></tr></thead>
            <tbody>
              <tr>
                <td><VIcon color="error" size="16">mdi-circle</VIcon> Rojo</td>
                <td>Venta parcial: falta crear en Winperfil la oferta con solo lo vendido.</td>
                <td>Quien tiene Winperfil crea la oferta y en <em>Pendientes técnicos</em> pulsa <strong>Buscar oferta en Winperfil → Vincular</strong>. El precio acordado se conserva.</td>
              </tr>
              <tr>
                <td><VIcon color="warning" size="16">mdi-circle</VIcon> Amarillo</td>
                <td>Se esperan las medidas definitivas (puede haber material ya pedido con medidas aproximadas).</td>
                <td>Cuando las medidas definitivas están en Winperfil y la pauta está lista, en <em>Pendientes técnicos</em> pulsa <strong>Pauta definitiva lista</strong>.</td>
              </tr>
              <tr>
                <td><VIcon color="success" size="16">mdi-circle</VIcon> Verde</td>
                <td>La oferta y las medidas son las definitivas. No hay que hacer nada.</td>
                <td>—</td>
              </tr>
            </tbody>
          </VTable>
          <p class="mb-0">
            <strong>Administración</strong> solo necesita fijarse en que, tras una venta parcial, el proyecto quede en la lista de pendientes
            y avisar a quien maneja Winperfil. Las obras que ya pasaron a <em>Lista para Corte</em> o <em>En Fabricación</em> salen en verde solas.
          </p>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 9. Material ───────────────────────────────────────────────── -->
      <VExpansionPanel id="material" value="material">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-cart-arrow-down</VIcon>Pedir el material
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <ol class="ps-5 mb-4">
            <li class="mb-1">Entra a <strong>Producción → Producción</strong> y busca la obra. Pulsa <strong>Materiales WP</strong>.</li>
            <li class="mb-1">
              En la pestaña <strong>Lista de compra</strong> verás lo que hay que comprar: barras de perfil (con su largo y color),
              accesorios, juntas y vidrios por medida. Una etiqueta indica si las cantidades son
              <strong>exactas de Winperfil</strong> o <strong>estimadas</strong>.
            </li>
            <li class="mb-1">Marca lo que quieras pedir y pulsa <strong>Generar orden de compra</strong>. Elige el proveedor.</li>
            <li class="mb-1">La orden se puede descargar en PDF o Excel y <strong>enviar por correo o WhatsApp</strong> desde <strong>Producción → Órdenes de Compra</strong>. Al enviarla, la obra queda marcada con "pedido a proveedor".</li>
          </ol>
          <p class="mb-2"><strong>¿Se pidió material antes de tener las medidas definitivas?</strong></p>
          <p class="mb-3">
            Entra otra vez a <strong>Materiales WP</strong> y abre la pestaña <strong>Falta pedir</strong>: compara lo que Winperfil calcula ahora
            con lo que ya se pidió en las órdenes anteriores de esa obra, y muestra <strong>lo que falta</strong> y <strong>lo que se pidió de más</strong>.
            El botón <strong>Generar orden complementaria</strong> crea una orden solo con la diferencia.
          </p>
          <VAlert type="info" variant="tonal" density="compact">
            Las otras pestañas son <strong>Despiece por ventana</strong> y <strong>Hoja de cortes</strong> (cómo se cortan las barras).
            Si algo no se calcula, el botón <strong>Recalcular</strong> vuelve a pedirlo a Winperfil.
          </VAlert>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 10. Preguntas frecuentes ──────────────────────────────────── -->
      <VExpansionPanel id="faq" value="faq">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-help-circle-outline</VIcon>¿Qué hago si…?
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <div class="mb-4">
            <div class="font-weight-medium">…la lista de Winperfil sale vacía aunque dice "Conectado"?</div>
            <div class="text-body-2">
              Primero revisa las fechas de los filtros. Si están bien, el servicio de Winperfil del computador puede estar detenido:
              avisa a quien administra ese computador para que lo reinicie. Mientras tanto, lo ya importado se sigue viendo en la pestaña <em>Sincronizados</em>.
            </div>
          </div>
          <div class="mb-4">
            <div class="font-weight-medium">…una cotización no aparece en Facturación?</div>
            <div class="text-body-2">Solo aparecen las <strong>aprobadas</strong> (y las que ya están en producción, entregadas o facturadas). Apruébala primero.</div>
          </div>
          <div class="mb-4">
            <div class="font-weight-medium">…emití un documento equivocado?</div>
            <div class="text-body-2">
              El botón <strong>Anular</strong> de la app solo cambia el estado interno: <strong>no anula nada en Bsale</strong>.
              Primero hay que emitir la nota de crédito o anular en Bsale, y después marcarlo aquí.
            </div>
          </div>
          <div class="mb-4">
            <div class="font-weight-medium">…envié la cotización por correo a una dirección equivocada?</div>
            <div class="text-body-2">El correo no se puede recuperar. Corrige el correo en la ficha del cliente y vuelve a enviarla a la dirección correcta.</div>
          </div>
          <div class="mb-4">
            <div class="font-weight-medium">…al enviar por correo dice que falta configurar el correo?</div>
            <div class="text-body-2">Es un tema del servidor. Avisa a quien administra el sistema; mientras tanto, envía por WhatsApp.</div>
          </div>
          <div class="mb-4">
            <div class="font-weight-medium">…la boleta que hice hoy no aparece en la app?</div>
            <div class="text-body-2">Los documentos de Bsale llegan una vez al día, a las 07:00. Aparecerá mañana, sin cotización, y hay que vincularla (ver "Ya emití la boleta directo en Bsale").</div>
          </div>
          <div>
            <div class="font-weight-medium">…el precio de una cotización de Winperfil figura como "Precio ajustado" con un candado?</div>
            <div class="text-body-2">
              Significa que el precio fue fijado a mano (por ejemplo, el acordado con el cliente) y la sincronización con Winperfil ya no lo cambia.
              Es lo normal en una obra aprobada.
            </div>
          </div>
        </VExpansionPanelText>
      </VExpansionPanel>

      <!-- ── 11. Glosario ──────────────────────────────────────────────── -->
      <VExpansionPanel id="glosario" value="glosario">
        <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
          <VIcon class="me-2" color="primary">mdi-book-alphabet</VIcon>Palabras que se usan
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <VTable density="compact">
            <tbody>
              <tr><td class="font-weight-medium" style="width:200px">Presupuesto / Oferta</td><td>En Winperfil, un presupuesto puede tener varias <strong>ofertas</strong> (versiones con otro precio o cambios). La que vale es la última aceptada.</td></tr>
              <tr><td class="font-weight-medium">Neto / con IVA</td><td>El neto es el precio sin IVA. Con IVA es el neto más 19%. Los documentos se emiten con IVA.</td></tr>
              <tr><td class="font-weight-medium">Anticipo / Abono / Saldo</td><td>Anticipo: primera parte que paga el cliente. Saldo: lo que falta por facturar o cobrar.</td></tr>
              <tr><td class="font-weight-medium">Documento sin cotización</td><td>Boleta o factura emitida directo en Bsale que aún no se une a ninguna obra.</td></tr>
              <tr><td class="font-weight-medium">EETT</td><td>Especificaciones técnicas de la obra (por ejemplo, el perfil o la línea acordada).</td></tr>
              <tr><td class="font-weight-medium">Pauta</td><td>El listado de cortes y materiales definitivo de una obra, ya con las medidas finales.</td></tr>
              <tr><td class="font-weight-medium">Precio ajustado (candado)</td><td>Precio fijado a mano que Winperfil no puede cambiar al sincronizar.</td></tr>
              <tr><td class="font-weight-medium">Oferta técnica</td><td>La oferta de Winperfil que corresponde exactamente a lo vendido y fabricado (se usa en ventas parciales).</td></tr>
            </tbody>
          </VTable>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>

    <div class="text-caption text-medium-emphasis mt-6">
      ¿Falta algo o algo no coincide con lo que ves en pantalla? Avísale a quien administra el sistema para actualizar esta guía.
    </div>
  </div>
</template>

<style scoped>
.guia {
  max-width: 980px;
}
.guia ol li {
  line-height: 1.6;
}
</style>
