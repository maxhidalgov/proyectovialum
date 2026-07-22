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
        <VDataTable :headers="headersDia" :items="repDia.dias" :items-per-page="25" density="compact">
          <template #item.estado="{ item }">
            <VChip size="x-small" :color="colorEstado(item.estado)" variant="tonal">{{ item.estado }}</VChip>
          </template>
          <template #item.real="{ item }">
            <span :class="item.estado === 'Atraso' ? 'text-warning font-weight-medium' : ''">{{ item.real || '—' }}</span>
          </template>
          <template #item.atraso_min="{ item }">
            <span v-if="item.estado === 'Atraso'" class="text-warning font-weight-medium">+{{ item.atraso_min }} min</span>
            <span v-else class="text-disabled">—</span>
          </template>
          <template #item.permiso="{ item }">{{ item.permiso || '' }}</template>
          <template #bottom>
            <div class="pa-3 text-caption text-medium-emphasis">{{ repDia.dias.length }} trabajadores con horario</div>
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

      <VCard v-if="repSem" class="mb-4">
        <VCardTitle class="text-subtitle-1">Resumen por trabajador</VCardTitle>
        <VDataTable :headers="headersSem" :items="repSem.resumen" :items-per-page="25" density="compact">
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
            <div class="pa-3 text-caption text-medium-emphasis">{{ repSem.resumen.length }} trabajadores</div>
          </template>
        </VDataTable>
      </VCard>
    </div>
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

const headersDia = [
  { title: 'Trabajador', value: 'nombre' },
  { title: 'Sucursal', value: 'sucursal' },
  { title: 'Turno', value: 'turno' },
  { title: 'Esperada', value: 'esperada' },
  { title: 'Entrada real', value: 'real' },
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
  return { 'A tiempo': 'success', 'Atraso': 'warning', 'Ausente': 'error', 'Permiso': 'info' }[e] || 'default'
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

cargarDiario()
</script>
