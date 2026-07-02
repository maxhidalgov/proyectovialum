<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-2">
      <div class="d-flex align-center gap-3">
        <v-icon icon="mdi-calendar-month" size="32" color="primary" />
        <div>
          <h1 class="text-h5 font-weight-bold">Calendario</h1>
          <p class="text-caption text-grey mt-1">Recordatorios, visitas, entregas y ausencias</p>
        </div>
      </div>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="abrirNuevo()">
        Nuevo recordatorio
      </v-btn>
    </div>

    <v-row>
      <!-- Filtros de fuentes -->
      <v-col cols="12" md="2">
        <v-card variant="outlined" class="pa-3">
          <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">Mostrar</div>
          <v-checkbox
            v-for="f in fuentes"
            :key="f.value"
            v-model="fuentesActivas"
            :value="f.value"
            density="compact"
            hide-details
            @update:model-value="refetch"
          >
            <template #label>
              <div class="d-flex align-center gap-2">
                <span class="leyenda-dot" :style="{ background: f.color }" />
                <span class="text-body-2">{{ f.label }}</span>
              </div>
            </template>
          </v-checkbox>
        </v-card>
      </v-col>

      <!-- Calendario -->
      <v-col cols="12" md="10">
        <v-card variant="outlined" class="pa-3">
          <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </v-card>
      </v-col>
    </v-row>

    <!-- Dialog crear/editar recordatorio -->
    <v-dialog v-model="dialog.show" max-width="480">
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold pa-4 d-flex align-center justify-space-between">
          {{ dialog.id ? 'Editar recordatorio' : 'Nuevo recordatorio' }}
          <v-btn v-if="dialog.id" icon="mdi-delete" size="small" variant="text" color="error" @click="eliminar" />
        </v-card-title>
        <v-card-text>
          <v-text-field
            v-model="dialog.titulo"
            label="Título *"
            variant="outlined"
            density="compact"
            class="mb-3"
            autofocus
          />
          <v-select
            v-model="dialog.tipo"
            :items="tiposRecordatorio"
            label="Tipo"
            variant="outlined"
            density="compact"
            class="mb-3"
          />
          <v-row dense>
            <v-col cols="7">
              <v-text-field
                v-model="dialog.fecha"
                label="Fecha *"
                type="date"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="5">
              <v-text-field
                v-model="dialog.hora"
                label="Hora"
                type="time"
                variant="outlined"
                density="compact"
              />
            </v-col>
          </v-row>
          <v-textarea
            v-model="dialog.descripcion"
            label="Descripción"
            variant="outlined"
            density="compact"
            rows="2"
            hide-details
            class="mb-3"
          />
          <v-select
            v-if="dialog.id"
            v-model="dialog.estado"
            :items="[
              { title: 'Pendiente', value: 'pendiente' },
              { title: 'Completado', value: 'completado' },
              { title: 'Cancelado', value: 'cancelado' },
            ]"
            label="Estado"
            variant="outlined"
            density="compact"
            hide-details
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog.show = false">Cancelar</v-btn>
          <v-btn color="primary" variant="flat" :loading="guardando" :disabled="!dialog.titulo || !dialog.fecha" @click="guardar">
            Guardar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Dialog detalle de evento no editable -->
    <v-dialog v-model="detalle.show" max-width="420">
      <v-card v-if="detalle.evento">
        <v-card-title class="text-body-1 font-weight-bold pa-4">
          {{ detalle.evento.title }}
        </v-card-title>
        <v-card-text>
          <div v-for="(item, i) in detalle.campos" :key="i" class="d-flex justify-space-between py-1 border-b">
            <span class="text-caption text-medium-emphasis">{{ item.label }}</span>
            <span class="text-body-2 text-right">{{ item.valor }}</span>
          </div>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-btn
            v-if="detalle.cotizacionId"
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="mdi-factory"
            :to="{ name: 'produccion' }"
          >
            Ver en producción
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="detalle.show = false">Cerrar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">
      {{ snack.msg }}
    </v-snackbar>

  </v-container>
</template>

<script setup>
import { ref, reactive } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'
import axios from '@/axiosInstance'
const api = axios

const calendarRef = ref(null)
const guardando   = ref(false)

const fuentes = [
  { value: 'recordatorio', label: 'Recordatorios', color: '#4caf50' },
  { value: 'visita',       label: 'Visitas',       color: '#2196f3' },
  { value: 'entrega',      label: 'Entregas',      color: '#ff9800' },
  { value: 'ausencia',     label: 'Ausencias',     color: '#ffc107' },
]
const fuentesActivas = ref(['recordatorio', 'visita', 'entrega', 'ausencia'])

const tiposRecordatorio = [
  { title: '📞 Llamada',      value: 'llamada' },
  { title: '👥 Reunión',      value: 'reunion' },
  { title: '✓ Tarea',        value: 'tarea' },
  { title: '💰 Pago',         value: 'pago' },
  { title: '🔄 Seguimiento',  value: 'seguimiento' },
  { title: '🚚 Entrega',      value: 'entrega' },
  { title: '· Otro',          value: 'otro' },
]

const snack   = ref({ show: false, color: 'success', msg: '' })
const dialog  = reactive({ show: false, id: null, titulo: '', tipo: 'tarea', fecha: '', hora: '', descripcion: '', estado: 'pendiente' })
const detalle = reactive({ show: false, evento: null, campos: [], cotizacionId: null })

// ── FullCalendar options ──────────────────────────────────────────
const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: 'es',
  firstDay: 1,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,listWeek',
  },
  buttonText: {
    today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista',
  },
  height: 'auto',
  dayMaxEvents: 3,
  navLinks: true,
  editable: false,
  selectable: true,
  events: async (info, success, failure) => {
    try {
      const { data } = await api.get('/api/calendario/eventos', {
        params: {
          start: info.startStr.split('T')[0],
          end: info.endStr.split('T')[0],
          fuentes: fuentesActivas.value.join(','),
        },
      })
      success(data)
    } catch (e) {
      failure(e)
    }
  },
  dateClick: (info) => abrirNuevo(info.dateStr),
  eventClick: (info) => onEventClick(info),
})

// ── Acciones ──────────────────────────────────────────────────────
function refetch() {
  calendarRef.value?.getApi().refetchEvents()
}

function abrirNuevo(fecha = null) {
  Object.assign(dialog, {
    show: true, id: null, titulo: '', tipo: 'tarea',
    fecha: fecha || new Date().toISOString().split('T')[0],
    hora: '', descripcion: '', estado: 'pendiente',
  })
}

function onEventClick(info) {
  const props = info.event.extendedProps

  // Recordatorios → editar
  if (props.fuente === 'recordatorio' && props.editable) {
    Object.assign(dialog, {
      show: true,
      id: props.recordatorio_id,
      titulo: info.event.title.replace(/^[^\s]+\s/, ''), // quitar emoji inicial
      tipo: props.tipo,
      fecha: info.event.startStr.split('T')[0],
      hora: info.event.startStr.includes('T') ? info.event.startStr.split('T')[1].slice(0, 5) : '',
      descripcion: props.descripcion || '',
      estado: props.estado,
    })
    return
  }

  // Otros → detalle read-only
  const campos = []
  if (props.cliente)          campos.push({ label: 'Cliente', valor: props.cliente })
  if (props.empleado)         campos.push({ label: 'Empleado', valor: props.empleado })
  if (props.empleados)        campos.push({ label: 'Empleados', valor: props.empleados })
  if (props.tipo)             campos.push({ label: 'Tipo', valor: props.tipo })
  if (props.estado)           campos.push({ label: 'Estado', valor: props.estado })
  if (props.estado_produccion) campos.push({ label: 'Estado producción', valor: props.estado_produccion })
  if (props.motivo)           campos.push({ label: 'Motivo', valor: props.motivo })
  if (props.notas)            campos.push({ label: 'Notas', valor: props.notas })
  if (props.vencida)          campos.push({ label: '⚠️', valor: 'Entrega vencida' })

  Object.assign(detalle, {
    show: true,
    evento: { title: info.event.title },
    campos,
    cotizacionId: props.cotizacion_id || null,
  })
}

async function guardar() {
  guardando.value = true
  try {
    const payload = {
      titulo: dialog.titulo,
      tipo: dialog.tipo,
      fecha: dialog.fecha,
      hora: dialog.hora || null,
      descripcion: dialog.descripcion || null,
    }

    if (dialog.id) {
      payload.estado = dialog.estado
      await api.patch(`/api/recordatorios/${dialog.id}`, payload)
    } else {
      await api.post('/api/recordatorios', payload)
    }

    dialog.show = false
    refetch()
    mostrarSnack('Recordatorio guardado')
  } catch {
    mostrarSnack('Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

async function eliminar() {
  if (!dialog.id) return
  try {
    await api.delete(`/api/recordatorios/${dialog.id}`)
    dialog.show = false
    refetch()
    mostrarSnack('Recordatorio eliminado')
  } catch {
    mostrarSnack('Error al eliminar', 'error')
  }
}

function mostrarSnack(msg, color = 'success') {
  snack.value = { show: true, color, msg }
}
</script>

<style scoped>
.leyenda-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.border-b {
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.border-b:last-child { border-bottom: none; }

:deep(.fc) {
  font-size: 13px;
}
:deep(.fc .fc-toolbar-title) {
  font-size: 1.1rem;
  font-weight: 600;
}
:deep(.fc-event) {
  cursor: pointer;
  font-size: 11px;
}
:deep(.fc-daygrid-day.fc-day-today) {
  background: rgba(var(--v-theme-primary), 0.06) !important;
}
</style>
