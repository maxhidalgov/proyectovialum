<template>
    <v-card>
      <v-card-title>
        Cotizaciones
        <v-spacer />
        <v-btn
          color="secondary"
          variant="tonal"
          class="mr-2"
          prepend-icon="mdi-window-open"
          @click="router.push('/cotizaciones/importar-pvc')"
        >
          Importar WINPERFIL
        </v-btn>
        <v-btn color="primary" @click="router.push({ name: 'cotizador' })">Nueva</v-btn>
      </v-card-title>
  
      <v-data-table
        :headers="headers"
        :items="cotizaciones"
        :items-per-page="10"
        class="elevation-1"
      >
        <template #item.cliente="{ item }">
          {{ item.cliente?.razon_social || `${item.cliente?.first_name || ''} ${item.cliente?.last_name || ''}`.trim() || '—' }}
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
          <v-btn icon @click="descargarPDF(item.id)" title="Descargar Cotización PDF">
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
  import { ref, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import api from '@/axiosInstance'
  
  const router = useRouter()
  const cotizaciones = ref([])
  
  const headers = [
    { title: 'ID', value: 'id' },
    { title: 'Cliente', value: 'cliente' },
    { title: 'Vendedor', value: 'vendedor' },
    { title: 'Fecha', value: 'fecha' },
    { title: 'Estado', value: 'estado' },
    { title: 'Total', value: 'total' },
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

const descargarPDF = async (cotizacionId) => {
  try {
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
  