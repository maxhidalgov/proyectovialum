<template>
  <div>
    <!-- Header + selector de período -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Estado de Resultados</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Ingresos, costos y utilidad por período</p>
      </VCol>
      <VCol cols="auto" class="d-flex align-center gap-2">
        <!-- Toggle Mensual / Anual -->
        <VBtnToggle v-model="modo" density="compact" variant="outlined" color="primary" mandatory divided>
          <VBtn value="mensual" size="small">Mensual</VBtn>
          <VBtn value="anual"   size="small">Anual</VBtn>
        </VBtnToggle>

        <!-- Selector de mes (modo mensual) -->
        <VTextField
          v-if="modo === 'mensual'"
          v-model="periodoMes"
          type="month"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 160px"
          @update:modelValue="cargar"
        />

        <!-- Selector de año (modo anual) -->
        <VSelect
          v-else
          v-model="periodoAnio"
          :items="aniosDisponibles"
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 110px"
          @update:modelValue="cargar"
        />

        <VBtn icon="mdi-refresh" variant="text" size="small" :loading="loading" @click="cargar" />
      </VCol>
    </VRow>

    <!-- Tarjetas resumen -->
    <VRow class="mb-5">
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <VIcon size="16" color="success">mdi-trending-up</VIcon>
            <span class="text-caption text-medium-emphasis">Ingresos</span>
          </div>
          <div class="text-h6 font-weight-bold text-success">{{ clp(data?.resultados?.ingresos) }}</div>
          <div class="text-caption text-medium-emphasis">{{ data?.ingresos?.cantidad || 0 }} documentos emitidos (Bsale)</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <VIcon size="16" color="error">mdi-trending-down</VIcon>
            <span class="text-caption text-medium-emphasis">Egresos</span>
          </div>
          <div class="text-h6 font-weight-bold text-error">{{ clp(data?.resultados?.total_egresos) }}</div>
          <div class="text-caption text-medium-emphasis">Compras + Gastos + Rem.</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <VIcon size="16" :color="utilidadColor">mdi-scale-balance</VIcon>
            <span class="text-caption text-medium-emphasis">Utilidad Operacional</span>
          </div>
          <div class="text-h6 font-weight-bold" :class="`text-${utilidadColor}`">{{ clp(data?.resultados?.utilidad_operacional) }}</div>
          <div class="text-caption" :class="`text-${utilidadColor}`">Margen {{ data?.resultados?.margen_operacional }}%</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <VIcon size="16" color="info">mdi-bank-check</VIcon>
            <span class="text-caption text-medium-emphasis">Cobrado (ref. caja)</span>
          </div>
          <div class="text-h6 font-weight-bold text-info">{{ clp(data?.ingresos?.cobrado) }}</div>
          <div class="text-caption text-medium-emphasis">Solo referencia — no afecta EERR</div>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <!-- Tabla EERR estructurada -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="pa-4 pb-2 text-subtitle-1 font-weight-bold">
            Estado de Resultados
            <span class="text-caption text-medium-emphasis font-weight-regular ml-2">{{ periodoLabel }}</span>
          </VCardTitle>
          <VCardText class="pa-0">
            <div v-if="loading" class="text-center py-8"><VProgressCircular indeterminate size="32" /></div>
            <template v-else-if="data">
              <!-- INGRESOS -->
              <div class="eerr-section eerr-header bg-success-subtle px-4 py-2">
                <div class="d-flex justify-space-between font-weight-bold">
                  <span>INGRESOS</span>
                  <span class="text-success">{{ clp(data.resultados.ingresos) }}</span>
                </div>
              </div>
              <!-- Bsale (SII) -->
              <div class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption pl-4">
                  Ventas / boletas SII
                  <span class="text-medium-emphasis">({{ data.ingresos.cantidad }} docs)</span>
                </span>
                <span class="text-caption">{{ clp(data.resultados.ingresos_bsale) }}</span>
              </div>
              <div class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption text-medium-emphasis pl-8">↳ Total bruto (con IVA)</span>
                <span class="text-caption text-medium-emphasis">{{ clp(data.ingresos.bruto) }}</span>
              </div>
              <div class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption text-medium-emphasis pl-8">↳ IVA débito fiscal</span>
                <span class="text-caption text-medium-emphasis">{{ clp(data.ingresos.iva) }}</span>
              </div>
              <!-- Ingresos manuales (sin doc SII) -->
              <div v-if="data.ingresos.manuales_total > 0" class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption pl-4 d-flex align-center gap-1">
                  <VIcon size="12" color="teal">mdi-receipt-text-plus</VIcon>
                  Ingresos sin doc SII
                  <span class="text-medium-emphasis">({{ data.ingresos.manuales_cantidad }})</span>
                  <RouterLink to="/ingresos-manuales" class="text-caption text-teal ml-1" style="text-decoration:none">ver →</RouterLink>
                </span>
                <span class="text-caption text-teal font-weight-medium">{{ clp(data.ingresos.manuales_total) }}</span>
              </div>
              <div v-if="data.ingresos.manuales_total > 0">
                <div v-for="cat in data.ingresos.manuales_por_categoria" :key="cat.categoria"
                  class="eerr-row px-4 py-1 d-flex justify-space-between">
                  <span class="text-caption text-medium-emphasis pl-8">↳ {{ cat.categoria }} ({{ cat.cantidad }})</span>
                  <span class="text-caption text-medium-emphasis">{{ clp(cat.total) }}</span>
                </div>
              </div>
              <div v-else class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption text-medium-emphasis pl-4 d-flex align-center gap-1">
                  <VIcon size="12" color="teal">mdi-receipt-text-plus</VIcon>
                  Ingresos sin doc SII
                  <RouterLink to="/ingresos-manuales" class="text-caption text-teal ml-1" style="text-decoration:none">ver →</RouterLink>
                </span>
                <span class="text-caption text-medium-emphasis">—</span>
              </div>

              <VDivider />

              <!-- COMPRAS -->
              <div class="eerr-section eerr-header bg-error-subtle px-4 py-2">
                <div class="d-flex justify-space-between font-weight-bold">
                  <span>COSTO DE VENTAS (Compras neto)</span>
                  <span class="text-error">({{ clp(data.resultados.compras) }})</span>
                </div>
              </div>
              <div v-if="data.compras.cantidad === 0" class="eerr-row px-4 py-1 text-caption text-medium-emphasis pl-8">Sin compras en el período</div>
              <div v-for="p in data.compras.proveedores" :key="p.nombre_emisor" class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption pl-4 text-truncate" style="max-width:200px">{{ p.nombre_emisor || '—' }} <span class="text-medium-emphasis">({{ p.cantidad }})</span></span>
                <span class="text-caption">{{ clp(p.total_neto) }}</span>
              </div>

              <VDivider />

              <!-- UTILIDAD BRUTA -->
              <div class="eerr-section px-4 py-2 d-flex justify-space-between font-weight-bold bg-surface-variant">
                <span>UTILIDAD BRUTA</span>
                <span :class="`text-${data.resultados.utilidad_bruta >= 0 ? 'success' : 'error'}`">
                  {{ clp(data.resultados.utilidad_bruta) }}
                  <span class="text-caption ml-1">({{ data.resultados.margen_bruto }}%)</span>
                </span>
              </div>

              <VDivider />

              <!-- GASTOS -->
              <div class="eerr-section eerr-header bg-error-subtle px-4 py-2">
                <div class="d-flex justify-space-between font-weight-bold">
                  <span>GASTOS OPERACIONALES</span>
                  <span class="text-error">({{ clp(data.resultados.gastos) }})</span>
                </div>
              </div>
              <div v-if="data.gastos.cantidad === 0" class="eerr-row px-4 py-1 text-caption text-medium-emphasis pl-8">Sin gastos en el período</div>
              <div v-for="g in data.gastos.por_categoria" :key="g.categoria" class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption pl-4">{{ g.categoria }} <span class="text-medium-emphasis">({{ g.cantidad }})</span></span>
                <span class="text-caption">{{ clp(g.total) }}</span>
              </div>

              <VDivider />

              <!-- REMUNERACIONES -->
              <div class="eerr-section eerr-header bg-error-subtle px-4 py-2">
                <div class="d-flex justify-space-between font-weight-bold">
                  <span>REMUNERACIONES</span>
                  <span class="text-error">({{ clp(data.resultados.remuneraciones) }})</span>
                </div>
              </div>
              <div v-if="data.remuneraciones.cantidad === 0" class="eerr-row px-4 py-1 text-caption text-medium-emphasis pl-8">Sin pagos en el período</div>
              <div v-for="r in data.remuneraciones.por_tipo" :key="r.tipo" class="eerr-row px-4 py-1 d-flex justify-space-between">
                <span class="text-caption pl-4 text-capitalize">{{ r.tipo }} <span class="text-medium-emphasis">({{ r.cantidad }})</span></span>
                <span class="text-caption">{{ clp(r.total) }}</span>
              </div>

              <VDivider />

              <!-- UTILIDAD OPERACIONAL -->
              <div class="px-4 py-3 d-flex justify-space-between font-weight-bold text-body-1" :class="data.resultados.utilidad_operacional >= 0 ? 'bg-success-subtle' : 'bg-error-subtle'">
                <span>UTILIDAD OPERACIONAL</span>
                <span :class="`text-${data.resultados.utilidad_operacional >= 0 ? 'success' : 'error'}`">
                  {{ clp(data.resultados.utilidad_operacional) }}
                  <span class="text-caption ml-1">({{ data.resultados.margen_operacional }}%)</span>
                </span>
              </div>
            </template>
            <div v-else class="text-center text-medium-emphasis py-8">Sin datos</div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gráfico histórico -->
      <VCol cols="12" md="6">
        <VCard class="h-100">
          <VCardTitle class="pa-4 pb-2 text-subtitle-1 font-weight-bold">
            {{ modo === 'anual' ? `Mensual ${periodoAnio}` : 'Tendencia — últimos 6 meses' }}
          </VCardTitle>
          <VCardText>
            <VueApexCharts
              v-if="chartOptions && chartSeries"
              type="bar"
              height="340"
              :options="chartOptions"
              :series="chartSeries"
            />
            <div v-else class="d-flex align-center justify-center" style="height:340px">
              <VProgressCircular indeterminate />
            </div>
          </VCardText>
        </VCard>

        <!-- Mini waterfall de egresos -->
        <VCard class="mt-4">
          <VCardTitle class="pa-4 pb-2 text-subtitle-1 font-weight-bold">Composición de egresos</VCardTitle>
          <VCardText>
            <template v-if="data">
              <div v-for="item in egresosItems" :key="item.label" class="mb-3">
                <div class="d-flex justify-space-between text-caption mb-1">
                  <span>{{ item.label }}</span>
                  <span class="font-weight-medium">{{ clp(item.monto) }} <span class="text-medium-emphasis">({{ item.pct }}%)</span></span>
                </div>
                <VProgressLinear :model-value="item.pct" :color="item.color" bg-color="grey-lighten-3" rounded height="6" />
              </div>
              <VDivider class="my-2" />
              <div class="d-flex justify-space-between text-caption font-weight-bold">
                <span>Total egresos</span>
                <span class="text-error">{{ clp(data.resultados.total_egresos) }}</span>
              </div>
            </template>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/axiosInstance'

// ── Estado ───────────────────────────────────────────────────────
const loading    = ref(false)
const data       = ref(null)
const modo       = ref('mensual')                                   // 'mensual' | 'anual'
const periodoMes = ref(new Date().toISOString().slice(0, 7))       // YYYY-MM
const periodoAnio = ref(new Date().getFullYear())                   // número

const hoy = new Date()
const aniosDisponibles = Array.from({ length: 5 }, (_, i) => hoy.getFullYear() - i)

// Recargar al cambiar modo
watch(modo, cargar)

// ── Computed ─────────────────────────────────────────────────────
const utilidadColor = computed(() => {
  const u = data.value?.resultados?.utilidad_operacional ?? 0
  return u >= 0 ? 'success' : 'error'
})

const periodoLabel = computed(() => {
  if (modo.value === 'anual') return `Año ${periodoAnio.value}`
  if (!periodoMes.value) return ''
  const [y, m] = periodoMes.value.split('-')
  return new Date(y, m - 1, 1).toLocaleDateString('es-CL', { month: 'long', year: 'numeric' })
})

const egresosItems = computed(() => {
  if (!data.value) return []
  const total = data.value.resultados.total_egresos || 1
  return [
    { label: 'Compras (neto)', monto: data.value.resultados.compras,       color: 'error',   pct: Math.round(data.value.resultados.compras       / total * 100) },
    { label: 'Gastos generales', monto: data.value.resultados.gastos,      color: 'warning', pct: Math.round(data.value.resultados.gastos         / total * 100) },
    { label: 'Remuneraciones',  monto: data.value.resultados.remuneraciones, color: 'deep-purple', pct: Math.round(data.value.resultados.remuneraciones / total * 100) },
  ]
})

// ── Gráfico ───────────────────────────────────────────────────────
const chartSeries = computed(() => {
  if (!data.value?.historico) return null
  return [
    { name: 'Ingresos',  data: data.value.historico.map(h => Math.round(h.ingresos)) },
    { name: 'Egresos',   data: data.value.historico.map(h => Math.round(h.egresos))  },
    { name: 'Utilidad',  data: data.value.historico.map(h => Math.round(h.utilidad)) },
  ]
})

const chartOptions = computed(() => {
  if (!data.value?.historico) return null
  const categorias = data.value.historico.map(h => {
    const [y, m] = h.periodo.split('-')
    return new Date(y, m - 1, 1).toLocaleDateString('es-CL', { month: 'short', year: '2-digit' })
  })
  return {
    chart: { toolbar: { show: false }, stacked: false },
    colors: ['#28a745', '#dc3545', '#007bff'],
    plotOptions: {
      bar: { borderRadius: 4, columnWidth: '55%' },
    },
    dataLabels: { enabled: false },
    xaxis: { categories: categorias },
    yaxis: {
      labels: {
        formatter: v => {
          if (Math.abs(v) >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`
          if (Math.abs(v) >= 1_000)    return `$${(v / 1_000).toFixed(0)}k`
          return `$${v}`
        },
      },
    },
    tooltip: {
      y: { formatter: v => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v) },
    },
    legend: { position: 'top' },
    stroke: {
      width: [0, 0, 2],
      curve: 'smooth',
    },
    markers: { size: [0, 0, 4] },
  }
})

// ── Carga ─────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    let params
    if (modo.value === 'anual') {
      params = { modo: 'anual', anio: periodoAnio.value }
    } else {
      const [anio, mes] = periodoMes.value.split('-')
      params = { modo: 'mensual', mes: parseInt(mes), anio }
    }
    const { data: res } = await api.get('/api/eerr', { params })
    data.value = res
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────
const clp = n => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)

function labelTipoDoc(tipo) {
  return { anticipo: 'Anticipo', saldo: 'Saldo', total: 'Factura total' }[tipo] ?? tipo
}

onMounted(cargar)
</script>

<style scoped>
.eerr-section {
  border-left: 3px solid transparent;
}
.eerr-header {
  border-left-color: currentColor;
}
.eerr-row {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06);
  min-height: 32px;
  display: flex;
  align-items: center;
}
</style>
