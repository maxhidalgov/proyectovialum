<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h2 class="text-h5 font-weight-bold">Panel de Operaciones</h2>
        <p class="text-caption text-grey mt-1">Cotizaciones aprobadas y seguimiento de producción</p>
      </div>
      <v-btn-toggle v-model="vista" mandatory color="primary" variant="outlined" density="compact">
        <v-btn value="tabla" prepend-icon="mdi-table">Tabla</v-btn>
        <v-btn value="kanban" prepend-icon="mdi-view-column">Kanban</v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-2" />

    <!-- Stat cards -->
    <v-row class="mb-4" dense>
      <v-col cols="6" sm="4" md="2" v-for="card in statCards" :key="card.label">
        <v-card variant="tonal" :color="card.color" class="pa-3 text-center">
          <v-icon :color="card.color" size="22" class="mb-1">{{ card.icon }}</v-icon>
          <div class="text-h6 font-weight-bold">{{ card.valor }}</div>
          <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtros -->
    <v-card class="mb-3 pa-3" variant="outlined">
      <v-row dense align="center">
        <v-col cols="12" sm="3">
          <v-text-field
            v-model="filtros.busqueda"
            label="Buscar cliente..."
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.estado"
            :items="['Aprobada','En Producción','Entregada','Facturada']"
            label="Estado"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.estadoProd"
            :items="['Sin asignar', ...estadosProduccion]"
            label="Estado producción"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.vendedor"
            :items="vendedoresUnicos"
            label="Vendedor"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.saldo"
            :items="[{ title: 'Con saldo pendiente', value: 'pendiente' }, { title: 'Sin saldo', value: 'pagado' }]"
            label="Saldo"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="1" class="d-flex align-center">
          <v-btn
            v-if="filtros.busqueda || filtros.estado || filtros.estadoProd || filtros.vendedor || filtros.saldo"
            size="small"
            variant="text"
            color="grey"
            @click="limpiarFiltros"
          >
            Limpiar
          </v-btn>
        </v-col>
      </v-row>

      <!-- Alertas resumen -->
      <v-row dense class="mt-2" v-if="alertas.vencidas > 0 || alertas.sinMover > 0">
        <v-col>
          <v-chip v-if="alertas.vencidas > 0" color="error" size="small" class="mr-2" prepend-icon="mdi-calendar-alert">
            {{ alertas.vencidas }} entrega{{ alertas.vencidas > 1 ? 's' : '' }} vencida{{ alertas.vencidas > 1 ? 's' : '' }}
          </v-chip>
          <v-chip v-if="alertas.sinMover > 0" color="warning" size="small" prepend-icon="mdi-clock-alert">
            {{ alertas.sinMover }} sin mover hace +{{ DIAS_ALERTA }} días
          </v-chip>
        </v-col>
      </v-row>
    </v-card>

    <!-- ── VISTA TABLA ─────────────────────────────────────────── -->
    <template v-if="vista === 'tabla'">
      <v-card>
        <v-data-table
          :headers="headers"
          :items="cotizacionesFiltradas"
          :items-per-page="25"
          density="compact"
          class="operaciones-table"
          :row-props="rowProps"
        >
          <!-- Cliente -->
          <template #item.cliente="{ item }">
            <div class="d-flex align-center gap-1">
              <span class="text-body-2 font-weight-medium">{{ item.cliente }}</span>
              <v-tooltip v-if="estaVencida(item)" text="Entrega vencida" location="top">
                <template #activator="{ props }">
                  <v-icon v-bind="props" color="error" size="14">mdi-calendar-alert</v-icon>
                </template>
              </v-tooltip>
              <v-tooltip v-if="sinMoverMucho(item)" text="Sin cambio de estado hace mucho" location="top">
                <template #activator="{ props }">
                  <v-icon v-bind="props" color="warning" size="14">mdi-clock-alert</v-icon>
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Estado cotización -->
          <template #item.estado="{ item }">
            <v-chip size="small" :color="colorEstadoCot(item.estado)" variant="tonal">
              {{ item.estado }}
            </v-chip>
          </template>

          <!-- Total -->
          <template #item.total="{ item }">
            <span class="text-body-2">{{ fmt(item.total) }}</span>
          </template>

          <!-- Abonado -->
          <template #item.total_abonado="{ item }">
            <div class="d-flex align-center gap-1">
              <span :class="item.saldo <= 0 ? 'text-success' : 'text-warning'" class="text-body-2">
                {{ fmt(item.total_abonado) }}
              </span>
              <v-btn icon size="x-small" variant="text" @click="abrirAbonos(item)">
                <v-icon size="14">mdi-plus-circle-outline</v-icon>
              </v-btn>
            </div>
          </template>

          <!-- Saldo -->
          <template #item.saldo="{ item }">
            <v-chip size="small" :color="item.saldo <= 0 ? 'success' : 'warning'" variant="tonal">
              {{ fmt(item.saldo) }}
            </v-chip>
          </template>

          <!-- Pedido proveedor -->
          <template #item.pedido_proveedor="{ item }">
            <v-checkbox-btn
              :model-value="item.pedido_proveedor"
              color="primary"
              @update:model-value="val => updateCampo(item, 'pedido_proveedor', val)"
            />
          </template>

          <!-- Estado producción -->
          <template #item.estado_produccion="{ item }">
            <div class="d-flex align-center gap-1">
              <v-select
                :model-value="item.estado_produccion"
                :items="estadosProduccion"
                density="compact"
                variant="plain"
                hide-details
                clearable
                style="min-width:180px"
                @update:model-value="val => updateCampo(item, 'estado_produccion', val)"
              >
                <template #selection="{ item: sel }">
                  <v-chip :color="colorEstadoProd(sel.value)" size="small" variant="flat">
                    {{ sel.value || '—' }}
                  </v-chip>
                </template>
              </v-select>
              <v-tooltip v-if="sinMoverMucho(item)" :text="`${diasEnEstado(item)} días en este estado`" location="top">
                <template #activator="{ props }">
                  <v-chip v-bind="props" color="warning" size="x-small" variant="tonal">
                    {{ diasEnEstado(item) }}d
                  </v-chip>
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Fecha entrega -->
          <template #item.fecha_entrega="{ item }">
            <input
              type="date"
              :value="item.fecha_entrega ?? ''"
              :class="['date-input', estaVencida(item) ? 'date-vencida' : '']"
              @change="e => updateCampo(item, 'fecha_entrega', e.target.value || null)"
            />
          </template>

          <!-- Ventanas / M² -->
          <template #item.cant_ventanas="{ item }">
            <v-chip size="small" color="blue" variant="tonal">{{ item.cant_ventanas }}</v-chip>
          </template>
          <template #item.m2="{ item }">
            <span class="text-body-2">{{ item.m2 }} m²</span>
          </template>

          <!-- Notas -->
          <template #item.notas_operaciones="{ item }">
            <v-text-field
              :model-value="item.notas_operaciones"
              density="compact"
              variant="plain"
              hide-details
              placeholder="—"
              style="min-width:140px"
              @blur="e => updateCampo(item, 'notas_operaciones', e.target.value || null)"
            />
          </template>
        </v-data-table>
      </v-card>
    </template>

    <!-- ── VISTA KANBAN ────────────────────────────────────────── -->
    <template v-else>
      <div class="kanban-board">
        <div v-for="col in columnasKanban" :key="col.estado ?? 'sin'" class="kanban-col">
          <div class="kanban-col-header" :style="{ borderColor: col.color }">
            <v-icon :color="col.color" size="16" class="mr-1">{{ col.icon }}</v-icon>
            <span class="text-caption font-weight-bold text-uppercase">{{ col.label ?? col.estado }}</span>
            <v-chip size="x-small" class="ml-auto" :color="col.color" variant="tonal">
              {{ tarjetasPorEstado(col.estado).length }}
            </v-chip>
          </div>

          <div class="kanban-col-body">
            <v-card
              v-for="item in tarjetasPorEstado(col.estado)"
              :key="item.id"
              class="kanban-card mb-2"
              variant="outlined"
              :class="{ 'kanban-card--vencida': estaVencida(item), 'kanban-card--alerta': sinMoverMucho(item) }"
            >
              <v-card-text class="pa-3">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-body-2 font-weight-bold">{{ item.cliente }}</span>
                  <div class="d-flex align-center gap-1">
                    <v-icon v-if="estaVencida(item)" color="error" size="14">mdi-calendar-alert</v-icon>
                    <v-icon v-if="sinMoverMucho(item)" color="warning" size="14">mdi-clock-alert</v-icon>
                    <span class="text-caption text-grey">#{{ item.id }}</span>
                  </div>
                </div>
                <div class="text-caption text-grey mb-1">{{ item.fecha }}</div>
                <div v-if="item.fecha_entrega" class="text-caption mb-2" :class="estaVencida(item) ? 'text-error' : 'text-grey'">
                  <v-icon size="12">mdi-calendar</v-icon> Entrega: {{ item.fecha_entrega }}
                </div>
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption">Total</span>
                  <span class="text-body-2 font-weight-medium">{{ fmt(item.total) }}</span>
                </div>
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption">Saldo</span>
                  <v-chip size="x-small" :color="item.saldo <= 0 ? 'success' : 'warning'" variant="tonal">
                    {{ fmt(item.saldo) }}
                  </v-chip>
                </div>
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-caption">{{ item.cant_ventanas }} ventanas</span>
                  <span class="text-caption">{{ item.m2 }} m²</span>
                </div>
                <div class="d-flex gap-1 flex-wrap mb-2">
                  <v-chip v-if="item.pedido_proveedor" size="x-small" color="blue" variant="tonal">
                    <v-icon start size="10">mdi-check</v-icon>Pedido OK
                  </v-chip>
                  <v-chip v-if="sinMoverMucho(item)" size="x-small" color="warning" variant="tonal">
                    {{ diasEnEstado(item) }}d sin mover
                  </v-chip>
                </div>
                <v-select
                  :model-value="item.estado_produccion"
                  :items="estadosProduccion"
                  density="compact"
                  variant="outlined"
                  hide-details
                  clearable
                  class="mt-1"
                  label="Mover a..."
                  style="font-size:12px"
                  @update:model-value="val => updateCampo(item, 'estado_produccion', val)"
                />
              </v-card-text>
            </v-card>

            <div v-if="!tarjetasPorEstado(col.estado).length" class="text-center text-caption text-grey pa-4">
              Sin cotizaciones
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── MODAL ABONOS ────────────────────────────────────────── -->
    <v-dialog v-model="dialogAbonos" max-width="480">
      <v-card v-if="itemAbonos">
        <v-card-title class="pa-4 pb-2">
          Abonos — {{ itemAbonos.cliente }} <span class="text-grey text-caption ml-1">#{{ itemAbonos.id }}</span>
        </v-card-title>
        <v-card-subtitle class="px-4 pb-3">
          Total: {{ fmt(itemAbonos.total) }} · Saldo:
          <strong :class="itemAbonos.saldo <= 0 ? 'text-success' : 'text-warning'">{{ fmt(itemAbonos.saldo) }}</strong>
        </v-card-subtitle>
        <v-divider />
        <v-card-text class="pa-4">
          <div v-if="itemAbonos.abonos.length" class="mb-4">
            <div
              v-for="ab in itemAbonos.abonos"
              :key="ab.id"
              class="d-flex align-center justify-space-between py-1"
              style="border-bottom:1px solid rgba(255,255,255,0.08)"
            >
              <div>
                <span class="text-body-2 font-weight-medium">{{ fmt(ab.monto) }}</span>
                <span class="text-caption text-grey ml-2">{{ ab.fecha }}</span>
                <span v-if="ab.nota" class="text-caption text-grey ml-2">— {{ ab.nota }}</span>
              </div>
              <v-btn icon size="x-small" color="error" variant="text" @click="eliminarAbono(ab)">
                <v-icon size="14">mdi-delete</v-icon>
              </v-btn>
            </div>
          </div>
          <div v-else class="text-caption text-grey mb-4">Sin abonos registrados</div>

          <v-divider class="mb-3" />
          <p class="text-caption font-weight-bold mb-2">Agregar abono</p>
          <v-row dense>
            <v-col cols="6">
              <v-text-field v-model.number="nuevoAbono.monto" label="Monto $" type="number" density="compact" variant="outlined" hide-details />
            </v-col>
            <v-col cols="6">
              <v-text-field v-model="nuevoAbono.fecha" label="Fecha" type="date" density="compact" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="nuevoAbono.nota" label="Nota (opcional)" density="compact" variant="outlined" hide-details />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-btn variant="text" @click="dialogAbonos = false">Cerrar</v-btn>
          <v-spacer />
          <v-btn color="primary" variant="flat" :loading="guardandoAbono" @click="guardarAbono">Agregar abono</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">
      {{ snack.msg }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'

const DIAS_ALERTA = 7   // días sin cambio de estado para mostrar alerta

const vista        = ref('tabla')
const cargando     = ref(false)
const cotizaciones = ref([])
const stats        = ref({})

// ── Filtros ──────────────────────────────────────────────────────
const filtros = ref({ busqueda: '', estado: null, estadoProd: null, vendedor: null, saldo: null })

const vendedoresUnicos = computed(() =>
  [...new Set(cotizaciones.value.map(c => c.vendedor).filter(Boolean))]
)

function limpiarFiltros() {
  filtros.value = { busqueda: '', estado: null, estadoProd: null, vendedor: null, saldo: null }
}

const cotizacionesFiltradas = computed(() => {
  return cotizaciones.value.filter(c => {
    if (filtros.value.busqueda && !c.cliente?.toLowerCase().includes(filtros.value.busqueda.toLowerCase())) return false
    if (filtros.value.estado && c.estado !== filtros.value.estado) return false
    if (filtros.value.estadoProd) {
      if (filtros.value.estadoProd === 'Sin asignar' && c.estado_produccion) return false
      if (filtros.value.estadoProd !== 'Sin asignar' && c.estado_produccion !== filtros.value.estadoProd) return false
    }
    if (filtros.value.vendedor && c.vendedor !== filtros.value.vendedor) return false
    if (filtros.value.saldo === 'pendiente' && c.saldo <= 0) return false
    if (filtros.value.saldo === 'pagado' && c.saldo > 0) return false
    return true
  })
})

// ── Alertas ──────────────────────────────────────────────────────
const hoy = new Date()
hoy.setHours(0, 0, 0, 0)

function estaVencida(item) {
  if (!item.fecha_entrega) return false
  return new Date(item.fecha_entrega) < hoy
}

function diasEnEstado(item) {
  const ref = item.fecha_entrega || item.fecha
  const d   = new Date(ref)
  return Math.floor((hoy - d) / 86400000)
}

function sinMoverMucho(item) {
  // Alerta si tiene estado_produccion asignado y lleva más de DIAS_ALERTA en la misma fecha
  if (!item.estado_produccion) return false
  return diasEnEstado(item) > DIAS_ALERTA
}

const alertas = computed(() => ({
  vencidas: cotizacionesFiltradas.value.filter(estaVencida).length,
  sinMover: cotizacionesFiltradas.value.filter(sinMoverMucho).length,
}))

function rowProps({ item }) {
  if (estaVencida(item)) return { class: 'row-vencida' }
  if (sinMoverMucho(item)) return { class: 'row-alerta' }
  return {}
}

// ── Stat cards ───────────────────────────────────────────────────
const statCards = computed(() => [
  { label: 'Cotizaciones',   valor: stats.value.total_cotizaciones ?? 0,      color: 'primary', icon: 'mdi-file-multiple'  },
  { label: 'Ventanas',       valor: stats.value.total_ventanas ?? 0,           color: 'blue',    icon: 'mdi-window-open'    },
  { label: 'M² fabricados',  valor: `${stats.value.total_m2 ?? 0} m²`,        color: 'teal',    icon: 'mdi-ruler-square'   },
  { label: 'Total a cobrar', valor: fmt(stats.value.total_facturado),          color: 'green',   icon: 'mdi-currency-usd'   },
  { label: 'Abonado',        valor: fmt(stats.value.total_abonado),            color: 'success', icon: 'mdi-cash-check'     },
  { label: 'Saldo pendiente',valor: fmt(stats.value.total_saldo),              color: 'warning', icon: 'mdi-cash-clock'     },
])

// ── Tabla ────────────────────────────────────────────────────────
const headers = [
  { title: '#',            value: 'id',                width: 60  },
  { title: 'Cliente',      value: 'cliente',           width: 180 },
  { title: 'Vendedor',     value: 'vendedor',          width: 120 },
  { title: 'Estado',       value: 'estado',            width: 120 },
  { title: 'Total',        value: 'total',             width: 120 },
  { title: 'Abonado',      value: 'total_abonado',     width: 130 },
  { title: 'Saldo',        value: 'saldo',             width: 110 },
  { title: 'Pedido Prov.', value: 'pedido_proveedor',  width: 100 },
  { title: 'Estado Prod.', value: 'estado_produccion', width: 220 },
  { title: 'Entrega',      value: 'fecha_entrega',     width: 140 },
  { title: 'Ventanas',     value: 'cant_ventanas',     width: 90  },
  { title: 'M²',           value: 'm2',                width: 80  },
  { title: 'Notas',        value: 'notas_operaciones', width: 180 },
]

const estadosProduccion = [
  'En Espera de Medidas',
  'Lista para Corte',
  'En Fabricación',
  'Fabricadas OK',
  'Instalada',
]

const columnasKanban = [
  { estado: null,                   label: 'Sin asignar',       color: 'grey',   icon: 'mdi-help-circle-outline' },
  { estado: 'En Espera de Medidas', color: 'grey',              icon: 'mdi-clock-outline'  },
  { estado: 'Lista para Corte',     color: 'blue',              icon: 'mdi-ruler-square'   },
  { estado: 'En Fabricación',       color: 'orange',            icon: 'mdi-wrench'         },
  { estado: 'Fabricadas OK',        color: 'green',             icon: 'mdi-check-circle'   },
  { estado: 'Instalada',            color: 'purple',            icon: 'mdi-home-check'     },
]

function colorEstadoProd(estado) {
  const map = {
    'En Espera de Medidas': 'grey',
    'Lista para Corte':     'blue',
    'En Fabricación':       'orange',
    'Fabricadas OK':        'green',
    'Instalada':            'purple',
  }
  return map[estado] ?? 'grey'
}

function colorEstadoCot(estado) {
  const map = {
    'Aprobada':      'green',
    'En Producción': 'blue',
    'Entregada':     'purple',
    'Facturada':     'teal',
  }
  return map[estado] ?? 'grey'
}

function tarjetasPorEstado(estado) {
  return cotizacionesFiltradas.value.filter(c =>
    estado === null ? !c.estado_produccion : c.estado_produccion === estado
  )
}

// ── Cargar datos ─────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/operaciones')
    cotizaciones.value = data.cotizaciones
    stats.value        = data.stats
  } catch {
    mostrarSnack('Error al cargar operaciones', 'error')
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Edición inline ───────────────────────────────────────────────
async function updateCampo(item, campo, valor) {
  item[campo] = valor
  try {
    await api.patch(`/api/operaciones/${item.id}`, { [campo]: valor })
  } catch {
    mostrarSnack('Error al guardar', 'error')
    cargar()
  }
}

// ── Abonos ───────────────────────────────────────────────────────
const dialogAbonos   = ref(false)
const itemAbonos     = ref(null)
const guardandoAbono = ref(false)
const nuevoAbono     = ref({ monto: null, fecha: new Date().toISOString().split('T')[0], nota: '' })

function abrirAbonos(item) {
  itemAbonos.value = item
  nuevoAbono.value = { monto: null, fecha: new Date().toISOString().split('T')[0], nota: '' }
  dialogAbonos.value = true
}

async function guardarAbono() {
  if (!nuevoAbono.value.monto || !nuevoAbono.value.fecha) {
    mostrarSnack('Ingresa monto y fecha', 'error'); return
  }
  guardandoAbono.value = true
  try {
    const { data } = await api.post(`/api/operaciones/${itemAbonos.value.id}/abonos`, nuevoAbono.value)
    itemAbonos.value.abonos.push(data)
    itemAbonos.value.total_abonado += data.monto
    itemAbonos.value.saldo         -= data.monto
    nuevoAbono.value = { monto: null, fecha: new Date().toISOString().split('T')[0], nota: '' }
    mostrarSnack('Abono registrado', 'success')
  } catch {
    mostrarSnack('Error al guardar abono', 'error')
  } finally {
    guardandoAbono.value = false
  }
}

async function eliminarAbono(abono) {
  if (!confirm(`¿Eliminar abono de ${fmt(abono.monto)}?`)) return
  try {
    await api.delete(`/api/operaciones/abonos/${abono.id}`)
    const idx = itemAbonos.value.abonos.findIndex(a => a.id === abono.id)
    if (idx !== -1) {
      itemAbonos.value.total_abonado -= abono.monto
      itemAbonos.value.saldo         += abono.monto
      itemAbonos.value.abonos.splice(idx, 1)
    }
    mostrarSnack('Abono eliminado', 'success')
  } catch {
    mostrarSnack('Error al eliminar', 'error')
  }
}

// ── Helpers ──────────────────────────────────────────────────────
const snack = ref({ show: false, color: 'success', msg: '' })
function mostrarSnack(msg, color = 'success') { snack.value = { show: true, color, msg } }

function fmt(val) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
  }).format(val || 0)
}
</script>

<style scoped>
.operaciones-table :deep(td) {
  padding-top: 4px !important;
  padding-bottom: 4px !important;
}

.operaciones-table :deep(.row-vencida td) {
  background: rgba(244, 67, 54, 0.07) !important;
}

.operaciones-table :deep(.row-alerta td) {
  background: rgba(255, 152, 0, 0.07) !important;
}

.date-input {
  background: transparent;
  border: none;
  color: inherit;
  font-size: 0.8rem;
  cursor: pointer;
  outline: none;
}

.date-vencida {
  color: rgb(244, 67, 54) !important;
  font-weight: 600;
}

/* Kanban */
.kanban-board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 16px;
  align-items: flex-start;
}

.kanban-col {
  min-width: 240px;
  max-width: 260px;
  flex-shrink: 0;
  background: rgba(255,255,255,0.04);
  border-radius: 8px;
  overflow: hidden;
}

.kanban-col-header {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-left: 3px solid;
  background: rgba(255,255,255,0.06);
  font-size: 11px;
}

.kanban-col-body {
  padding: 8px;
  max-height: calc(100vh - 300px);
  overflow-y: auto;
}

.kanban-card {
  cursor: default;
  transition: box-shadow 0.15s;
}

.kanban-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
}

.kanban-card--vencida {
  border-color: rgba(244, 67, 54, 0.5) !important;
}

.kanban-card--alerta {
  border-color: rgba(255, 152, 0, 0.5) !important;
}
</style>
