<template>
  <div>
    <div class="d-flex align-center mb-4" style="gap:12px">
      <VIcon size="28" color="primary">mdi-clock-check-outline</VIcon>
      <div>
        <h2 class="text-h5 mb-0">Asistencia</h2>
        <div class="text-caption text-medium-emphasis">Marcaciones y atrasos en vivo desde Workera</div>
      </div>
    </div>

    <VAlert v-if="error" type="warning" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </VAlert>

    <VTabs v-model="tab" class="mb-4">
      <VTab value="diario">Diario</VTab>
      <VTab value="semanal">Semanal</VTab>
      <VTab value="periodo">Resumen / KPIs</VTab>
    </VTabs>

    <!-- ── DIARIO ──────────────────────────────────────────────────────────── -->
    <div v-if="tab === 'diario'">
      <VCard class="mb-4">
        <VCardText class="d-flex flex-wrap align-center" style="gap:16px">
          <VTextField v-model="fecha" type="date" label="Fecha" density="compact" hide-details style="max-width:190px" />
          <VTextField v-model.number="tolerancia" type="number" label="Tolerancia (min)" density="compact" hide-details style="max-width:150px" />
          <VBtn color="primary" :loading="loadingDia" @click="cargarDiario">
            <VIcon start>mdi-magnify</VIcon>Ver día
          </VBtn>
        </VCardText>
      </VCard>

      <VRow v-if="repDia" class="mb-2">
        <VCol v-for="c in tarjetasDia" :key="c.label" cols="6" md="2">
          <VCard :color="c.color" variant="tonal">
            <VCardText class="text-center py-3">
              <div class="text-h5 font-weight-bold">{{ c.valor }}</div>
              <div class="text-caption">{{ c.label }}</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VCard v-if="repDia">
        <VCardText class="pb-0">
          <VAutocomplete
            v-model="filtroTrabajador"
            :items="trabajadores"
            item-title="nombre"
            item-value="code"
            label="Filtrar por trabajador"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            style="max-width:320px"
          />
        </VCardText>
        <VDataTable :headers="headersDia" :items="diasFiltrados" :items-per-page="25" density="compact">
          <template #item.estado="{ item }">
            <VChip size="x-small" :color="colorEstado(item.estado)" variant="tonal">{{ item.estado }}</VChip>
          </template>
          <template #item.real="{ item }">
            <span :class="item.estado === 'Atraso' ? 'text-warning font-weight-medium' : ''">{{ item.real || '—' }}</span>
          </template>
          <template #item.salida="{ item }">{{ item.salida || '—' }}</template>
          <template #item.atraso_min="{ item }">
            <span v-if="item.estado === 'Atraso'" class="text-warning font-weight-medium">+{{ item.atraso_min }} min</span>
            <span v-else class="text-disabled">—</span>
          </template>
          <template #item.permiso="{ item }">{{ item.permiso || '' }}</template>
          <template #bottom>
            <div class="pa-3 text-caption text-medium-emphasis">{{ diasFiltrados.length }} trabajadores con horario</div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- ── SEMANAL ─────────────────────────────────────────────────────────── -->
    <div v-if="tab === 'semanal'">
      <VCard class="mb-4">
        <VCardText class="d-flex flex-wrap align-center" style="gap:16px">
          <VTextField v-model="desde" type="date" label="Desde" density="compact" hide-details style="max-width:190px" />
          <VTextField v-model="hasta" type="date" label="Hasta" density="compact" hide-details style="max-width:190px" />
          <VTextField v-model.number="tolerancia" type="number" label="Tolerancia (min)" density="compact" hide-details style="max-width:150px" />
          <VBtn color="primary" :loading="loadingSem" @click="cargarSemanal">
            <VIcon start>mdi-magnify</VIcon>Ver resumen
          </VBtn>
        </VCardText>
      </VCard>

      <template v-if="repSem">
        <VCard class="mb-4">
          <VCardText class="d-flex flex-wrap align-center" style="gap:16px">
            <VAutocomplete
              v-model="filtroTrabajador"
              :items="trabajadores"
              item-title="nombre"
              item-value="code"
              label="Filtrar por trabajador"
              density="compact"
              variant="outlined"
              clearable
              hide-details
              style="max-width:320px"
            />
            <div class="d-flex" style="gap:8px">
              <VBtn
                :variant="vistaSem === 'resumen' ? 'flat' : 'outlined'"
                :color="vistaSem === 'resumen' ? 'primary' : undefined"
                class="text-none"
                @click="vistaSem = 'resumen'"
              >Resumen</VBtn>
              <VBtn
                :variant="vistaSem === 'grilla' ? 'flat' : 'outlined'"
                :color="vistaSem === 'grilla' ? 'primary' : undefined"
                class="text-none"
                @click="vistaSem = 'grilla'"
              >Grilla diaria</VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- Resumen por trabajador -->
        <VCard v-if="vistaSem === 'resumen'">
          <VCardTitle class="text-subtitle-1">Resumen por trabajador</VCardTitle>
          <VDataTable :headers="headersSem" :items="resumenFiltrado" :items-per-page="25" density="compact">
            <template #item.atrasos="{ item }">
              <VChip v-if="item.atrasos > 0" size="x-small" color="warning" variant="tonal">{{ item.atrasos }}</VChip>
              <span v-else class="text-disabled">0</span>
            </template>
            <template #item.min_atraso="{ item }">
              <span v-if="item.min_atraso > 0" class="text-warning">{{ item.min_atraso }} min</span>
              <span v-else class="text-disabled">—</span>
            </template>
            <template #item.ausentes="{ item }">
              <VChip v-if="item.ausentes > 0" size="x-small" color="error" variant="tonal">{{ item.ausentes }}</VChip>
              <span v-else class="text-disabled">0</span>
            </template>
            <template #bottom>
              <div class="pa-3 text-caption text-medium-emphasis">{{ resumenFiltrado.length }} trabajadores</div>
            </template>
          </VDataTable>
        </VCard>

        <!-- Grilla diaria: hora de entrada por día -->
        <VCard v-else>
          <VCardTitle class="text-subtitle-1">Hora de entrada → salida por día</VCardTitle>
          <VCardText style="overflow-x:auto">
            <VTable density="compact">
              <thead>
                <tr>
                  <th class="text-left text-no-wrap">Trabajador</th>
                  <th v-for="f in diasCols" :key="f" class="text-center text-no-wrap">{{ fmtDiaCol(f) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in grilla" :key="r.code">
                  <td class="text-no-wrap font-weight-medium">{{ r.nombre }}</td>
                  <td v-for="f in diasCols" :key="f" class="text-center">
                    <span v-if="r.celdas[f]" :class="celdaClase(r.celdas[f])">{{ celdaTexto(r.celdas[f]) }}</span>
                    <span v-else class="text-disabled">·</span>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div class="mt-3 text-caption text-medium-emphasis">
              <span class="text-success">verde</span> = a tiempo ·
              <span class="text-warning">naranjo</span> = atraso ·
              <span class="text-error">rojo</span> = ausente ·
              <span class="text-info">azul</span> = permiso ·
              <span class="text-secondary">gris</span> = feriado · · = sin horario
            </div>
          </VCardText>
        </VCard>
      </template>
    </div>

    <!-- ── RESUMEN / KPIs ──────────────────────────────────────────────────── -->
    <div v-if="tab === 'periodo'">
      <VCard class="mb-4">
        <VCardText class="d-flex flex-wrap align-center" style="gap:16px">
          <div class="d-flex" style="gap:8px">
            <VBtn
              :variant="modoPeriodo === 'mes' ? 'flat' : 'outlined'"
              :color="modoPeriodo === 'mes' ? 'primary' : undefined"
              class="text-none" size="small"
              @click="modoPeriodo = 'mes'"
            >Por mes</VBtn>
            <VBtn
              :variant="modoPeriodo === 'rango' ? 'flat' : 'outlined'"
              :color="modoPeriodo === 'rango' ? 'primary' : undefined"
              class="text-none" size="small"
              @click="modoPeriodo = 'rango'"
            >Rango</VBtn>
          </div>

          <template v-if="modoPeriodo === 'mes'">
            <VSelect v-model.number="mesSel" :items="mesesOpts" label="Mes" density="compact" hide-details style="max-width:170px" />
            <VSelect v-model.number="anioSel" :items="aniosOpts" label="Año" density="compact" hide-details style="max-width:120px" />
          </template>
          <template v-else>
            <VTextField v-model="pDesde" type="date" label="Desde" density="compact" hide-details style="max-width:180px" />
            <VTextField v-model="pHasta" type="date" label="Hasta" density="compact" hide-details style="max-width:180px" />
          </template>

          <VTextField v-model.number="tolerancia" type="number" label="Tolerancia (min)" density="compact" hide-details style="max-width:150px" />
          <VBtn color="primary" :loading="loadingPer" @click="cargarPeriodo">
            <VIcon start>mdi-chart-box-outline</VIcon>Ver resumen
          </VBtn>
        </VCardText>
      </VCard>

      <template v-if="repPer">
        <!-- KPIs -->
        <VRow class="mb-1">
          <VCol v-for="k in kpis" :key="k.label" cols="12" sm="6" md="4" lg="">
            <VCard :color="k.color" variant="tonal" height="100%">
              <VCardText class="py-3">
                <div class="d-flex align-center mb-1" style="gap:6px">
                  <VIcon size="18">{{ k.icon }}</VIcon>
                  <span class="text-caption font-weight-medium">{{ k.label }}</span>
                </div>
                <div class="text-subtitle-1 font-weight-bold text-truncate" :title="k.nombre">{{ k.nombre || '—' }}</div>
                <div class="text-caption">{{ k.detalle }}</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Resumen por trabajador -->
        <VCard>
          <VCardText class="pb-0">
            <VAutocomplete
              v-model="filtroTrabajador"
              :items="trabajadoresPer"
              item-title="nombre" item-value="code"
              label="Filtrar por trabajador"
              density="compact" variant="outlined" clearable hide-details
              style="max-width:320px"
            />
          </VCardText>
          <VDataTable :headers="headersPer" :items="resumenPerFiltrado" :items-per-page="50" density="compact"
                      :sort-by="[{ key: 'ausentes', order: 'desc' }]">
            <template #item.pct_asistencia="{ item }">
              <VChip size="x-small" :color="item.pct_asistencia >= 95 ? 'success' : item.pct_asistencia >= 85 ? 'warning' : 'error'" variant="tonal">
                {{ item.pct_asistencia }}%
              </VChip>
            </template>
            <template #item.atrasos="{ item }">
              <span :class="item.atrasos > 0 ? 'text-warning' : 'text-disabled'">{{ item.atrasos }}</span>
            </template>
            <template #item.min_atraso="{ item }">
              <span :class="item.min_atraso > 0 ? 'text-warning' : 'text-disabled'">{{ item.min_atraso || '—' }}</span>
            </template>
            <template #item.ausentes="{ item }">
              <VChip v-if="item.ausentes > 0" size="x-small" color="error" variant="tonal">{{ item.ausentes }}</VChip>
              <span v-else class="text-disabled">0</span>
            </template>
            <template #item.acciones="{ item }">
              <VBtn size="x-small" variant="text" color="primary" class="text-none"
                    :disabled="item.ausentes === 0 && item.atrasos === 0" @click="abrirDetalle(item)">
                Ver detalle
              </VBtn>
            </template>
            <template #bottom>
              <div class="pa-3 text-caption text-medium-emphasis">{{ resumenPerFiltrado.length }} trabajadores · {{ repPer.desde }} a {{ repPer.hasta }}</div>
            </template>
          </VDataTable>
        </VCard>
      </template>
    </div>

    <!-- Detalle de faltas/atrasos por persona -->
    <VDialog v-model="dlgDetalle" max-width="620">
      <VCard v-if="detalleSel">
        <VCardTitle class="d-flex align-center justify-space-between">
          <span class="text-subtitle-1">{{ detalleSel.nombre }}</span>
          <VBtn icon="mdi-close" variant="text" size="small" @click="dlgDetalle = false" />
        </VCardTitle>
        <VCardText>
          <div class="d-flex flex-wrap mb-3" style="gap:8px">
            <VChip size="small" color="error" variant="tonal">{{ detalleSel.ausentes }} faltas</VChip>
            <VChip size="small" color="warning" variant="tonal">{{ detalleSel.atrasos }} atrasos ({{ detalleSel.min_atraso }} min)</VChip>
            <VChip size="small" color="success" variant="tonal">{{ detalleSel.pct_asistencia }}% asistencia</VChip>
          </div>

          <div v-if="detalleDias.ausentes.length" class="mb-3">
            <div class="text-caption font-weight-bold text-error mb-1">Días que faltó ({{ detalleDias.ausentes.length }})</div>
            <VTable density="compact">
              <tbody>
                <tr v-for="d in detalleDias.ausentes" :key="d.fecha">
                  <td class="text-no-wrap">{{ fmtFechaLarga(d.fecha) }}</td>
                  <td class="text-medium-emphasis">{{ d.turno || '—' }}</td>
                  <td class="text-medium-emphasis">esperado {{ d.esperada }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <div v-if="detalleDias.atrasos.length">
            <div class="text-caption font-weight-bold text-warning mb-1">Días con atraso ({{ detalleDias.atrasos.length }})</div>
            <VTable density="compact">
              <tbody>
                <tr v-for="d in detalleDias.atrasos" :key="d.fecha">
                  <td class="text-no-wrap">{{ fmtFechaLarga(d.fecha) }}</td>
                  <td>llegó {{ d.real }}</td>
                  <td class="text-warning">+{{ d.atraso_min }} min</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <div v-if="!detalleDias.ausentes.length && !detalleDias.atrasos.length" class="text-center text-medium-emphasis py-4">
            Sin faltas ni atrasos en el período 🎉
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from '@/axiosInstance'

const tab = ref('diario')
const error = ref('')
const tolerancia = ref(5)

// Diario
const hoy = new Date().toISOString().slice(0, 10)
const fecha = ref(hoy)
const loadingDia = ref(false)
const repDia = ref(null)

// Semanal (semana actual: lunes a hoy)
function lunesDeEstaSemana() {
  const d = new Date()
  const day = (d.getDay() + 6) % 7 // 0 = lunes
  d.setDate(d.getDate() - day)
  return d.toISOString().slice(0, 10)
}
const desde = ref(lunesDeEstaSemana())
const hasta = ref(hoy)
const loadingSem = ref(false)
const repSem = ref(null)

// Período / KPIs
const now2 = new Date()
const modoPeriodo = ref('mes')
const mesSel = ref(now2.getMonth() + 1)
const anioSel = ref(now2.getFullYear())
const pDesde = ref(lunesDeEstaSemana())
const pHasta = ref(hoy)
const loadingPer = ref(false)
const repPer = ref(null)
const mesesOpts = [
  { title: 'Enero', value: 1 }, { title: 'Febrero', value: 2 }, { title: 'Marzo', value: 3 },
  { title: 'Abril', value: 4 }, { title: 'Mayo', value: 5 }, { title: 'Junio', value: 6 },
  { title: 'Julio', value: 7 }, { title: 'Agosto', value: 8 }, { title: 'Septiembre', value: 9 },
  { title: 'Octubre', value: 10 }, { title: 'Noviembre', value: 11 }, { title: 'Diciembre', value: 12 },
]
const aniosOpts = [now2.getFullYear() - 1, now2.getFullYear(), now2.getFullYear() + 1]

const headersDia = [
  { title: 'Trabajador', value: 'nombre' },
  { title: 'Sucursal', value: 'sucursal' },
  { title: 'Turno', value: 'turno' },
  { title: 'Esperada', value: 'esperada' },
  { title: 'Entrada real', value: 'real' },
  { title: 'Salida', value: 'salida' },
  { title: 'Atraso', value: 'atraso_min' },
  { title: 'Estado', value: 'estado' },
  { title: 'Permiso', value: 'permiso' },
]

const headersSem = [
  { title: 'Trabajador', value: 'nombre' },
  { title: 'Sucursal', value: 'sucursal' },
  { title: 'Días c/horario', value: 'dias_horario' },
  { title: 'A tiempo', value: 'a_tiempo' },
  { title: 'Atrasos', value: 'atrasos' },
  { title: 'Min. atraso', value: 'min_atraso' },
  { title: 'Ausencias', value: 'ausentes' },
  { title: 'Permisos', value: 'permisos' },
]

const tarjetasDia = computed(() => {
  if (!repDia.value) return []
  const r = repDia.value.resumen
  return [
    { label: 'Con horario', valor: r.con_horario, color: 'primary' },
    { label: 'A tiempo', valor: r.a_tiempo, color: 'success' },
    { label: 'Atrasos', valor: r.atrasos, color: 'warning' },
    { label: 'Ausentes', valor: r.ausentes, color: 'error' },
    { label: 'Permisos', valor: r.permisos, color: 'info' },
    { label: 'Min. atraso', valor: r.min_atraso, color: 'warning' },
  ]
})

function colorEstado(e) {
  return { 'A tiempo': 'success', 'Atraso': 'warning', 'Ausente': 'error', 'Permiso': 'info', 'Feriado': 'secondary' }[e] || 'default'
}

// ── Filtro por trabajador y grilla diaria ──────────────────────────────────
const filtroTrabajador = ref(null)
const vistaSem = ref('resumen')

const trabajadores = computed(() => {
  const src = tab.value === 'diario' ? (repDia.value?.dias || []) : (repSem.value?.dias || [])
  const map = new Map()
  for (const r of src) if (r.code && !map.has(r.code)) map.set(r.code, r.nombre)
  return [...map].map(([code, nombre]) => ({ code, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

const diasFiltrados = computed(() => {
  const arr = repDia.value?.dias || []
  return filtroTrabajador.value ? arr.filter(d => d.code === filtroTrabajador.value) : arr
})

const resumenFiltrado = computed(() => {
  const arr = repSem.value?.resumen || []
  return filtroTrabajador.value ? arr.filter(r => r.code === filtroTrabajador.value) : arr
})

const diasCols = computed(() => {
  if (!repSem.value) return []
  return [...new Set(repSem.value.dias.map(d => d.fecha))].sort()
})

const grilla = computed(() => {
  if (!repSem.value) return []
  let dias = repSem.value.dias
  if (filtroTrabajador.value) dias = dias.filter(d => d.code === filtroTrabajador.value)
  const map = new Map()
  for (const d of dias) {
    if (!map.has(d.code)) map.set(d.code, { code: d.code, nombre: d.nombre, celdas: {} })
    map.get(d.code).celdas[d.fecha] = d
  }
  return [...map.values()].sort((a, b) => a.nombre.localeCompare(b.nombre))
})

function fmtDiaCol(fecha) {
  const d = new Date(fecha + 'T00:00:00')
  return d.toLocaleDateString('es-CL', { weekday: 'short', day: '2-digit', month: '2-digit' })
}

function celdaTexto(d) {
  if (d.estado === 'Feriado') return d.real ? `Fer. ${d.real}` : 'Feriado'
  if (d.estado === 'Permiso') return 'Perm.'
  if (d.estado === 'Ausente') return 'Ausente'
  const ent = d.real || '—'
  return d.salida ? `${ent} → ${d.salida}` : ent
}

function celdaClase(d) {
  return {
    'A tiempo': 'text-success',
    'Atraso': 'text-warning font-weight-bold',
    'Ausente': 'text-error',
    'Permiso': 'text-info',
    'Feriado': 'text-secondary',
  }[d.estado] || ''
}

async function cargarDiario() {
  loadingDia.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/api/asistencia/diario', { params: { fecha: fecha.value, tolerancia: tolerancia.value } })
    repDia.value = data
  } catch (e) {
    error.value = e.response?.data?.error || 'No se pudo cargar la asistencia del día'
    repDia.value = null
  } finally {
    loadingDia.value = false
  }
}

async function cargarSemanal() {
  loadingSem.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/api/asistencia/semanal', { params: { desde: desde.value, hasta: hasta.value, tolerancia: tolerancia.value } })
    repSem.value = data
  } catch (e) {
    error.value = e.response?.data?.error || 'No se pudo cargar el resumen semanal'
    repSem.value = null
  } finally {
    loadingSem.value = false
  }
}

// ── Período / KPIs ─────────────────────────────────────────────────────────
const headersPer = [
  { title: 'Trabajador', value: 'nombre' },
  { title: 'Días c/horario', value: 'dias_horario' },
  { title: 'A tiempo', value: 'a_tiempo' },
  { title: 'Atrasos', value: 'atrasos' },
  { title: 'Min. atraso', value: 'min_atraso' },
  { title: 'Ausencias', value: 'ausentes' },
  { title: '% Asist.', value: 'pct_asistencia' },
  { title: '', value: 'acciones', sortable: false },
]

const resumenPer = computed(() =>
  (repPer.value?.resumen || []).map(r => ({
    ...r,
    pct_asistencia: r.dias_horario > 0 ? Math.round((r.dias_horario - r.ausentes) / r.dias_horario * 100) : 0,
  })),
)

const trabajadoresPer = computed(() =>
  (repPer.value?.resumen || [])
    .map(r => ({ code: r.code, nombre: r.nombre }))
    .sort((a, b) => a.nombre.localeCompare(b.nombre)),
)

const resumenPerFiltrado = computed(() =>
  filtroTrabajador.value ? resumenPer.value.filter(r => r.code === filtroTrabajador.value) : resumenPer.value,
)

const kpis = computed(() => {
  const arr = (repPer.value?.resumen || []).filter(r => r.dias_horario > 0)
  if (!arr.length) return []
  const m = arr.map(r => ({
    ...r,
    pct_asist: (r.dias_horario - r.ausentes) / r.dias_horario,
    pct_punt: (r.a_tiempo + r.atrasos) > 0 ? r.a_tiempo / (r.a_tiempo + r.atrasos) : 1,
    prom_atraso: r.atrasos > 0 ? r.min_atraso / r.atrasos : 0,
  }))
  const top = sel => m.reduce((a, b) => (sel(b) > sel(a) ? b : a))
  // Solo quienes marcaron (a tiempo o atrasados) cuentan para asistencia/puntualidad
  const conMarcas = m.filter(r => (r.a_tiempo + r.atrasos) > 0)
  const mejor = conMarcas.length
    ? conMarcas.reduce((a, b) => (b.pct_asist > a.pct_asist || (b.pct_asist === a.pct_asist && b.dias_horario > a.dias_horario)) ? b : a)
    : null
  const punt = conMarcas.length
    ? conMarcas.reduce((a, b) => {
        const ma = a.a_tiempo + a.atrasos, mb = b.a_tiempo + b.atrasos
        return (b.pct_punt > a.pct_punt || (b.pct_punt === a.pct_punt && mb > ma)) ? b : a
      })
    : null
  const atras = top(r => r.min_atraso)
  const ausen = top(r => r.ausentes)
  const prom = top(r => r.prom_atraso)
  return [
    { label: 'Mejor asistencia', icon: 'mdi-trophy', color: 'success', nombre: mejor ? mejor.nombre : '—', detalle: mejor ? `${Math.round(mejor.pct_asist * 100)}% · ${mejor.ausentes} faltas` : 'sin marcas' },
    { label: 'Más puntual', icon: 'mdi-clock-check-outline', color: 'info', nombre: punt ? punt.nombre : '—', detalle: punt ? `${Math.round(punt.pct_punt * 100)}% a tiempo (${punt.a_tiempo}/${punt.a_tiempo + punt.atrasos})` : 'sin marcas' },
    { label: 'Más atrasado', icon: 'mdi-timer-sand', color: 'warning', nombre: atras.min_atraso > 0 ? atras.nombre : '—', detalle: atras.min_atraso > 0 ? `${atras.min_atraso} min · ${atras.atrasos} atrasos` : 'sin atrasos' },
    { label: 'Más ausencias', icon: 'mdi-account-off-outline', color: 'error', nombre: ausen.ausentes > 0 ? ausen.nombre : '—', detalle: ausen.ausentes > 0 ? `${ausen.ausentes} faltas` : 'sin faltas' },
    { label: 'Peor prom. atraso', icon: 'mdi-chart-timeline-variant', color: 'warning', nombre: prom.prom_atraso > 0 ? prom.nombre : '—', detalle: prom.prom_atraso > 0 ? `${Math.round(prom.prom_atraso)} min/atraso` : 'sin atrasos' },
  ]
})

// Detalle de faltas/atrasos por persona
const dlgDetalle = ref(false)
const detalleSel = ref(null)
function abrirDetalle(item) {
  detalleSel.value = { ...item, min_atraso: item.min_atraso || 0 }
  dlgDetalle.value = true
}
const detalleDias = computed(() => {
  const res = { ausentes: [], atrasos: [] }
  if (!detalleSel.value || !repPer.value) return res
  for (const d of repPer.value.dias) {
    if (d.code !== detalleSel.value.code) continue
    if (d.estado === 'Ausente') res.ausentes.push(d)
    else if (d.estado === 'Atraso') res.atrasos.push(d)
  }
  res.ausentes.sort((a, b) => a.fecha.localeCompare(b.fecha))
  res.atrasos.sort((a, b) => a.fecha.localeCompare(b.fecha))
  return res
})
function fmtFechaLarga(f) {
  return new Date(f + 'T00:00:00').toLocaleDateString('es-CL', { weekday: 'long', day: '2-digit', month: 'long' })
}

async function cargarPeriodo() {
  loadingPer.value = true
  error.value = ''
  try {
    let d, h
    if (modoPeriodo.value === 'mes') {
      const mm = String(mesSel.value).padStart(2, '0')
      const last = new Date(anioSel.value, mesSel.value, 0).getDate()
      d = `${anioSel.value}-${mm}-01`
      h = `${anioSel.value}-${mm}-${String(last).padStart(2, '0')}`
    } else {
      d = pDesde.value
      h = pHasta.value
    }
    const { data } = await axios.get('/api/asistencia/semanal', { params: { desde: d, hasta: h, tolerancia: tolerancia.value } })
    repPer.value = data
    filtroTrabajador.value = null
  } catch (e) {
    error.value = e.response?.data?.error || 'No se pudo cargar el resumen del período'
    repPer.value = null
  } finally {
    loadingPer.value = false
  }
}

cargarDiario()
</script>
