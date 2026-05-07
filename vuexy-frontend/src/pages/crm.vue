<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h2 class="text-h5 font-weight-bold">CRM — Pipeline de Ventas</h2>
        <p class="text-caption text-grey mt-1">Seguimiento de cotizaciones por estado</p>
      </div>
      <v-btn color="primary" @click="router.push({ name: 'cotizador' })">
        <v-icon start>mdi-plus</v-icon>Nueva Cotización
      </v-btn>
    </div>

    <!-- Stat cards por estado -->
    <v-row class="mb-4" dense>
      <v-col v-for="col in columnas" :key="col.estado" cols="6" sm="4" md="2">
        <v-card
          :color="col.color"
          variant="tonal"
          class="pa-3 text-center"
          style="cursor:pointer"
          :class="{ 'card-activa': filtroEstado === col.estado }"
          @click="toggleFiltroEstado(col.estado)"
        >
          <div class="text-h6 font-weight-bold">{{ contarPorEstado(col.estado) }}</div>
          <div class="text-caption font-weight-medium">{{ col.estado }}</div>
          <div class="text-caption text-medium-emphasis">{{ formatMonto(totalPorEstado(col.estado)) }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Alertas globales -->
    <div v-if="alertasGlobales.lentas > 0 || alertasGlobales.montoEvaluacion > 0" class="mb-3 d-flex gap-2 flex-wrap">
      <v-chip v-if="alertasGlobales.lentas > 0" color="warning" size="small" prepend-icon="mdi-clock-alert">
        {{ alertasGlobales.lentas }} cotización{{ alertasGlobales.lentas > 1 ? 'es' : '' }} sin avanzar hace +{{ DIAS_ALERTA }}d
      </v-chip>
      <v-chip v-if="alertasGlobales.montoEvaluacion > 0" color="blue" size="small" prepend-icon="mdi-currency-usd">
        {{ formatMonto(alertasGlobales.montoEvaluacion) }} en evaluación
      </v-chip>
    </div>

    <!-- Filtros rápidos -->
    <v-card class="mb-4 pa-3" variant="outlined">
      <v-row dense align="center">
        <v-col cols="12" sm="4">
          <v-text-field
            v-model="filtroBusqueda"
            label="Buscar cliente..."
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="3">
          <v-select
            v-model="filtroVendedor"
            :items="vendedoresUnicos"
            label="Vendedor"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="3">
          <v-select
            v-model="filtroAlerta"
            :items="[{ title: `Sin avanzar +${DIAS_ALERTA}d`, value: 'lentas' }, { title: 'Hoy', value: 'hoy' }]"
            label="Alertas"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2" class="d-flex align-center">
          <v-btn
            v-if="filtroBusqueda || filtroVendedor || filtroAlerta || filtroEstado"
            size="small" variant="text" color="grey"
            @click="limpiarFiltros"
          >
            Limpiar
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <!-- Kanban -->
    <div v-else class="kanban-board">
      <div v-for="col in columnasFiltradas" :key="col.estado" class="kanban-col">

        <!-- Cabecera -->
        <div class="kanban-col-header" :style="{ borderTop: `3px solid ${col.hex}` }">
          <v-chip :color="col.color" size="small" class="mr-2">{{ porEstadoFiltrado(col.estado).length }}</v-chip>
          <span class="font-weight-bold text-body-2">{{ col.estado }}</span>
          <span class="ml-auto text-caption text-medium-emphasis">
            {{ formatMonto(porEstadoFiltrado(col.estado).reduce((s,c) => s + (Number(c.total)||0), 0)) }}
          </span>
        </div>

        <!-- Tarjetas -->
        <div class="kanban-cards">
          <v-card
            v-for="item in porEstadoFiltrado(col.estado)"
            :key="item.id"
            class="kanban-card mb-3"
            elevation="1"
            style="cursor:pointer"
            :class="{
              'card-lenta':  esLenta(item),
              'card-hoy':    esDehoy(item),
            }"
            @click="verCotizacion(item)"
          >
            <v-card-text class="pa-3">
              <!-- ID + fecha -->
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-medium-emphasis">#{{ item.id }}</span>
                <span class="text-caption text-medium-emphasis">{{ formatFecha(item.fecha) }}</span>
              </div>

              <!-- Cliente -->
              <div class="font-weight-medium text-body-2 mb-1" style="line-height:1.3">
                {{ nombreCliente(item.cliente) }}
              </div>
              <div v-if="item.cliente?.identification" class="text-caption text-medium-emphasis mb-2">
                {{ item.cliente.identification }}
              </div>

              <!-- Monto -->
              <div class="text-body-2 font-weight-bold mb-2" :style="{ color: col.hex }">
                {{ formatMonto(item.total) }}
              </div>

              <!-- Días + alertas -->
              <div class="d-flex justify-space-between align-center mb-2">
                <v-avatar size="22" color="grey-lighten-2" class="text-caption">
                  {{ iniciales(item.vendedor?.name) }}
                </v-avatar>
                <div class="d-flex align-center gap-1">
                  <v-tooltip v-if="esLenta(item)" :text="`${diasEnEstado(item.fecha)}d sin avanzar`" location="top">
                    <template #activator="{ props }">
                      <v-chip v-bind="props" color="warning" size="x-small" variant="flat" prepend-icon="mdi-clock-alert">
                        {{ diasEnEstado(item.fecha) }}d
                      </v-chip>
                    </template>
                  </v-tooltip>
                  <span v-else class="text-caption text-medium-emphasis">{{ diasEnEstado(item.fecha) }}d</span>
                  <v-tooltip v-if="esDehoy(item)" text="Creada hoy" location="top">
                    <template #activator="{ props }">
                      <v-icon v-bind="props" color="green" size="14">mdi-new-box</v-icon>
                    </template>
                  </v-tooltip>
                </div>
              </div>

              <!-- Acciones -->
              <div class="d-flex ga-1 mt-1" @click.stop>
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

          <div v-if="!porEstadoFiltrado(col.estado).length" class="text-center text-medium-emphasis text-caption pa-4">
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
            <strong>{{ nombreCliente(itemSeleccionado?.cliente) }}</strong>
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/axiosInstance'

const DIAS_ALERTA = 7

const router           = useRouter()
const cotizaciones     = ref([])
const loading          = ref(true)
const dialogEstado     = ref(false)
const itemSeleccionado = ref(null)
const columnaSeleccionada = ref(null)
const loadingEstado    = ref(false)

// Filtros
const filtroBusqueda = ref('')
const filtroVendedor = ref(null)
const filtroAlerta   = ref(null)
const filtroEstado   = ref(null)  // click en stat card

const columnas = [
  { estado: 'Evaluación',    color: 'grey',   hex: '#757575' },
  { estado: 'Aprobada',      color: 'green',  hex: '#43A047' },
  { estado: 'En Producción', color: 'blue',   hex: '#1E88E5' },
  { estado: 'Entregada',     color: 'purple', hex: '#8E24AA' },
  { estado: 'Facturada',     color: 'teal',   hex: '#00897B' },
  { estado: 'Rechazada',     color: 'red',    hex: '#E53935' },
]

// Si hay filtro de estado, mostrar solo esa columna; sino todas
const columnasFiltradas = computed(() =>
  filtroEstado.value ? columnas.filter(c => c.estado === filtroEstado.value) : columnas
)

const transicionesMap = {
  'Evaluación':    ['Aprobada', 'Rechazada'],
  'Aprobada':      ['En Producción', 'Rechazada'],
  'En Producción': ['Entregada'],
  'Entregada':     ['Facturada'],
}

const transicionesPosibles = (estado) => transicionesMap[estado] ?? []
const colorDeEstado = (nombre) => columnas.find(c => c.estado === nombre)?.color ?? 'grey'

// ── Alertas ──────────────────────────────────────────────────────
const hoy = new Date(); hoy.setHours(0,0,0,0)

function diasEnEstado(fecha) {
  if (!fecha) return 0
  return Math.floor((Date.now() - new Date(fecha).getTime()) / 86400000)
}

function esLenta(item) {
  const estado = item.estado?.nombre
  // Solo alertar en estados activos (no rechazada/facturada)
  if (['Rechazada','Facturada'].includes(estado)) return false
  return diasEnEstado(item.fecha) > DIAS_ALERTA
}

function esDehoy(item) {
  if (!item.fecha) return false
  return new Date(item.fecha).toDateString() === hoy.toDateString()
}

const alertasGlobales = computed(() => ({
  lentas:           cotizaciones.value.filter(esLenta).length,
  montoEvaluacion:  cotizaciones.value
    .filter(c => c.estado?.nombre === 'Evaluación')
    .reduce((s, c) => s + (Number(c.total) || 0), 0),
}))

// ── Filtrado ─────────────────────────────────────────────────────
const vendedoresUnicos = computed(() =>
  [...new Set(cotizaciones.value.map(c => c.vendedor?.name).filter(Boolean))]
)

function toggleFiltroEstado(estado) {
  filtroEstado.value = filtroEstado.value === estado ? null : estado
}

function limpiarFiltros() {
  filtroBusqueda.value = ''
  filtroVendedor.value = null
  filtroAlerta.value   = null
  filtroEstado.value   = null
}

const cotizacionesFiltradas = computed(() => {
  return cotizaciones.value.filter(c => {
    const nombre = nombreCliente(c.cliente).toLowerCase()
    if (filtroBusqueda.value && !nombre.includes(filtroBusqueda.value.toLowerCase())) return false
    if (filtroVendedor.value && c.vendedor?.name !== filtroVendedor.value) return false
    if (filtroAlerta.value === 'lentas' && !esLenta(c)) return false
    if (filtroAlerta.value === 'hoy' && !esDehoy(c)) return false
    return true
  })
})

const porEstado = (estado) => cotizaciones.value.filter(c => c.estado?.nombre === estado)
const porEstadoFiltrado = (estado) => cotizacionesFiltradas.value.filter(c => c.estado?.nombre === estado)
const contarPorEstado = (estado) => porEstado(estado).length
const totalPorEstado = (estado) => porEstado(estado).reduce((s, c) => s + (Number(c.total) || 0), 0)

// ── Helpers ──────────────────────────────────────────────────────
const formatMonto = (n) =>
  '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(n) || 0)

const formatFecha = (fecha) => {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit' })
}

const nombreCliente = (cliente) => {
  if (!cliente) return '—'
  return cliente.razon_social
    || `${cliente.first_name || ''} ${cliente.last_name || ''}`.trim()
    || '—'
}

const iniciales = (nombre) => {
  if (!nombre) return '?'
  return nombre.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
}

const verCotizacion = (item) => router.push(`/cotizacion-ver?id=${item.id}`)

const abrirCambioEstado = (item, col) => {
  itemSeleccionado.value    = item
  columnaSeleccionada.value = col
  dialogEstado.value        = true
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
    const url  = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
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
  gap: 4px;
}

.kanban-cards {
  padding: 10px 8px;
  min-height: 80px;
  max-height: calc(100vh - 320px);
  overflow-y: auto;
}

.kanban-card { transition: box-shadow 0.15s; }
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; }

.card-lenta  { border-left: 3px solid rgb(255, 152, 0) !important; }
.card-hoy    { border-left: 3px solid rgb(76, 175, 80) !important; }

.card-activa {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
</style>
