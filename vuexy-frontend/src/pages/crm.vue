<template>
  <v-container fluid class="pa-4">
    <!-- Header con resumen -->
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h5 font-weight-bold">CRM — Pipeline de Ventas</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" @click="router.push({ name: 'cotizador' })">
          <v-icon start>mdi-plus</v-icon>
          Nueva Cotización
        </v-btn>
      </v-col>
    </v-row>

    <!-- Tarjetas de resumen -->
    <v-row class="mb-6">
      <v-col v-for="col in columnas" :key="col.estado" cols="6" sm="4" md="2">
        <v-card :color="col.color" variant="tonal" class="text-center pa-3">
          <div class="text-h6 font-weight-bold">{{ contarPorEstado(col.estado) }}</div>
          <div class="text-caption">{{ col.estado }}</div>
          <div class="text-caption font-weight-medium">
            {{ formatMonto(totalPorEstado(col.estado)) }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Kanban -->
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <div v-else class="kanban-board">
      <div
        v-for="col in columnas"
        :key="col.estado"
        class="kanban-col"
      >
        <!-- Cabecera columna -->
        <div class="kanban-col-header" :style="{ borderTop: `3px solid ${col.hex}` }">
          <v-chip :color="col.color" size="small" class="mr-2">{{ contarPorEstado(col.estado) }}</v-chip>
          <span class="font-weight-bold text-body-2">{{ col.estado }}</span>
        </div>

        <!-- Tarjetas -->
        <div class="kanban-cards">
          <v-card
            v-for="item in porEstado(col.estado)"
            :key="item.id"
            class="kanban-card mb-3"
            elevation="1"
            @click="verCotizacion(item)"
            style="cursor: pointer;"
          >
            <v-card-text class="pa-3">
              <!-- ID + fecha -->
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-medium-emphasis">#{{ item.id }}</span>
                <span class="text-caption text-medium-emphasis">{{ formatFecha(item.fecha) }}</span>
              </div>

              <!-- Cliente -->
              <div class="font-weight-medium text-body-2 mb-1" style="line-height: 1.3;">
                {{ item.cliente?.razon_social || '—' }}
              </div>

              <!-- RUT -->
              <div v-if="item.cliente?.identification" class="text-caption text-medium-emphasis mb-2">
                {{ item.cliente.identification }}
              </div>

              <!-- Monto -->
              <div class="text-body-2 font-weight-bold" :style="{ color: col.hex }">
                {{ formatMonto(item.total) }}
              </div>

              <!-- Vendedor + días -->
              <div class="d-flex justify-space-between align-center mt-2">
                <v-avatar size="22" color="grey-lighten-2" class="text-caption">
                  {{ iniciales(item.vendedor?.name) }}
                </v-avatar>
                <span class="text-caption text-medium-emphasis">
                  {{ diasEnEstado(item.fecha) }}d
                </span>
              </div>

              <!-- Acciones rápidas -->
              <div class="d-flex ga-1 mt-2" @click.stop>
                <v-btn
                  v-if="transicionesPosibles(col.estado).length"
                  size="x-small"
                  :color="col.color"
                  variant="tonal"
                  @click.stop="abrirCambioEstado(item, col)"
                >
                  <v-icon size="14">mdi-arrow-right-circle</v-icon>
                  Avanzar
                </v-btn>
                <v-btn size="x-small" variant="text" icon @click.stop="verCotizacion(item)">
                  <v-icon size="16">mdi-eye</v-icon>
                </v-btn>
                <v-btn size="x-small" variant="text" icon @click.stop="descargarOT(item.id)" title="Orden de Trabajo">
                  <v-icon size="16">mdi-file-document-outline</v-icon>
                </v-btn>
              </div>
            </v-card-text>
          </v-card>

          <div v-if="!porEstado(col.estado).length" class="text-center text-medium-emphasis text-caption pa-4">
            Sin cotizaciones
          </div>
        </div>
      </div>
    </div>

    <!-- Dialog cambio de estado -->
    <v-dialog v-model="dialogEstado" max-width="360">
      <v-card>
        <v-card-title class="text-h6 pa-4">Cambiar Estado</v-card-title>
        <v-card-text class="pa-4">
          <p class="mb-3">
            Cotización <strong>#{{ itemSeleccionado?.id }}</strong> —
            <strong>{{ itemSeleccionado?.cliente?.razon_social }}</strong>
          </p>
          <p class="text-body-2 mb-4">
            Estado actual: <v-chip size="small" :color="columnaSeleccionada?.color">{{ columnaSeleccionada?.estado }}</v-chip>
          </p>
          <v-btn
            v-for="destino in transicionesPosibles(columnaSeleccionada?.estado)"
            :key="destino"
            block
            class="mb-2"
            :color="colorDeEstado(destino)"
            variant="tonal"
            :loading="loadingEstado"
            @click="confirmarCambio(destino)"
          >
            → {{ destino }}
          </v-btn>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialogEstado = false">Cancelar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/axiosInstance'

const router = useRouter()
const cotizaciones = ref([])
const loading = ref(true)
const dialogEstado = ref(false)
const itemSeleccionado = ref(null)
const columnaSeleccionada = ref(null)
const loadingEstado = ref(false)

const columnas = [
  { estado: 'Evaluación',    color: 'grey',    hex: '#757575' },
  { estado: 'Aprobada',      color: 'green',   hex: '#43A047' },
  { estado: 'En Producción', color: 'blue',    hex: '#1E88E5' },
  { estado: 'Entregada',     color: 'purple',  hex: '#8E24AA' },
  { estado: 'Facturada',     color: 'teal',    hex: '#00897B' },
  { estado: 'Rechazada',     color: 'red',     hex: '#E53935' },
]

const transicionesMap = {
  'Evaluación':    ['Aprobada', 'Rechazada'],
  'Aprobada':      ['En Producción', 'Rechazada'],
  'En Producción': ['Entregada'],
  'Entregada':     ['Facturada'],
}

const transicionesPosibles = (estado) => transicionesMap[estado] ?? []

const colorDeEstado = (nombre) => {
  return columnas.find(c => c.estado === nombre)?.color ?? 'grey'
}

const porEstado = (estado) =>
  cotizaciones.value.filter(c => c.estado?.nombre === estado)

const contarPorEstado = (estado) => porEstado(estado).length

const totalPorEstado = (estado) =>
  porEstado(estado).reduce((sum, c) => sum + (Number(c.total) || 0), 0)

const formatMonto = (n) =>
  '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(n) || 0)

const formatFecha = (fecha) => {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit' })
}

const diasEnEstado = (fecha) => {
  if (!fecha) return 0
  const diff = Date.now() - new Date(fecha).getTime()
  return Math.floor(diff / 86400000)
}

const iniciales = (nombre) => {
  if (!nombre) return '?'
  return nombre.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
}

const verCotizacion = (item) => {
  router.push(`/cotizacion-ver?id=${item.id}`)
}

const abrirCambioEstado = (item, col) => {
  itemSeleccionado.value = item
  columnaSeleccionada.value = col
  dialogEstado.value = true
}

const confirmarCambio = async (nuevoEstado) => {
  loadingEstado.value = true
  try {
    const { data } = await api.patch(`/api/cotizaciones/${itemSeleccionado.value.id}/estado`, { estado: nuevoEstado })
    itemSeleccionado.value.estado = data.estado
    dialogEstado.value = false
  } catch (err) {
    alert(err.response?.data?.message || 'Error al cambiar el estado.')
  } finally {
    loadingEstado.value = false
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
  } catch {
    alert('Error al descargar la Orden de Trabajo.')
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/api/cotizaciones')
    cotizaciones.value = Array.isArray(res.data) ? res.data : []
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.kanban-board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  align-items: flex-start;
  padding-bottom: 16px;
}

.kanban-col {
  min-width: 230px;
  flex: 1;
  background: rgba(0,0,0,0.03);
  border-radius: 8px;
  overflow: hidden;
}

.kanban-col-header {
  padding: 10px 12px;
  display: flex;
  align-items: center;
  background: rgba(0,0,0,0.04);
}

.kanban-cards {
  padding: 10px 8px;
  min-height: 80px;
  max-height: calc(100vh - 280px);
  overflow-y: auto;
}

.kanban-card {
  transition: box-shadow 0.15s;
}
.kanban-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}
</style>
