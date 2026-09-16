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

    <!-- Enviar cotización (WhatsApp) -->
    <v-dialog v-model="dialogEnviar.show" max-width="520">
      <v-card v-if="dialogEnviar.item">
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="green">mdi-whatsapp</v-icon>
          Enviar cotización #{{ dialogEnviar.item.id }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-text-field v-model="dialogEnviar.telefono" label="Teléfono (WhatsApp)" placeholder="Ej: 9 1234 5678"
            density="compact" variant="outlined" prepend-inner-icon="mdi-phone" class="mb-3" />
          <v-textarea v-model="dialogEnviar.mensaje" label="Mensaje" rows="6" density="compact" variant="outlined" auto-grow />
          <p class="text-caption text-medium-emphasis mt-1">
            Se abrirá WhatsApp con el mensaje y el link al PDF. La cotización queda como <strong>Enviada</strong> y se crea un recordatorio de seguimiento en 3 días.
          </p>
          <v-alert v-if="dialogEnviar.error" color="error" variant="tonal" density="compact" class="mt-2 text-caption">{{ dialogEnviar.error }}</v-alert>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="dialogEnviar.show = false">Cancelar</v-btn>
          <v-btn color="green" :loading="dialogEnviar.enviando" @click="enviarCotizacion">
            <v-icon start>mdi-whatsapp</v-icon>Enviar por WhatsApp
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
const dialogEnviar = ref({ show: false, enviando: false, item: null, telefono: '', mensaje: '', error: null })

function abrirEnviar(item) {
  const nombre = item.cliente?.razon_social
    || `${item.cliente?.first_name || ''} ${item.cliente?.last_name || ''}`.trim()
  const tel = item.cliente?.telefono || item.cliente?.phone || ''
  const pdfUrl = `${window.location.origin}/cotizaciones/${item.id}/pdf`
  const mensaje = `${nombre ? 'Hola ' + nombre + ',' : 'Hola,'}\n\nTe comparto la cotización #${item.id} de Vialum. Puedes verla y descargarla acá:\n${pdfUrl}\n\nCualquier duda quedo atento. ¡Saludos!`
  dialogEnviar.value = { show: true, enviando: false, item, telefono: tel, mensaje, error: null }
}

async function enviarCotizacion() {
  const d = dialogEnviar.value
  d.enviando = true
  d.error = null
  try {
    // Garantizar que el PDF público que se comparte tenga las imágenes Winperfil
    await asegurarGraficosCotizacion(api, d.item.id)
    const { data } = await api.post(`/api/cotizaciones/${d.item.id}/enviar`, {
      via: 'whatsapp',
      telefono: d.telefono || undefined,
      mensaje: d.mensaje || undefined,
    })
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
  