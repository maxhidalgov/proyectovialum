<template>
  <VContainer fluid class="pa-4">
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
      <div class="d-flex align-center gap-3">
        <VIcon icon="mdi-chart-bar" size="32" color="primary" />
        <div>
          <h1 class="text-h5 font-weight-bold">m² fabricados por mes</h1>
          <p class="text-caption text-grey mt-1">
            Un trabajo cuenta como fabricado el mes en que pasa a "Fabricadas OK" (o a "Instalada") y como instalado el mes en que pasa a "Instalada"
          </p>
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VSelect
          v-model="nMeses" :items="[6, 12, 24]" density="compact" variant="outlined" hide-details
          style="width: 120px" suffix="meses" @update:modelValue="cargar"
        />
        <VBtn variant="tonal" size="small" prepend-icon="mdi-arrow-left" @click="router.push({ name: 'produccion' })">Taller</VBtn>
      </div>
    </div>

    <div v-if="cargando" class="text-center py-10"><VProgressCircular indeterminate color="primary" /></div>

    <template v-else-if="data">
      <VRow class="mb-2" dense>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="primary" class="pa-3 text-center">
            <div class="text-h5 font-weight-bold">{{ fmt(mesActual?.fabricado_m2) }} m²</div>
            <div class="text-caption">Fabricados este mes ({{ mesActual?.fabricado_obras ?? 0 }} obras)</div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="success" class="pa-3 text-center">
            <div class="text-h5 font-weight-bold">{{ data.prom_fabricado != null ? fmt(data.prom_fabricado) + ' m²' : '—' }}</div>
            <div class="text-caption">Promedio fabricado, últimos 3 meses completos</div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="info" class="pa-3 text-center">
            <div class="text-h5 font-weight-bold">{{ fmt(mesActual?.instalado_m2) }} m²</div>
            <div class="text-caption">Instalados este mes ({{ mesActual?.instalado_obras ?? 0 }} obras)</div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="warning" class="pa-3 text-center">
            <div class="text-h5 font-weight-bold">{{ fmt(data.en_proceso.m2) }} m²</div>
            <div class="text-caption">En proceso ahora ({{ data.en_proceso.obras }} obras)</div>
          </VCard>
        </VCol>
      </VRow>

      <VAlert v-if="data.sin_fecha.obras" type="warning" variant="tonal" density="compact" class="mb-4 text-caption">
        {{ data.sin_fecha.obras }} obra(s) ({{ fmt(data.sin_fecha.m2) }} m²) están en "Fabricadas OK" o "Instalada" pero no tienen fecha en el historial, por eso no aparecen en ningún mes.
        Se pueden agregar desde Operaciones → Tiempos → agregar hito.
      </VAlert>

      <VAlert v-if="data.sin_inicio" type="info" variant="tonal" density="compact" class="mb-4 text-caption">
        {{ data.sin_inicio }} obra(s) de este período no tienen fecha de "En Fabricación" (pasaron directo a Fabricadas OK), así que no entran en el tiempo de fabricación ni en la cola de corte.
        Marca "En Fabricación" el día que empieza cada obra para medirlo.
      </VAlert>

      <!-- Cuánto llevan hoy en cada estado -->
      <div class="text-subtitle-1 font-weight-bold mb-2">Cómo está el flujo hoy</div>
      <VRow class="mb-4" dense>
        <VCol v-for="p in data.pendientes" :key="p.estado" cols="12" md="4">
          <VCard variant="outlined" class="pa-3 h-100">
            <div class="d-flex justify-space-between align-center">
              <div class="font-weight-bold">{{ p.estado }}</div>
              <VChip size="small" label :color="colorAtraso(p)" variant="tonal">{{ p.dias_prom != null ? 'prom. ' + fmt(p.dias_prom) + ' días' : 'sin fechas' }}</VChip>
            </div>
            <div class="text-caption text-medium-emphasis mb-2">{{ textoEstado(p.estado) }}</div>
            <div class="text-h6 font-weight-bold">{{ p.obras }} obras · {{ fmt(p.m2) }} m²</div>
            <div v-if="p.dias_max != null" class="text-caption">La más antigua lleva {{ p.dias_max }} días</div>
            <VTable v-if="p.items.length" density="compact" class="mt-2">
              <tbody>
                <tr v-for="o in p.items" :key="o.id">
                  <td class="text-caption">#{{ o.id }} {{ o.cliente }}</td>
                  <td class="text-right text-caption text-no-wrap">{{ fmt(o.m2) }} m²</td>
                  <td class="text-right text-caption text-no-wrap font-weight-bold">{{ o.dias != null ? o.dias + ' d' : '—' }}</td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tiempos promedio del período -->
      <div class="text-subtitle-1 font-weight-bold mb-2">Tiempos promedio entre estados</div>
      <VRow class="mb-4" dense>
        <VCol v-for="t in tramosInfo" :key="t.k" cols="12" md="4">
          <VCard variant="tonal" :color="t.color" class="pa-3">
            <div class="text-caption">{{ t.titulo }}</div>
            <div class="text-h5 font-weight-bold">{{ data.prom_tiempos[t.k].dias_prom != null ? fmt(data.prom_tiempos[t.k].dias_prom) + ' días' : '—' }}</div>
            <div class="text-caption">{{ t.ayuda }} · {{ data.prom_tiempos[t.k].n }} obras con ambas fechas</div>
          </VCard>
        </VCol>
      </VRow>

      <VCard class="pa-4 mb-4">
        <VueApexCharts type="bar" height="300" :options="chartOpts" :series="series" />
      </VCard>

      <VCard>
        <VTable density="compact">
          <thead>
            <tr>
              <th>Mes</th>
              <th class="text-right">m² fabricados</th><th class="text-right">Obras</th><th class="text-right">Ventanas</th>
              <th class="text-right">m² instalados</th><th class="text-right">Obras</th>
              <th class="text-right">Cola de corte (días)</th><th class="text-right">Fabricación (días)</th>
              <th class="text-right">m²/día fab.</th><th class="text-right">Espera instalar (días)</th><th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="m in mesesDesc" :key="m.mes">
              <tr>
                <td class="font-weight-bold">{{ etiqueta(m.mes) }}</td>
                <td class="text-right font-weight-bold">{{ fmt(m.fabricado_m2) }}</td>
                <td class="text-right">{{ m.fabricado_obras }}</td>
                <td class="text-right">{{ m.fabricado_ventanas }}</td>
                <td class="text-right">{{ fmt(m.instalado_m2) }}</td>
                <td class="text-right">{{ m.instalado_obras }}</td>
                <td class="text-right">{{ dias(m.tiempos.cola) }}</td>
                <td class="text-right">{{ dias(m.tiempos.fabrica) }}</td>
                <td class="text-right">{{ m.tiempos.fabrica.m2_por_dia != null ? fmt(m.tiempos.fabrica.m2_por_dia) : '—' }}</td>
                <td class="text-right">{{ dias(m.tiempos.espera) }}</td>
                <td class="text-right">
                  <VBtn v-if="m.obras.length" size="x-small" variant="text" :icon="abierto === m.mes ? 'mdi-chevron-up' : 'mdi-chevron-down'" @click="abierto = abierto === m.mes ? null : m.mes" />
                </td>
              </tr>
              <tr v-if="abierto === m.mes">
                <td colspan="11" class="bg-surface-variant">
                  <VTable density="compact" class="bg-transparent">
                    <tbody>
                      <tr v-for="o in m.obras" :key="o.id + o.tipo">
                        <td class="text-caption text-no-wrap">{{ o.fecha }}</td>
                        <td><VChip size="x-small" label :color="o.tipo === 'fabricado' ? 'primary' : 'info'" variant="tonal">{{ o.tipo }}</VChip></td>
                        <td class="text-caption">#{{ o.id }} {{ o.cliente }}</td>
                        <td class="text-right text-caption">{{ fmt(o.m2) }} m² · {{ o.ventanas }} ventanas</td>
                      </tr>
                    </tbody>
                  </VTable>
                </td>
              </tr>
            </template>
          </tbody>
        </VTable>
      </VCard>

      <p class="text-caption text-medium-emphasis mt-3">
        m² = ancho × alto × cantidad de cada ventana. Si varias obras se marcaron el mismo día (por ejemplo al poner al día el sistema), caen en el mismo mes: revisa las fechas en Operaciones → Tiempos.
      </p>
    </template>
  </VContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/axiosInstance'
import VueApexCharts from 'vue3-apexcharts'

const router = useRouter()
const data = ref(null)
const cargando = ref(false)
const nMeses = ref(12)
const abierto = ref(null)

const fmt = n => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 1 }).format(Number(n) || 0)
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']
const etiqueta = k => `${MESES[Number(k.slice(5, 7)) - 1]} ${k.slice(0, 4)}`

const dias = t => (t?.dias_prom != null ? fmt(t.dias_prom) : '—')
const tramosInfo = [
  { k: 'cola', titulo: 'Cola de corte: Lista para Corte → En Fabricación', color: 'warning', ayuda: 'Si sube, el taller va atrasado' },
  { k: 'fabrica', titulo: 'Fabricación: En Fabricación → Fabricadas OK', color: 'primary', ayuda: 'Trabajo del taller' },
  { k: 'espera', titulo: 'Espera: Fabricadas OK → Instalada', color: 'info', ayuda: 'Si sube, faltan instaladores o coordinación' },
]
const textoEstado = e => ({
  'Lista para Corte': 'Medidas listas, esperando entrar al taller. Si hay muchas y llevan días, vas atrasado en fabricación.',
  'En Fabricación': 'Se está cortando o armando.',
  'Fabricadas OK': 'Terminadas, esperando instalación. Si se acumulan, faltan instaladores o coordinación.',
}[e] ?? '')
const colorAtraso = p => (p.dias_prom == null ? 'default' : p.dias_prom > 14 ? 'error' : p.dias_prom > 7 ? 'warning' : 'success')

const mesesDesc = computed(() => [...(data.value?.meses ?? [])].reverse())
const mesActual = computed(() => data.value?.meses?.[data.value.meses.length - 1])

const series = computed(() => [
  { name: 'Fabricados (m²)', data: (data.value?.meses ?? []).map(m => m.fabricado_m2) },
  { name: 'Instalados (m²)', data: (data.value?.meses ?? []).map(m => m.instalado_m2) },
])
const chartOpts = computed(() => ({
  chart: { toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: (data.value?.meses ?? []).map(m => etiqueta(m.mes)) },
  yaxis: { title: { text: 'm²' } },
  legend: { position: 'top' },
}))

async function cargar() {
  cargando.value = true
  try {
    const { data: d } = await axios.get('/api/produccion/m2-mensual', { params: { meses: nMeses.value } })
    data.value = d
  } catch (e) { console.error(e) }
  finally { cargando.value = false }
}

onMounted(cargar)
</script>
