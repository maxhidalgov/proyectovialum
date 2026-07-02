<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-3">
        <v-icon icon="mdi-factory" size="32" color="primary" />
        <div>
          <h1 class="text-h5 font-weight-bold">Taller de Fabricación</h1>
          <p class="text-caption text-grey mt-1">Seguimiento de etapas por cotización</p>
        </div>
      </div>
      <div class="d-flex gap-2">
        <v-btn variant="tonal" prepend-icon="mdi-refresh" size="small" @click="cargar" :loading="cargando">
          Actualizar
        </v-btn>
      </div>
    </div>

    <!-- Stat cards -->
    <v-row class="mb-4" dense>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="orange" class="pa-3 text-center">
          <v-icon color="orange" size="22" class="mb-1">mdi-wrench</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.en_fabricacion ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">En Fabricación</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="blue" class="pa-3 text-center">
          <v-icon color="blue" size="22" class="mb-1">mdi-scissors-cutting</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.lista_corte ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Lista para Corte</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="green" class="pa-3 text-center">
          <v-icon color="green" size="22" class="mb-1">mdi-check-circle</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.fabricadas_ok ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Fabricadas OK</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="error" class="pa-3 text-center">
          <v-icon color="error" size="22" class="mb-1">mdi-calendar-alert</v-icon>
          <div class="text-h6 font-weight-bold">{{ vencidas }}</div>
          <div class="text-caption text-medium-emphasis">Entregas vencidas</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtro -->
    <div class="d-flex gap-3 mb-4 align-center flex-wrap">
      <v-text-field
        v-model="busqueda"
        prepend-inner-icon="mdi-magnify"
        label="Buscar cliente..."
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:280px"
      />
      <v-btn-toggle v-model="filtroEstado" color="primary" variant="outlined" density="compact">
        <v-btn value="">Todos</v-btn>
        <v-btn value="En Fabricación">Fabricando</v-btn>
        <v-btn value="Lista para Corte">Lista</v-btn>
        <v-btn value="Fabricadas OK">OK</v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-4" />

    <!-- Sin resultados -->
    <v-card v-if="!cargando && cotizacionesFiltradas.length === 0" class="pa-8 text-center" variant="outlined">
      <v-icon size="48" color="grey" class="mb-3">mdi-factory</v-icon>
      <div class="text-body-1 text-grey">No hay cotizaciones en producción</div>
      <div class="text-caption text-grey mt-1">Las cotizaciones aparecen aquí cuando tienen estado "Lista para Corte" o "En Fabricación"</div>
    </v-card>

    <!-- Tarjetas de cotización -->
    <div v-for="cotizacion in cotizacionesFiltradas" :key="cotizacion.id" class="mb-4">
      <v-card
        :class="['cotizacion-card', `urgencia-${cotizacion.urgencia}`]"
        variant="outlined"
      >
        <!-- Header de la tarjeta -->
        <v-card-text class="pb-2">
          <div class="d-flex align-start justify-space-between gap-2 flex-wrap">
            <div class="d-flex align-center gap-3">
              <div>
                <div class="d-flex align-center gap-2">
                  <span class="text-h6 font-weight-bold">{{ cotizacion.cliente }}</span>
                  <span class="text-caption text-grey">#{{ cotizacion.id }}</span>
                  <v-chip
                    size="x-small"
                    :color="colorEstado(cotizacion.estado_produccion)"
                    variant="flat"
                  >
                    {{ cotizacion.estado_produccion }}
                  </v-chip>
                </div>
                <div class="d-flex align-center gap-3 mt-1 flex-wrap">
                  <span class="text-caption text-grey">
                    <v-icon size="12">mdi-ruler-square</v-icon>
                    {{ cotizacion.m2 }} m² · {{ cotizacion.cant_ventanas }} ventanas
                  </span>
                  <span
                    v-if="cotizacion.fecha_entrega"
                    class="text-caption"
                    :class="claseEntrega(cotizacion)"
                  >
                    <v-icon size="12">mdi-calendar</v-icon>
                    Entrega: {{ fmtFecha(cotizacion.fecha_entrega) }}
                    <span v-if="cotizacion.dias_para_entrega !== null">
                      ({{ cotizacion.dias_para_entrega >= 0 ? `en ${cotizacion.dias_para_entrega}d` : `${Math.abs(cotizacion.dias_para_entrega)}d vencida` }})
                    </span>
                  </span>
                  <v-chip v-if="cotizacion.fabricar_termopanel" size="x-small" color="teal" variant="tonal">Termopanel</v-chip>
                  <v-chip v-if="cotizacion.cortar_vidrio_cnc" size="x-small" color="purple" variant="tonal">Vidrio CNC</v-chip>
                </div>
              </div>
            </div>

            <!-- Progreso + acciones -->
            <div class="d-flex align-center gap-2">
              <div class="text-center" style="min-width:80px">
                <v-progress-circular
                  :model-value="cotizacion.progreso"
                  :color="cotizacion.progreso === 100 ? 'success' : 'primary'"
                  size="42"
                  width="4"
                >
                  <span class="text-caption font-weight-bold">{{ cotizacion.progreso }}%</span>
                </v-progress-circular>
              </div>
              <div class="d-flex flex-column gap-1">
                <v-btn
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-scissors-cutting"
                  :to="{ name: 'produccion-id', params: { id: cotizacion.id } }"
                >
                  Hoja Cortes
                </v-btn>
                <v-btn
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  prepend-icon="mdi-clipboard-list"
                  :to="{ name: 'produccion-materiales-id', params: { id: cotizacion.id } }"
                >
                  Materiales
                </v-btn>
              </div>
            </div>
          </div>

          <!-- Barra de progreso -->
          <v-progress-linear
            :model-value="cotizacion.progreso"
            :color="cotizacion.progreso === 100 ? 'success' : 'primary'"
            rounded
            height="4"
            class="mt-3 mb-3"
          />

          <!-- Etapas -->
          <div class="etapas-row">
            <div
              v-for="etapa in cotizacion.etapas"
              :key="etapa.etapa"
              class="etapa-item"
              :class="`etapa--${etapa.estado}`"
            >
              <div class="etapa-icon-wrap">
                <v-icon size="16" :color="colorEtapa(etapa.estado)">{{ iconoEtapa(etapa.etapa) }}</v-icon>
              </div>
              <div class="etapa-label">{{ etapa.label }}</div>
              <div v-if="etapa.empleado" class="etapa-empleado">{{ primerNombre(etapa.empleado) }}</div>
              <div class="etapa-actions">
                <v-btn
                  v-if="etapa.estado === 'pendiente'"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'en_progreso')"
                >
                  Iniciar
                </v-btn>
                <v-btn
                  v-else-if="etapa.estado === 'en_progreso'"
                  size="x-small"
                  color="success"
                  variant="flat"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'completado')"
                >
                  ✓ Listo
                </v-btn>
                <v-btn
                  v-else
                  size="x-small"
                  color="grey"
                  variant="plain"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'pendiente')"
                >
                  Reabrir
                </v-btn>
              </div>
            </div>
          </div>

          <!-- Notas -->
          <div v-if="cotizacion.notas_operaciones" class="mt-2">
            <v-chip size="x-small" color="grey" variant="tonal" prepend-icon="mdi-note-text">
              {{ cotizacion.notas_operaciones }}
            </v-chip>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Dialog asignar empleado -->
    <v-dialog v-model="dialogEtapa.show" max-width="380">
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold pa-4">
          {{ dialogEtapa.etapaLabel }} — {{ dialogEtapa.cliente }}
        </v-card-title>
        <v-card-text>
          <v-select
            v-model="dialogEtapa.empleadoId"
            :items="empleados"
            item-title="nombre"
            item-value="id"
            label="Asignar a empleado (opcional)"
            variant="outlined"
            density="compact"
            clearable
            class="mb-3"
          />
          <v-textarea
            v-model="dialogEtapa.notas"
            label="Notas (opcional)"
            variant="outlined"
            density="compact"
            rows="2"
            hide-details
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialogEtapa.show = false">Cancelar</v-btn>
          <v-btn color="primary" variant="flat" :loading="guardando" @click="confirmarEtapa">
            Guardar
          </v-btn>
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
import axios from '@/axiosInstance'
const api = axios

const cargando  = ref(false)
const guardando = ref(false)
const cotizaciones = ref([])
const empleados    = ref([])
const stats        = ref({})
const busqueda     = ref('')
const filtroEstado = ref('')

const snack = ref({ show: false, color: 'success', msg: '' })

const dialogEtapa = ref({
  show: false, cotizacion: null, etapa: null,
  etapaLabel: '', cliente: '',
  nuevoEstado: '', empleadoId: null, notas: '',
})

// ── Computed ──────────────────────────────────────────────────────
const vencidas = computed(() =>
  cotizaciones.value.filter(c => c.urgencia === 'vencida').length
)

const cotizacionesFiltradas = computed(() => {
  return cotizaciones.value.filter(c => {
    if (filtroEstado.value && c.estado_produccion !== filtroEstado.value) return false
    if (busqueda.value && !c.cliente.toLowerCase().includes(busqueda.value.toLowerCase())) return false
    return true
  })
})

// ── Cargar datos ──────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/taller')
    cotizaciones.value = data.cotizaciones
    empleados.value    = data.empleados
    stats.value        = data.stats
  } catch {
    mostrarSnack('Error al cargar taller', 'error')
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Etapas ────────────────────────────────────────────────────────
function cambiarEtapa(cotizacion, etapa, nuevoEstado) {
  // Si va a iniciar/completar, mostrar dialog para asignar empleado
  if (nuevoEstado !== 'pendiente') {
    dialogEtapa.value = {
      show: true,
      cotizacion,
      etapa,
      etapaLabel: etapa.label,
      cliente: cotizacion.cliente,
      nuevoEstado,
      empleadoId: etapa.empleado_id ?? null,
      notas: etapa.notas ?? '',
    }
  } else {
    // Reabrir: directo sin dialog
    guardarEtapa(cotizacion, etapa, 'pendiente', null, null)
  }
}

async function confirmarEtapa() {
  const { cotizacion, etapa, nuevoEstado, empleadoId, notas } = dialogEtapa.value
  await guardarEtapa(cotizacion, etapa, nuevoEstado, empleadoId, notas)
  dialogEtapa.value.show = false
}

async function guardarEtapa(cotizacion, etapa, estado, empleadoId, notas) {
  guardando.value = true
  try {
    await api.post(`/api/taller/${cotizacion.id}/etapas`, {
      etapa: etapa.etapa,
      estado,
      empleado_id: empleadoId,
      notas,
    })
    // Actualizar local sin recargar todo
    etapa.estado      = estado
    etapa.empleado_id = empleadoId
    etapa.notas       = notas
    if (estado === 'en_progreso') etapa.fecha_inicio = new Date().toISOString().split('T')[0]
    if (estado === 'completado')  etapa.fecha_fin_real = new Date().toISOString().split('T')[0]
    if (estado === 'pendiente') { etapa.fecha_inicio = null; etapa.fecha_fin_real = null }

    // Recalcular progreso local
    const total     = cotizacion.etapas.length
    const completas = cotizacion.etapas.filter(e => e.estado === 'completado').length
    cotizacion.progreso = total > 0 ? Math.round(completas / total * 100) : 0

    mostrarSnack(`Etapa "${etapa.label}" → ${estado}`)
  } catch {
    mostrarSnack('Error al guardar etapa', 'error')
  } finally {
    guardando.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────
function colorEstado(estado) {
  return { 'En Fabricación': 'orange', 'Lista para Corte': 'blue', 'Fabricadas OK': 'green' }[estado] ?? 'grey'
}

function colorEtapa(estado) {
  return { pendiente: 'grey', en_progreso: 'orange', completado: 'success' }[estado] ?? 'grey'
}

function iconoEtapa(etapa) {
  const map = {
    corte_perfiles:         'mdi-scissors-cutting',
    corte_vidrio:           'mdi-image-filter-frames',
    fabricacion_termopanel: 'mdi-layers',
    armado:                 'mdi-hammer-wrench',
    vidriado:               'mdi-window-open',
    junquillos:             'mdi-minus',
    control:                'mdi-magnify-scan',
    instalacion:            'mdi-home-wrench',
    entrega:                'mdi-truck-delivery',
  }
  return map[etapa] ?? 'mdi-circle-small'
}

function claseEntrega(c) {
  if (c.urgencia === 'vencida') return 'text-error font-weight-bold'
  if (c.urgencia === 'critica') return 'text-error'
  if (c.urgencia === 'proxima') return 'text-warning'
  return 'text-grey'
}

function fmtFecha(f) {
  if (!f) return ''
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

function primerNombre(nombre) {
  return nombre?.split(' ')[0] ?? ''
}

function mostrarSnack(msg, color = 'success') {
  snack.value = { show: true, color, msg }
}
</script>

<style scoped>
.cotizacion-card {
  border-left: 4px solid transparent;
  transition: box-shadow 0.15s;
}
.cotizacion-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.2) !important; }
.urgencia-vencida  { border-left-color: rgb(244, 67, 54)  !important; }
.urgencia-critica  { border-left-color: rgb(244, 67, 54)  !important; }
.urgencia-proxima  { border-left-color: rgb(255, 152, 0)  !important; }
.urgencia-normal   { border-left-color: rgb(var(--v-theme-primary)) !important; }

/* Etapas */
.etapas-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.etapa-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 8px 6px;
  border-radius: 8px;
  min-width: 76px;
  max-width: 90px;
  border: 1px solid rgba(255,255,255,0.1);
  flex: 1;
  transition: background 0.15s;
}

.etapa--pendiente  { background: rgba(255,255,255,0.03); }
.etapa--en_progreso { background: rgba(255, 152, 0, 0.12); border-color: rgba(255,152,0,0.4); }
.etapa--completado  { background: rgba(76, 175, 80, 0.1);  border-color: rgba(76,175,80,0.4); }

.etapa-icon-wrap {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.08);
}

.etapa-label {
  font-size: 10px;
  font-weight: 600;
  text-align: center;
  line-height: 1.2;
  color: rgba(255,255,255,0.85);
}

.etapa-empleado {
  font-size: 9px;
  color: rgba(255,255,255,0.5);
  text-align: center;
}

.etapa-actions {
  width: 100%;
  margin-top: 2px;
}
</style>
