<template>
    <v-card>
      <v-card-title class="d-flex align-center flex-wrap gap-2 pb-2">
        <span>Cotizaciones</span>
        <v-spacer />
        <v-text-field
          v-model="buscar"
          placeholder="Buscar cliente, ID..."
          prepend-inner-icon="mdi-magnify"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          style="max-width:260px"
        />
        <v-select
          v-model="filtroOrigen"
          :items="['Todos', 'Winperfil', 'Manual']"
          label="Origen"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width:130px"
        />
        <v-select
          v-model="filtroEstado"
          :items="['Todos', 'Evaluación', 'Aprobada', 'En Producción', 'Entregada', 'Facturada', 'Rechazada']"
          label="Estado"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width:150px"
        />
        <v-btn
          color="secondary"
          variant="tonal"
          prepend-icon="mdi-window-open"
          @click="router.push('/cotizaciones/importar-pvc')"
        >
          Importar WINPERFIL
        </v-btn>
        <v-btn color="primary" @click="router.push({ name: 'cotizador' })">Nueva</v-btn>
      </v-card-title>

      <v-data-table
        :headers="headers"
        :items="cotizacionesFiltradas"
        :items-per-page="20"
        class="elevation-1"
      >
        <template #item.cliente="{ item }">
          <div class="d-flex align-center gap-1">
            <span>{{ item.cliente?.razon_social || `${item.cliente?.first_name || ''} ${item.cliente?.last_name || ''}`.trim() || '—' }}</span>
            <v-chip
              v-if="item.winperfil_numero"
              size="x-small"
              color="deep-purple"
              variant="tonal"
              class="ml-1"
            >
              WP {{ item.winperfil_serie }}-{{ item.winperfil_numero }}
            </v-chip>
          </div>
        </template>
  
        <template #item.vendedor="{ item }">
          {{ item.vendedor?.name || '—' }}
        </template>
  
        <template #item.estado="{ item }">
          <v-menu v-if="['Evaluación','Aprobada','En Producción','Entregada'].includes(item.estado?.nombre)">
            <template #activator="{ props }">
              <v-chip
                v-bind="props"
                :color="getEstadoColor(item.estado?.nombre)"
                style="cursor:pointer"
                append-icon="mdi-chevron-down"
              >
                {{ item.estado?.nombre || '—' }}
              </v-chip>
            </template>
            <v-list density="compact">
                  <v-list-item
                v-if="item.estado?.nombre === 'Evaluación'"
                prepend-icon="mdi-check-circle"
                title="Aprobar"
                @click="cambiarEstado(item, 'Aprobada')"
              />
              <v-list-item
                v-if="item.estado?.nombre === 'Aprobada'"
                prepend-icon="mdi-factory"
                title="Pasar a Producción"
                @click="cambiarEstado(item, 'En Producción')"
              />
              <v-list-item
                v-if="item.estado?.nombre === 'En Producción'"
                prepend-icon="mdi-truck-delivery"
                title="Marcar Entregada"
                @click="cambiarEstado(item, 'Entregada')"
              />
              <v-list-item
                v-if="item.estado?.nombre === 'Entregada'"
                prepend-icon="mdi-currency-usd"
                title="Marcar Facturada"
                @click="cambiarEstado(item, 'Facturada')"
              />
              <v-list-item
                v-if="['Evaluación','Aprobada'].includes(item.estado?.nombre)"
                prepend-icon="mdi-close-circle"
                title="Rechazar"
                @click="cambiarEstado(item, 'Rechazada')"
              />
            </v-list>
          </v-menu>
          <v-chip v-else :color="getEstadoColor(item.estado?.nombre)">
            {{ item.estado?.nombre || '—' }}
          </v-chip>
        </template>
  
        <template #item.total="{ item }">
          ${{ Number(item.total).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
        </template>

        <template #item.acciones="{ item }">
          <v-btn icon @click="verCotizacion(item)">
            <v-icon>mdi-eye</v-icon>
          </v-btn>
          <v-btn
            icon
            @click="editarCotizacion(item)"
            :disabled="item.estado?.nombre === 'Aprobada'"
          >
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon @click="descargarPDF(item.id)" title="Descargar Cotización PDF" :loading="pdfCargando === item.id">
            <v-icon>mdi-file-pdf-box</v-icon>
          </v-btn>
          <v-btn icon :to="{ name: 'produccion-materiales-id', params: { id: item.id } }" title="Materiales y costos (descargar Excel)">
            <v-icon>mdi-microsoft-excel</v-icon>
          </v-btn>
          <v-btn icon color="green" @click="abrirEnviar(item)"
            :title="item.enviado_at ? ('Enviada el ' + (item.enviado_at || '').slice(0,10) + ' — reenviar') : 'Enviar cotización por WhatsApp'">
            <v-badge v-if="item.enviado_at" dot color="success"><v-icon>mdi-whatsapp</v-icon></v-badge>
            <v-icon v-else>mdi-whatsapp</v-icon>
          </v-btn>
          <v-btn icon color="orange" @click="descargarOT(item.id)" title="Descargar Orden de Trabajo">
            <v-icon>mdi-file-document-outline</v-icon>
          </v-btn>
          <v-btn icon @click="duplicarCotizacion(item)">
            <v-icon>mdi-content-copy</v-icon>
          </v-btn>
          <v-btn
            v-if="item.adjunto_winperfil"
            icon
            color="blue"
            :href="item.adjunto_winperfil"
            target="_blank"
            title="Ver PDF WINPERFIL"
          >
            <v-icon>mdi-paperclip</v-icon>
          </v-btn>

        </template>
      </v-data-table>
    </v-card>

    <!-- Enviar cotización (WhatsApp o correo con el PDF adjunto) -->
    <v-dialog v-model="dialogEnviar.show" max-width="520">
      <v-card v-if="dialogEnviar.item">
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon :color="dialogEnviar.canal === 'email' ? 'primary' : 'green'">{{ dialogEnviar.canal === 'email' ? 'mdi-email-outline' : 'mdi-whatsapp' }}</v-icon>
          Enviar cotización #{{ dialogEnviar.item.id }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <v-btn
              size="small" :variant="dialogEnviar.canal === 'whatsapp' ? 'flat' : 'outlined'"
              :color="dialogEnviar.canal === 'whatsapp' ? 'green' : undefined"
              prepend-icon="mdi-whatsapp" @click="elegirCanal('whatsapp')"
            >
              WhatsApp · link
            </v-btn>
            <v-btn
              size="small" :variant="dialogEnviar.canal === 'wapdf' ? 'flat' : 'outlined'"
              :color="dialogEnviar.canal === 'wapdf' ? 'green' : undefined"
              prepend-icon="mdi-file-pdf-box" @click="elegirCanal('wapdf')"
            >
              WhatsApp · PDF
            </v-btn>
            <v-btn
              size="small" :variant="dialogEnviar.canal === 'email' ? 'flat' : 'outlined'"
              :color="dialogEnviar.canal === 'email' ? 'primary' : undefined"
              prepend-icon="mdi-email-outline" @click="elegirCanal('email')"
            >
              Correo · PDF
            </v-btn>
          </div>
          <v-text-field v-if="dialogEnviar.canal !== 'email'" v-model="dialogEnviar.telefono" label="Teléfono (WhatsApp)" placeholder="Ej: 9 1234 5678"
            density="compact" variant="outlined" prepend-inner-icon="mdi-phone" class="mb-3" />
          <v-text-field v-else v-model="dialogEnviar.email" label="Correo del destinatario" placeholder="cliente@empresa.cl" type="email"
            density="compact" variant="outlined" prepend-inner-icon="mdi-email-outline" class="mb-3" />
          <v-textarea v-model="dialogEnviar.mensaje" label="Mensaje" rows="6" density="compact" variant="outlined" auto-grow />
          <p v-if="dialogEnviar.canal === 'whatsapp'" class="text-caption text-medium-emphasis mt-1">
            Se abrirá WhatsApp con el mensaje y el link al PDF. La cotización queda como <strong>Enviada</strong> y se crea un recordatorio de seguimiento en 3 días.
          </p>
          <p v-else-if="dialogEnviar.canal === 'wapdf'" class="text-caption text-medium-emphasis mt-1">
            Envía el <strong>PDF como archivo</strong>, no como link. En el <strong>celular</strong> se abre el menú de compartir con el PDF ya adjunto
            (elige WhatsApp y el contacto). En el <strong>computador</strong> se descarga el PDF y se abre el chat: tú lo adjuntas con el clip.
            La cotización queda como <strong>Enviada</strong> con recordatorio en 3 días.
          </p>
          <p v-else class="text-caption text-medium-emphasis mt-1">
            Se envía un correo con el <strong>PDF adjunto</strong>. La cotización queda como <strong>Enviada</strong> y se crea un recordatorio de seguimiento en 3 días.
          </p>
          <v-alert v-if="dialogEnviar.aviso" color="success" variant="tonal" density="compact" class="mt-2 text-caption" icon="mdi-file-pdf-box">{{ dialogEnviar.aviso }}</v-alert>
          <v-alert v-if="dialogEnviar.error" color="error" variant="tonal" density="compact" class="mt-2 text-caption">{{ dialogEnviar.error }}</v-alert>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3 flex-wrap gap-1">
          <v-btn variant="text" size="small" :disabled="dialogEnviar.enviando"
            :loading="dialogEnviar.presencial" @click="marcarEntregaPresencial">
            <v-icon start>mdi-handshake-outline</v-icon>Entregada en persona
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="dialogEnviar.show = false">{{ dialogEnviar.aviso ? 'Cerrar' : 'Cancelar' }}</v-btn>
          <v-btn
            v-if="!dialogEnviar.aviso"
            :color="dialogEnviar.canal === 'email' ? 'primary' : 'green'"
            :loading="dialogEnviar.enviando || (dialogEnviar.canal === 'wapdf' && dialogEnviar.pdfCargando)"
            @click="dialogEnviar.canal === 'wapdf' ? enviarPdfWhatsapp() : enviarCotizacion()"
          >
            <v-icon start>{{ dialogEnviar.canal === 'email' ? 'mdi-email-outline' : dialogEnviar.canal === 'wapdf' ? 'mdi-file-pdf-box' : 'mdi-whatsapp' }}</v-icon>
            {{ dialogEnviar.canal === 'email' ? 'Enviar por correo' : dialogEnviar.canal === 'wapdf' ? 'Compartir PDF' : 'Enviar por WhatsApp' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </template>
  
  <script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import api from '@/axiosInstance'
  import { asegurarGraficosCotizacion } from '@/composables/useSvgToPng'

  const router = useRouter()
  const cotizaciones   = ref([])
  const buscar         = ref('')
  const filtroOrigen   = ref('Todos')
  const filtroEstado   = ref('Todos')

  const cotizacionesFiltradas = computed(() => {
    let list = cotizaciones.value

    if (filtroOrigen.value === 'Winperfil') list = list.filter(c => c.winperfil_numero)
    if (filtroOrigen.value === 'Manual')    list = list.filter(c => !c.winperfil_numero)

    if (filtroEstado.value !== 'Todos')
      list = list.filter(c => c.estado?.nombre === filtroEstado.value)

    if (buscar.value?.trim()) {
      const q = buscar.value.toLowerCase()
      list = list.filter(c => {
        const nombre = (c.cliente?.razon_social || `${c.cliente?.first_name||''} ${c.cliente?.last_name||''}`).toLowerCase()
        const id     = String(c.id)
        const wp     = c.winperfil_numero ? `${c.winperfil_serie}-${c.winperfil_numero}` : ''
        return nombre.includes(q) || id.includes(q) || wp.toLowerCase().includes(q)
      })
    }

    return list
  })
  
  const headers = [
    { title: 'ID', value: 'id' },
    { title: 'Cliente', value: 'cliente' },
    { title: 'Vendedor', value: 'vendedor' },
    { title: 'Fecha', value: 'fecha' },
    { title: 'Estado', value: 'estado' },
    { title: 'Total Neto', value: 'total' },
    { title: 'Acciones', value: 'acciones', sortable: false },
  ]
  
  const getEstadoColor = (estadoNombre) => {
    switch (estadoNombre) {
      case 'Evaluación':    return 'grey'
      case 'Aprobada':      return 'green'
      case 'En Producción': return 'blue'
      case 'Entregada':     return 'purple'
      case 'Facturada':     return 'teal'
      case 'Rechazada':     return 'red'
      case 'Anulada':       return 'red'
      case 'Enviada':       return 'orange'
      default:              return 'grey'
    }
  }

  const cambiarEstado = async (item, nuevoEstado) => {
    if (!confirm(`¿Cambiar cotización #${item.id} a "${nuevoEstado}"?`)) return
    try {
      const { data } = await api.patch(`/api/cotizaciones/${item.id}/estado`, { estado: nuevoEstado })
      item.estado = data.estado
      if (nuevoEstado === 'Aprobada') {
        router.push('/facturacion')
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error al cambiar el estado.')
    }
  }
  
  const verCotizacion = (item) => {
    router.push(`/cotizacion-ver?id=${item.id}`)
  }
  
  const editarCotizacion = (item) => {
    if (item.adjunto_winperfil) {
      router.push(`/cotizaciones/importar-pvc?edit=${item.id}`)
    } else {
      router.push(`/cotizador?id=${item.id}`)
    }
  }

    const duplicarCotizacion = async (item) => {
  if (!confirm(`¿Deseas duplicar la cotización #${item.id}?`)) return

  try {
    const res = await api.post(`/api/cotizaciones/${item.id}/duplicar`)
    alert('Cotización duplicada con éxito')
    router.push(`/cotizador?id=${res.data.id}`)
  } catch (error) {
    console.error(error)
    alert('Error al duplicar la cotización')
  }
}

const descargarOT = async (cotizacionId) => {
  try {
    const response = await api.get(`/api/cotizaciones/${cotizacionId}/orden-trabajo`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `OT_${cotizacionId}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch (error) {
    console.error('Error al descargar OT:', error)
    alert('Error al descargar la Orden de Trabajo.')
  }
}

const pdfCargando = ref(null) // ID de la cotización cuyo PDF está generándose

const descargarPDF = async (cotizacionId) => {
  pdfCargando.value = cotizacionId
  try {
    // 1. Garantizar los PNGs de las ventanas Winperfil (genera y GUARDA los que falten)
    await asegurarGraficosCotizacion(api, cotizacionId)

    // 2. Descargar PDF (el server usa los PNGs de la BD)
    const response = await api.get(`/api/cotizaciones/${cotizacionId}/pdf`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `cotizacion_${cotizacionId}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch (error) {
    console.error('Error al descargar PDF:', error)
    alert('Error al descargar el PDF. Verifica que estás autenticado.')
  } finally {
    pdfCargando.value = null
  }
}

// ── Enviar cotización (WhatsApp) ──────────────────────────────────
const dialogEnviar = ref({
  show: false, enviando: false, presencial: false, item: null, canal: 'whatsapp', telefono: '', email: '',
  mensaje: '', mensajeWhatsapp: '', mensajeWaPdf: '', mensajeCorreo: '', error: null, aviso: null,
  pdfArchivo: null, pdfBlob: null, pdfCargando: false,
})

// Cada canal tiene su propio texto: WhatsApp·link lleva el link; WhatsApp·PDF y correo llevan el PDF
const claveMensaje = { whatsapp: 'mensajeWhatsapp', wapdf: 'mensajeWaPdf', email: 'mensajeCorreo' }

function elegirCanal(canal) {
  const d = dialogEnviar.value
  if (d.canal === canal) return
  // conserva lo que se haya editado del mensaje del canal que se deja
  d[claveMensaje[d.canal]] = d.mensaje
  d.canal = canal
  d.mensaje = d[claveMensaje[canal]]
  d.error = null
  d.aviso = null
  if (canal === 'wapdf') prepararPdf()
}

// El PDF se genera ANTES del clic final: el navegador solo deja abrir el menú de compartir
// si el clic es reciente, y generar el PDF puede tardar unos segundos.
async function prepararPdf() {
  const d = dialogEnviar.value
  if (d.pdfArchivo || d.pdfCargando || !d.item) return
  d.pdfCargando = true
  try {
    await asegurarGraficosCotizacion(api, d.item.id)
    const res = await api.get(`/api/cotizaciones/${d.item.id}/pdf`, { responseType: 'blob' })
    d.pdfBlob = res.data
    d.pdfArchivo = new File([res.data], `Cotizacion-${d.item.id}-Vialum.pdf`, { type: 'application/pdf' })
  } catch (e) {
    d.error = 'No se pudo generar el PDF. Intenta de nuevo.'
  } finally {
    d.pdfCargando = false
  }
}

async function abrirEnviar(item) {
  const nombre = item.cliente?.razon_social
    || `${item.cliente?.first_name || ''} ${item.cliente?.last_name || ''}`.trim()
  const tel = item.cliente?.telefono || item.cliente?.phone || ''
  // Link público por TOKEN (no expone el ID). Usa el token de la fila o lo pide al backend.
  let pdfUrl = item.public_token
    ? `${window.location.origin}/p/cotizacion/${item.public_token}`
    : ''
  if (!pdfUrl) {
    try {
      const { data } = await api.get(`/api/cotizaciones/${item.id}/public-link`)
      pdfUrl = data.url
      item.public_token = data.token
    } catch (e) { /* el backend regenerará el link al enviar */ }
  }
  const linkTxt = pdfUrl ? `\n\nTe comparto la cotización #${item.id} de Vialum. Puedes verla y descargarla acá:\n${pdfUrl}` : `\n\nTe comparto la cotización #${item.id} de Vialum.`
  const mensaje = `${nombre ? 'Hola ' + nombre + ',' : 'Hola,'}${linkTxt}\n\nCualquier duda quedo atento. ¡Saludos!`
  // Correo por defecto: el de la persona de contacto, si la hay; si no, el del cliente
  const email = item.contacto?.email || item.cliente?.email || ''
  const atencion = item.contacto_nombre || nombre
  const vendedor = item.vendedor?.name || 'Vialum'
  const mensajeCorreo = `${atencion ? 'Hola ' + atencion + ',' : 'Hola,'}\n\nTe envío adjunta la cotización #${item.id} de Vialum. Cualquier duda quedo atento.\n\nSaludos,\n${vendedor}`
  const mensajeWaPdf = `${nombre ? 'Hola ' + nombre + ',' : 'Hola,'}\n\nTe envío adjunta la cotización #${item.id} de Vialum. Cualquier duda quedo atento. ¡Saludos!`
  dialogEnviar.value = {
    show: true, enviando: false, presencial: false, item, canal: 'whatsapp',
    telefono: tel, email, mensaje, mensajeWhatsapp: mensaje, mensajeWaPdf, mensajeCorreo, error: null, aviso: null,
    pdfArchivo: null, pdfBlob: null, pdfCargando: false,
  }
}

async function marcarEntregaPresencial() {
  const d = dialogEnviar.value
  d.presencial = true
  d.error = null
  try {
    const { data } = await api.post(`/api/cotizaciones/${d.item.id}/enviar`, { via: 'presencial' })
    d.item.enviado_at = data.enviado_at || new Date().toISOString()
    d.show = false
  } catch (e) {
    d.error = e.response?.data?.message || 'No se pudo marcar la entrega'
  } finally {
    d.presencial = false
  }
}

async function enviarCotizacion() {
  const d = dialogEnviar.value
  d.enviando = true
  d.error = null
  try {
    // Garantizar que el PDF público que se comparte tenga las imágenes Winperfil
    await asegurarGraficosCotizacion(api, d.item.id)
    const porCorreo = d.canal === 'email'
    if (porCorreo && !d.email) throw { response: { data: { message: 'Ingresa el correo del destinatario' } } }
    const { data } = await api.post(`/api/cotizaciones/${d.item.id}/enviar`, porCorreo
      ? { via: 'email', email: d.email, mensaje: d.mensaje || undefined }
      : { via: 'whatsapp', telefono: d.telefono || undefined, mensaje: d.mensaje || undefined })
    if (data.wa_url) window.open(data.wa_url, '_blank')
    // Reflejar el envío en la fila
    d.item.enviado_at = data.enviado_at || new Date().toISOString()
    d.show = false
  } catch (e) {
    d.error = e.response?.data?.message || 'No se pudo enviar'
  } finally {
    d.enviando = false
  }
}

// Teléfono chileno → solo dígitos con código de país (9 dígitos que parten con 9 = celular sin 56)
function telefonoParaWhatsapp(tel) {
  const digitos = String(tel || '').replace(/\D+/g, '')
  return digitos.length === 9 && digitos.startsWith('9') ? `56${digitos}` : digitos
}

// Deja el registro del envío (queda "Enviada" y se crea el recordatorio de seguimiento)
async function registrarEnvioPdf(d, destino) {
  const { data } = await api.post(`/api/cotizaciones/${d.item.id}/enviar`, {
    via: 'whatsapp', solo_registrar: true, telefono: destino || undefined,
  })
  d.item.enviado_at = data.enviado_at || new Date().toISOString()
}

// WhatsApp · PDF: el PDF viaja como ARCHIVO, no como link
async function enviarPdfWhatsapp() {
  const d = dialogEnviar.value
  d.error = null
  d.aviso = null
  if (!d.pdfArchivo) await prepararPdf()
  if (!d.pdfArchivo) return
  d.enviando = true
  try {
    // 1) Celular (y navegadores que lo permiten): menú de compartir con el PDF adjunto
    if (navigator.canShare && navigator.canShare({ files: [d.pdfArchivo] })) {
      try {
        await navigator.share({ files: [d.pdfArchivo], text: d.mensaje, title: `Cotización #${d.item.id}` })
        await registrarEnvioPdf(d, d.telefono)
        d.show = false
        return
      } catch (e) {
        if (e?.name === 'AbortError') return  // cerró el menú sin compartir: no se registra nada
        // cualquier otro fallo: se sigue con el plan 2
      }
    }
    // 2) Computador: descarga el PDF y abre el chat con el mensaje; el PDF se adjunta con el clip
    const url = window.URL.createObjectURL(d.pdfBlob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', d.pdfArchivo.name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)

    const tel = telefonoParaWhatsapp(d.telefono)
    window.open(tel ? `https://wa.me/${tel}?text=${encodeURIComponent(d.mensaje)}` : 'https://web.whatsapp.com/', '_blank')
    await registrarEnvioPdf(d, d.telefono)
    d.aviso = `Se descargó «${d.pdfArchivo.name}». En WhatsApp pulsa el clip (📎) → Documento y elige ese archivo: es el último de tu carpeta Descargas.`
  } catch (e) {
    d.error = e.response?.data?.message || 'No se pudo preparar el envío'
  } finally {
    d.enviando = false
  }
}

  onMounted(async () => {
    try {
      const res = await api.get('/api/cotizaciones')
      cotizaciones.value = Array.isArray(res.data) ? res.data : []
    } catch (error) {
      console.error('Error al cargar cotizaciones:', error)
      cotizaciones.value = []
    }
  })
  </script>
  