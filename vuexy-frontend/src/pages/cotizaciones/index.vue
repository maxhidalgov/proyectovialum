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
  </template>
  
  <script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import api from '@/axiosInstance'
  import { svgDataUriToPng } from '@/composables/useSvgToPng'

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
    // 1. Intentar GET simple primero (usa PNGs guardados en BD si la cotización fue vista antes)
    const { data: cot } = await api.get(`/api/cotizaciones/${cotizacionId}`)
    const detalles = cot.detalles || []

    // 2. Ver si faltan PNGs (cotización nunca fue abierta en cotizacion-ver)
    const sinPng = detalles.filter(d => d.tipo_item === 'winperfil' && d.winperfil_grafico && !d.winperfil_grafico_png)

    if (sinPng.length > 0) {
      // Convertir los que faltan y guardar en BD
      const graficos = {}
      for (const det of sinPng) {
        try {
          graficos[det.id] = await svgDataUriToPng(det.winperfil_grafico.trim())
        } catch (e) {
          console.warn('canvg error detalle', det.id, e)
        }
      }
      if (Object.keys(graficos).length) {
        // Guardar en BD para futuras veces
        api.post(`/api/cotizaciones/${cotizacionId}/guardar-graficos-png`, { graficos }).catch(() => {})
      }
    }

    // 3. Descargar PDF (el server usa los PNGs de la BD)
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
  