<template>
  <div class="pa-4">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-5">
      <div>
        <h5 class="text-h5 font-weight-bold">Dashboard Financiero</h5>
        <p class="text-medium-emphasis text-body-2 mb-0">Resumen en tiempo real · valores con IVA salvo que se indique</p>
      </div>
      <v-btn variant="tonal" color="primary" size="small" :loading="cargando" @click="cargar">
        <v-icon start size="16">mdi-refresh</v-icon>Actualizar
      </v-btn>
    </div>

    <!-- Error de carga -->
    <v-alert v-if="errorMsg" type="error" variant="tonal" class="mb-5" closable @click:close="errorMsg = null">
      <strong>Error al cargar el dashboard:</strong> {{ errorMsg }}
    </v-alert>

    <!-- ── KPI cards ────────────────────────────────────────────────────── -->
    <v-row class="mb-5" dense>
      <!-- Por Cobrar -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="lg" variant="flat" class="kpi-card border-s-4" style="border-left-color: #56CA00 !important">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption text-medium-emphasis font-weight-medium text-uppercase">Por Cobrar</span>
              <v-avatar color="success" variant="tonal" size="38">
                <v-icon size="20">mdi-cash-multiple</v-icon>
              </v-avatar>
            </div>
            <div v-if="cargando" class="skeleton-text" style="height:32px;width:70%;border-radius:4px" />
            <p v-else class="text-h5 font-weight-bold mb-0 text-success">{{ clp(data.kpis.por_cobrar) }}</p>
            <p class="text-caption text-medium-emphasis mt-1 mb-0">Facturas emitidas pendientes de cobro</p>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Por Pagar -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="lg" variant="flat" class="kpi-card border-s-4" style="border-left-color: #FF4C51 !important">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption text-medium-emphasis font-weight-medium text-uppercase">Por Pagar</span>
              <v-avatar color="error" variant="tonal" size="38">
                <v-icon size="20">mdi-credit-card-outline</v-icon>
              </v-avatar>
            </div>
            <div v-if="cargando" class="skeleton-text" style="height:32px;width:70%;border-radius:4px" />
            <p v-else class="text-h5 font-weight-bold mb-0 text-error">{{ clp(data.kpis.por_pagar) }}</p>
            <p class="text-caption text-medium-emphasis mt-1 mb-0">Facturas de compra pendientes de pago</p>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Saldo Cuenta -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="lg" variant="flat" class="kpi-card border-s-4" style="border-left-color: #16B1FF !important">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption text-medium-emphasis font-weight-medium text-uppercase">Saldo Cta. Corriente</span>
              <v-avatar color="info" variant="tonal" size="38">
                <v-icon size="20">mdi-bank-outline</v-icon>
              </v-avatar>
            </div>
            <div v-if="cargando" class="skeleton-text" style="height:32px;width:70%;border-radius:4px" />
            <p v-else class="text-h5 font-weight-bold mb-0" :class="data.kpis.saldo_cta_corriente >= 0 ? 'text-info' : 'text-error'">
              {{ clp(data.kpis.saldo_cta_corriente) }}
            </p>
            <p class="text-caption text-medium-emphasis mt-1 mb-0">
              <template v-if="data.kpis.saldo_cta_corriente_fecha">
                Al {{ fmtFecha(data.kpis.saldo_cta_corriente_fecha) }}
              </template>
              <template v-else>Último saldo disponible en banco</template>
            </p>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Promedio días cobro -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="lg" variant="flat" class="kpi-card border-s-4" style="border-left-color: #FFB400 !important">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption text-medium-emphasis font-weight-medium text-uppercase">Promedio cobro</span>
              <v-avatar color="warning" variant="tonal" size="38">
                <v-icon size="20">mdi-clock-outline</v-icon>
              </v-avatar>
            </div>
            <div v-if="cargando" class="skeleton-text" style="height:32px;width:50%;border-radius:4px" />
            <p v-else class="text-h5 font-weight-bold mb-0 text-warning">{{ data.kpis.promedio_dias_cobro }} <span class="text-body-1">días</span></p>
            <p class="text-caption text-medium-emphasis mt-1 mb-0">Entre emisión y primer pago recibido</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Fila principal ────────────────────────────────────────────────── -->
    <v-row class="mb-5" dense>

      <!-- Top 5 clientes por cobrar -->
      <v-col cols="12" md="5">
        <v-card rounded="lg" variant="flat" height="100%">
          <v-card-title class="pa-5 pb-3 d-flex align-center">
            <v-icon color="success" class="mr-2" size="20">mdi-account-cash</v-icon>
            Top clientes por cobrar
          </v-card-title>
          <v-card-text class="pa-0">
            <div v-if="cargando" class="pa-5">
              <v-skeleton-loader v-for="i in 5" :key="i" type="list-item-two-line" class="mb-1" />
            </div>
            <v-table v-else density="compact">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th class="text-right">Docs</th>
                  <th class="text-right">Pendiente</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(cl, i) in data.top_clientes" :key="i">
                  <td>
                    <div class="d-flex align-center gap-2 py-1">
                      <v-avatar color="success" variant="tonal" size="28">
                        <span class="text-caption font-weight-bold">{{ iniciales(cl.nombre) }}</span>
                      </v-avatar>
                      <div>
                        <p class="text-body-2 font-weight-medium mb-0" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ cl.nombre }}</p>
                        <p class="text-caption text-medium-emphasis mb-0">{{ cl.rut || '—' }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="text-right text-caption">{{ cl.documentos }}</td>
                  <td class="text-right font-weight-bold text-success">{{ clp(cl.pendiente) }}</td>
                </tr>
                <tr v-if="!data.top_clientes.length">
                  <td colspan="3" class="text-center text-caption text-medium-emphasis py-6">
                    Sin clientes con saldo pendiente
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Flujo de Caja -->
      <v-col cols="12" md="7">
        <v-card rounded="lg" variant="flat" height="100%">
          <v-card-title class="pa-5 pb-3 d-flex align-center">
            <v-icon color="info" class="mr-2" size="20">mdi-chart-bar</v-icon>
            Flujo de Caja
            <span class="text-caption text-medium-emphasis ml-2 font-weight-regular">últimos 12 meses · movimientos bancarios reales</span>
          </v-card-title>
          <v-card-text class="pa-3">
            <div v-if="cargando" class="d-flex align-center justify-center" style="height:240px">
              <v-progress-circular indeterminate color="info" />
            </div>
            <VueApexCharts
              v-else
              type="bar"
              height="250"
              :options="chartOpts"
              :series="chartSeries"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Resultado Operacional ─────────────────────────────────────────── -->
    <v-card rounded="lg" variant="flat">
      <v-card-title class="pa-5 pb-3 d-flex align-center">
        <v-icon color="primary" class="mr-2" size="20">mdi-table-large</v-icon>
        Resultado Operacional
        <span class="text-caption text-medium-emphasis ml-2 font-weight-regular">valores sin IVA</span>
      </v-card-title>
      <v-card-text class="pa-0">
        <div v-if="cargando" class="pa-5">
          <v-skeleton-loader type="table-row-divider@5" />
        </div>
        <v-table v-else density="comfortable">
          <thead>
            <tr>
              <th style="min-width:160px">Concepto</th>
              <th v-for="m in data.resultado_operacional" :key="m.mes" class="text-right">
                {{ fmtMes(m.mes) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-caption font-weight-medium">Ingresos (ventas netas)</td>
              <td v-for="m in data.resultado_operacional" :key="m.mes" class="text-right text-caption text-success">
                {{ clp(m.ingresos) }}
              </td>
            </tr>
            <tr>
              <td class="text-caption pl-4 text-medium-emphasis">— Compras / materiales</td>
              <td v-for="m in data.resultado_operacional" :key="m.mes" class="text-right text-caption text-error">
                {{ m.compras > 0 ? '(' + clp(m.compras) + ')' : '—' }}
              </td>
            </tr>
            <tr>
              <td class="text-caption pl-4 text-medium-emphasis">— Gastos operacionales</td>
              <td v-for="m in data.resultado_operacional" :key="m.mes" class="text-right text-caption text-error">
                {{ m.gastos > 0 ? '(' + clp(m.gastos) + ')' : '—' }}
              </td>
            </tr>
            <tr>
              <td class="text-caption pl-4 text-medium-emphasis">— Remuneraciones</td>
              <td v-for="m in data.resultado_operacional" :key="m.mes" class="text-right text-caption text-error">
                {{ m.remuneraciones > 0 ? '(' + clp(m.remuneraciones) + ')' : '—' }}
              </td>
            </tr>
            <tr class="resultado-row">
              <td class="text-body-2 font-weight-bold">Resultado Operacional</td>
              <td
                v-for="m in data.resultado_operacional"
                :key="m.mes"
                class="text-right font-weight-bold text-body-2"
                :class="m.resultado >= 0 ? 'text-success' : 'text-error'"
              >
                {{ clp(m.resultado) }}
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/axiosInstance'

// ── Estado ──────────────────────────────────────────────────────────────
const cargando  = ref(true)
const errorMsg  = ref(null)
const data = ref({
  kpis: { por_cobrar: 0, por_pagar: 0, saldo_cta_corriente: 0, promedio_dias_cobro: 0 },
  top_clientes: [],
  flujo_caja: [],
  resultado_operacional: [],
})

// ── Carga ────────────────────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  errorMsg.value = null
  try {
    const { data: res } = await api.get('/api/dashboard-financiero')
    data.value = res
  } catch (e) {
    console.error('Error cargando dashboard financiero', e)
    errorMsg.value = e?.response?.data?.message || e?.message || 'Error desconocido al cargar el dashboard'
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Gráfico Flujo de Caja ────────────────────────────────────────────────
const chartSeries = computed(() => [
  {
    name: 'Ingresos',
    data: data.value.flujo_caja.map(r => Math.round(r.ingresos / 1000)),
  },
  {
    name: 'Egresos',
    data: data.value.flujo_caja.map(r => -Math.round(r.egresos / 1000)),
  },
])

const chartOpts = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    background: 'transparent',
    fontFamily: 'inherit',
  },
  theme: { mode: 'dark' },
  colors: ['#56CA00', '#FF4C51'],
  plotOptions: {
    bar: {
      columnWidth: '55%',
      borderRadius: 4,
    },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: data.value.flujo_caja.map(r => fmtMes(r.mes)),
    labels: { style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: v => (v >= 0 ? '+' : '') + v.toLocaleString('es-CL') + 'K',
      style: { fontSize: '11px' },
    },
  },
  tooltip: {
    y: {
      formatter: v => clp(Math.abs(v) * 1000),
    },
  },
  legend: {
    position: 'top',
    horizontalAlign: 'right',
  },
  grid: {
    borderColor: 'rgba(255,255,255,0.08)',
  },
}))

// ── Helpers ──────────────────────────────────────────────────────────────
const clp = (n) =>
  new Intl.NumberFormat('es-CL', {
    style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
  }).format(Number(n) || 0)

function fmtMes(ym) {
  const [y, m] = ym.split('-')
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']
  return `${meses[parseInt(m) - 1]}-${y.slice(2)}`
}

function fmtFecha(f) {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function iniciales(nombre) {
  if (!nombre) return '?'
  return nombre.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<style scoped>
.kpi-card {
  border-left-width: 4px !important;
  border-left-style: solid !important;
}
.skeleton-text {
  background: rgba(255,255,255,0.08);
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.4; }
}
.resultado-row {
  border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.resultado-row td {
  padding-top: 10px !important;
  padding-bottom: 10px !important;
}
</style>
