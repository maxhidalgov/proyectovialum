<template>
  <VContainer fluid class="pa-4">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-3">
      <div>
        <h1 class="text-h5 font-weight-bold">Compras Mensuales</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">Análisis de facturas de compra recibidas</p>
      </div>
      <div class="d-flex align-center gap-2">
        <VSelect
          v-model="mes"
          :items="meses"
          item-title="title"
          item-value="value"
          hide-details
          variant="outlined"
          density="compact"
          style="width: 140px"
        />
        <VSelect
          v-model="anio"
          :items="anios"
          hide-details
          variant="outlined"
          density="compact"
          style="width: 100px"
        />
        <VBtn color="primary" :loading="loading" @click="cargar">
          Consultar
        </VBtn>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <VProgressCircular indeterminate color="primary" size="48" />
    </div>

    <template v-else-if="data">
      <!-- KPI Cards -->
      <VRow class="mb-4">
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="primary" class="pa-4">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-medium-emphasis mb-1">Total Neto</div>
                <div class="text-h6 font-weight-bold">${{ fmt(data.kpis.total_neto) }}</div>
              </div>
              <VIcon size="36" color="primary">mdi-cash-multiple</VIcon>
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="success" class="pa-4">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-medium-emphasis mb-1">Total c/IVA</div>
                <div class="text-h6 font-weight-bold">${{ fmt(data.kpis.total_bruto) }}</div>
              </div>
              <VIcon size="36" color="success">mdi-receipt</VIcon>
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="warning" class="pa-4">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-medium-emphasis mb-1">N° Facturas</div>
                <div class="text-h6 font-weight-bold">{{ data.kpis.cantidad }}</div>
              </div>
              <VIcon size="36" color="warning">mdi-file-document-multiple</VIcon>
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="info" class="pa-4">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-medium-emphasis mb-1">Promedio / Factura</div>
                <div class="text-h6 font-weight-bold">${{ fmt(data.kpis.promedio) }}</div>
              </div>
              <VIcon size="36" color="info">mdi-calculator-variant</VIcon>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Charts row 1 -->
      <VRow class="mb-4">
        <!-- Barras por día -->
        <VCol cols="12" md="8">
          <VCard class="pa-4">
            <div class="text-subtitle-1 font-weight-bold mb-3">Compras por día</div>
            <VueApexCharts
              type="bar"
              height="260"
              :options="chartDiarioOpts"
              :series="chartDiarioSeries"
            />
          </VCard>
        </VCol>

        <!-- Donut proveedores -->
        <VCol cols="12" md="4">
          <VCard class="pa-4">
            <div class="text-subtitle-1 font-weight-bold mb-3">Top proveedores</div>
            <VueApexCharts
              type="donut"
              height="260"
              :options="chartDonutOpts"
              :series="chartDonutSeries"
            />
          </VCard>
        </VCol>
      </VRow>

      <!-- Línea evolución 12 meses -->
      <VCard class="pa-4 mb-4">
        <div class="text-subtitle-1 font-weight-bold mb-3">Evolución últimos 12 meses (neto)</div>
        <VueApexCharts
          type="area"
          height="220"
          :options="chartEvolucionOpts"
          :series="chartEvolucionSeries"
        />
      </VCard>

      <!-- Tabla proveedores -->
      <VCard>
        <VCardTitle class="pa-4 pb-2">Resumen por Proveedor</VCardTitle>
        <VTable density="compact">
          <thead>
            <tr>
              <th>#</th>
              <th>Proveedor</th>
              <th class="text-right">Facturas</th>
              <th class="text-right">Total Neto</th>
              <th>% del mes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in data.proveedores" :key="i">
              <td class="text-medium-emphasis">{{ i + 1 }}</td>
              <td class="font-weight-medium">{{ p.nombre }}</td>
              <td class="text-right">{{ p.cantidad }}</td>
              <td class="text-right font-weight-medium">${{ fmt(p.total) }}</td>
              <td style="min-width:160px">
                <div class="d-flex align-center gap-2">
                  <VProgressLinear
                    :model-value="p.porcentaje"
                    color="primary"
                    height="6"
                    rounded
                    style="flex:1"
                  />
                  <span class="text-caption" style="width:36px">{{ p.porcentaje }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
        <div v-if="!data.proveedores?.length" class="text-center py-8 text-medium-emphasis">
          Sin datos para el período seleccionado
        </div>
      </VCard>
    </template>

    <div v-else class="text-center py-12 text-medium-emphasis">
      <VIcon size="64" class="mb-3">mdi-chart-bar</VIcon>
      <div>Selecciona un mes y año para ver el análisis</div>
    </div>
  </VContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/axiosInstance'

const mes    = ref(new Date().getMonth() + 1)
const anio   = ref(new Date().getFullYear())
const loading = ref(false)
const data    = ref(null)

const meses = [
  { title: 'Enero', value: 1 }, { title: 'Febrero', value: 2 },
  { title: 'Marzo', value: 3 }, { title: 'Abril', value: 4 },
  { title: 'Mayo', value: 5 },  { title: 'Junio', value: 6 },
  { title: 'Julio', value: 7 }, { title: 'Agosto', value: 8 },
  { title: 'Septiembre', value: 9 }, { title: 'Octubre', value: 10 },
  { title: 'Noviembre', value: 11 }, { title: 'Diciembre', value: 12 },
]
const anios = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const fmt = n => Number(n ?? 0).toLocaleString('es-CL')

async function cargar() {
  loading.value = true
  try {
    const { data: res } = await api.get('/api/compras/estadisticas', {
      params: { mes: mes.value, anio: anio.value },
    })
    data.value = res
  } catch (e) {
    console.error('Error cargando estadísticas', e)
  } finally {
    loading.value = false
  }
}

// Color de texto adaptado al tema (claro en dark, oscuro en light)
const labelColor  = '#a8aaae'
const gridColor   = 'rgba(255,255,255,0.07)'

// ── Chart: barras diarias ──────────────────────────────────────────────────
const chartDiarioOpts = computed(() => ({
  chart: { toolbar: { show: false }, animations: { enabled: true }, foreColor: labelColor },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: data.value?.diario?.labels ?? [],
    labels: { style: { fontSize: '11px', colors: labelColor } },
    title: { text: 'Día', style: { color: labelColor } },
    axisBorder: { color: gridColor },
    axisTicks:  { color: gridColor },
  },
  yaxis: {
    labels: {
      style: { colors: labelColor },
      formatter: v => '$' + Number(v).toLocaleString('es-CL'),
    },
  },
  tooltip: {
    theme: 'dark',
    y: { formatter: v => '$' + Number(v).toLocaleString('es-CL') },
  },
  colors: ['#7367f0'],
  grid: { borderColor: gridColor },
}))

const chartDiarioSeries = computed(() => [
  { name: 'Neto', data: data.value?.diario?.neto ?? [] },
])

// ── Chart: donut proveedores ───────────────────────────────────────────────
const chartDonutOpts = computed(() => ({
  chart: { animations: { enabled: true }, foreColor: labelColor },
  labels: data.value?.proveedores?.map(p => p.nombre) ?? [],
  legend: {
    position: 'bottom',
    fontSize: '11px',
    labels: { colors: labelColor },
  },
  dataLabels: { enabled: false },
  tooltip: {
    theme: 'dark',
    y: { formatter: v => '$' + Number(v).toLocaleString('es-CL') },
  },
  plotOptions: {
    pie: { donut: { size: '65%', labels: {
      show: true,
      name:  { color: labelColor },
      value: { color: labelColor, formatter: v => '$' + Number(v).toLocaleString('es-CL') },
      total: {
        show: true,
        label: 'Total',
        color: labelColor,
        formatter: () => '$' + fmt(data.value?.kpis?.total_neto ?? 0),
      },
    }}},
  },
  colors: ['#7367f0','#28c76f','#ff9f43','#00cfe8','#ea5455','#6f42c1','#20c997','#fd7e14'],
}))

const chartDonutSeries = computed(() =>
  data.value?.proveedores?.map(p => p.total) ?? []
)

// ── Chart: área evolución ──────────────────────────────────────────────────
const chartEvolucionOpts = computed(() => ({
  chart: { toolbar: { show: false }, animations: { enabled: true }, foreColor: labelColor },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  xaxis: {
    categories: data.value?.evolucion?.labels ?? [],
    labels: { style: { fontSize: '11px', colors: labelColor } },
    axisBorder: { color: gridColor },
    axisTicks:  { color: gridColor },
  },
  yaxis: {
    labels: {
      style: { colors: labelColor },
      formatter: v => '$' + Number(v / 1000000).toFixed(1) + 'M',
    },
  },
  tooltip: {
    theme: 'dark',
    y: { formatter: v => '$' + Number(v).toLocaleString('es-CL') },
  },
  colors: ['#28c76f'],
  grid: { borderColor: gridColor },
  markers: { size: 4 },
}))

const chartEvolucionSeries = computed(() => [
  { name: 'Neto', data: data.value?.evolucion?.neto ?? [] },
])

onMounted(cargar)
</script>
