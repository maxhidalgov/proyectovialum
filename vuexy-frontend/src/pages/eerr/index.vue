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
          <VBtn value="matriz"  size="small">Mes a mes</VBtn>
        </VBtnToggle>
        <VBtn v-if="modo === 'matriz'" color="success" variant="tonal" size="small" prepend-icon="mdi-download" @click="descargarMatrizCsv">
          Descargar
        </VBtn>

        <!-- Selector de mes (modo mensual) -->
        <template v-if="modo === 'mensual'">
          <VSelect
            v-model="mesSel"
            :items="mesesOpts"
            density="compact"
            variant="outlined"
            hide-details
            style="min-width:130px"
            @update:modelValue="cargar"
          />
          <VSelect
            v-model="anioSel"
            :items="aniosDisponibles"
            density="compact"
            variant="outlined"
            hide-details
            style="min-width:95px"
            @update:modelValue="cargar"
          />
        </template>

        <!-- Selector de año (modo anual) -->
        <VSelect
          v-else
          v-model="anioSel"
          :items="aniosDisponibles"
          density="compact"
          variant="outlined"
          hide-details
          style="min-width:95px"
          @update:modelValue="cargar"
        />

        <VBtn icon="mdi-refresh" variant="text" size="small" :loading="loading" @click="cargar" />
      </VCol>
    </VRow>

    <!-- Tarjetas resumen -->
    <VRow v-if="modo !== 'matriz'" class="mb-5">
      <template v-if="loading">
        <VCol v-for="n in 4" :key="n" cols="6" sm="3">
          <VCard variant="outlined" class="pa-3">
            <VSkeletonLoader type="text" width="80" class="mb-2" />
            <VSkeletonLoader type="heading" />
          </VCard>
        </VCol>
      </template>
      <template v-else>
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
      </template>
    </VRow>

    <!-- ── VISTA MES A MES (matriz anual) ─────────────────────────── -->
    <VCard v-if="modo === 'matriz'">
      <VCardText class="pa-0">
        <div v-if="loading" class="text-center py-10"><VProgressCircular indeterminate size="32" /></div>
        <div v-else-if="matriz" style="overflow-x:auto">
          <table class="matriz-eerr">
            <thead>
              <tr>
                <th class="text-left sticky-col">Resultado Operacional {{ matriz.anio }}</th>
                <th v-for="(mes, i) in matriz.meses" :key="i" class="text-right">{{ mes }}</th>
                <th class="text-right total-col">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, ri) in matriz.rows" :key="ri" :class="filaClase(r)">
                <td class="text-left sticky-col" :style="r.nivel === 1 ? 'padding-left:24px' : ''">{{ r.label }}</td>
                <td v-for="(mes, i) in matriz.meses" :key="i" class="text-right">{{ celda(r, r.valores[i + 1]) }}</td>
                <td class="text-right total-col">{{ celda(r, r.total) }}</td>
              </tr>
              <tr class="fila-margen">
                <td class="text-left sticky-col">Margen %</td>
                <td v-for="(mes, i) in matriz.meses" :key="i" class="text-right">{{ matriz.margen.valores[i + 1] }}%</td>
                <td class="text-right total-col">{{ matriz.margen.total }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCardText>
    </VCard>

    <VRow v-if="modo !== 'matriz'">
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
                  class="eerr-row eerr-clickable px-4 py-1 d-flex justify-space-between"
                  @click="abrirDetalle({ label: cat.categoria, origen: 'ingreso_manual', filtro: cat.categoria })">
                  <span class="text-caption text-medium-emphasis pl-8">↳ {{ cat.categoria }} ({{ cat.cantidad }})
                    <VIcon size="11" class="ml-1">mdi-magnify</VIcon>
                  </span>
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

              <!-- SECCIONES DE EGRESOS (dinámicas) -->
              <template v-for="sec in data.secciones" :key="sec.key">
                <div class="eerr-section eerr-header bg-error-subtle px-4 py-2">
                  <div class="d-flex justify-space-between font-weight-bold">
                    <span>{{ sec.titulo }}</span>
                    <span class="text-error">({{ clp(sec.total) }})</span>
                  </div>
                </div>
                <template v-if="sec.total === 0">
                  <div class="eerr-row px-4 py-1 d-flex align-center">
                    <span class="text-caption text-medium-emphasis pl-4">Sin registros en el período</span>
                  </div>
                </template>
                <template v-else>
                  <template v-for="grupo in sec.grupos" :key="grupo.titulo ?? '_'">
                    <template v-if="grupo.lineas.length > 0">
                      <div v-if="grupo.titulo" class="eerr-row eerr-grupo-titulo px-4 py-1 d-flex align-center">
                        <span class="text-caption font-weight-medium text-medium-emphasis pl-2">{{ grupo.titulo }}</span>
                      </div>
                      <div v-for="linea in grupo.lineas" :key="linea.label"
                           class="eerr-row px-4 py-1 d-flex justify-space-between"
                           :class="{ 'eerr-clickable': linea.origen }"
                           @click="linea.origen && abrirDetalle(linea)">
                        <span class="text-caption" :class="grupo.titulo ? 'pl-8' : 'pl-4'">
                          {{ linea.label }} <span class="text-medium-emphasis">({{ linea.cantidad }})</span>
                          <VIcon v-if="linea.origen" size="11" class="text-medium-emphasis ml-1">mdi-magnify</VIcon>
                        </span>
                        <span class="text-caption">{{ clp(linea.total) }}</span>
                      </div>
                    </template>
                  </template>
                </template>

                <!-- UTILIDAD BRUTA intercalada después de Costo de Ventas -->
                <template v-if="sec.key === 'costo_ventas'">
                  <VDivider />
                  <div class="eerr-section px-4 py-2 d-flex justify-space-between font-weight-bold bg-surface-variant">
                    <span>UTILIDAD BRUTA</span>
                    <span :class="`text-${data.resultados.utilidad_bruta >= 0 ? 'success' : 'error'}`">
                      {{ clp(data.resultados.utilidad_bruta) }}
                      <span class="text-caption ml-1">({{ data.resultados.margen_bruto }}%)</span>
                    </span>
                  </div>
                </template>
                <VDivider />
              </template>

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
            {{ modo === 'anual' ? `Mensual ${anioSel}` : 'Tendencia — últimos 6 meses' }}
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

    <!-- Detalle de una línea del EERR -->
    <VDialog v-model="dialogDetalle.show" max-width="720" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          {{ dialogDetalle.label }}
          <VSpacer />
          <span class="text-body-2 font-weight-bold">{{ clp(dialogDetalle.total) }}</span>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-0">
          <div v-if="dialogDetalle.loading" class="text-center py-8"><VProgressCircular indeterminate /></div>
          <template v-else>
            <p v-if="!dialogDetalle.items.length" class="text-center text-medium-emphasis py-8">Sin documentos en el período.</p>
            <VTable v-else density="compact">
              <thead><tr><th>Fecha</th><th v-if="tieneFolio">Folio</th><th>Detalle</th><th class="text-right">Monto</th></tr></thead>
              <tbody>
                <tr v-for="(it, i) in dialogDetalle.items" :key="i">
                  <td class="text-caption text-no-wrap">{{ (it.fecha || '').slice(0,10) }}</td>
                  <td v-if="tieneFolio" class="text-caption">{{ it.folio || '—' }}</td>
                  <td class="text-caption">{{ it.nombre || '—' }}</td>
                  <td class="text-right text-caption font-weight-medium">{{ clp(it.monto) }}</td>
                </tr>
              </tbody>
            </VTable>
          </template>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="dialogDetalle.show = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/axiosInstance'

// ── Estado ───────────────────────────────────────────────────────
const loading = ref(false)
const data    = ref(null)
const matriz  = ref(null)        // vista mes a mes (12 columnas)
const modo    = ref('mensual')   // 'mensual' | 'anual' | 'matriz'

// ── Detalle de línea (drill-down) ────────────────────────────────
const dialogDetalle = ref({ show: false, loading: false, label: '', items: [], total: 0 })
const tieneFolio = computed(() => dialogDetalle.value.items.some(i => i.folio))

async function abrirDetalle(linea) {
  if (!linea?.origen) return
  dialogDetalle.value = { show: true, loading: true, label: linea.label, items: [], total: 0 }
  try {
    const { data: d } = await api.get('/api/eerr/detalle', {
      params: {
        origen: linea.origen,
        filtro: linea.filtro,
        desde:  data.value?.periodo?.desde,
        hasta:  data.value?.periodo?.hasta,
      },
    })
    dialogDetalle.value.items = d.items || []
    dialogDetalle.value.total = d.total || 0
  } catch {
    dialogDetalle.value.items = []
  } finally {
    dialogDetalle.value.loading = false
  }
}

const _hoy   = new Date()
const mesSel = ref(_hoy.getMonth() + 1)   // 1-12
const anioSel = ref(_hoy.getFullYear())

const periodoMes = computed(() =>
  `${anioSel.value}-${String(mesSel.value).padStart(2, '0')}`
)

const hoy = _hoy
const aniosDisponibles = Array.from({ length: 5 }, (_, i) => hoy.getFullYear() - i)

const mesesOpts = [
  { title: 'Enero',      value: 1  },
  { title: 'Febrero',    value: 2  },
  { title: 'Marzo',      value: 3  },
  { title: 'Abril',      value: 4  },
  { title: 'Mayo',       value: 5  },
  { title: 'Junio',      value: 6  },
  { title: 'Julio',      value: 7  },
  { title: 'Agosto',     value: 8  },
  { title: 'Septiembre', value: 9  },
  { title: 'Octubre',    value: 10 },
  { title: 'Noviembre',  value: 11 },
  { title: 'Diciembre',  value: 12 },
]

// Recargar al cambiar modo
watch(modo, cargar)

// ── Computed ─────────────────────────────────────────────────────
const utilidadColor = computed(() => {
  const u = data.value?.resultados?.utilidad_operacional ?? 0
  return u >= 0 ? 'success' : 'error'
})

const periodoLabel = computed(() => {
  if (modo.value === 'anual') return `Año ${anioSel.value}`
  return `${mesesOpts[mesSel.value - 1]?.title ?? ''} ${anioSel.value}`
})

const egresosItems = computed(() => {
  if (!data.value?.secciones) return []
  const total = data.value.resultados.total_egresos || 1
  const colorMap = { costo_ventas: 'error', gastos_operacionales: 'warning', remuneraciones: 'deep-purple', financiero: 'blue-grey' }
  return data.value.secciones
    .filter(s => s.total > 0)
    .map(s => ({ label: s.titulo, monto: s.total, color: colorMap[s.key] ?? 'grey', pct: Math.round(s.total / total * 100) }))
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
    if (modo.value === 'matriz') {
      matriz.value = null
      const { data: res } = await api.get('/api/eerr/anual-mensual', { params: { anio: anioSel.value } })
      matriz.value = res
    } else {
      data.value = null
      const params = modo.value === 'anual'
        ? { modo: 'anual', anio: anioSel.value }
        : { modo: 'mensual', mes: mesSel.value, anio: anioSel.value }
      const { data: res } = await api.get('/api/eerr', { params })
      data.value = res
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// Descargar la matriz mes a mes como CSV (se abre en Excel)
function descargarMatrizCsv() {
  if (!matriz.value) return
  const meses = matriz.value.meses
  const head = ['', ...meses, 'Total']
  const esResultado = r => r.tipo === 'ingreso' || r.tipo === 'resultado'
  const signo = r => (r.tipo === 'seccion' || r.tipo === 'linea') ? -1 : 1
  const filas = matriz.value.rows.map(r => {
    const s = signo(r)
    const label = (r.nivel === 1 ? '   ' : '') + r.label
    const vals = meses.map((_, i) => Math.round(s * (r.valores[i + 1] || 0)))
    return [label, ...vals, Math.round(s * r.total)]
  })
  const mg = matriz.value.margen
  const filaMargen = ['Margen %', ...meses.map((_, i) => (mg.valores[i + 1] ?? 0) + '%'), mg.total + '%']
  const rows = [head, ...filas, filaMargen]
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `EERR_${matriz.value.anio}_mes_a_mes.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// ── Helpers ───────────────────────────────────────────────────────
const clp = n => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)

// Matriz mes a mes: egresos (sección/línea) se muestran en negativo; 0 → "-"
function celda(r, val) {
  const s = (r.tipo === 'seccion' || r.tipo === 'linea') ? -1 : 1
  const n = Math.round(s * (Number(val) || 0))
  return n === 0 ? '-' : n.toLocaleString('es-CL')
}
function filaClase(r) {
  return {
    'fila-ingreso':   r.tipo === 'ingreso',
    'fila-seccion':   r.tipo === 'seccion',
    'fila-linea':     r.tipo === 'linea',
    'fila-resultado': r.tipo === 'resultado',
  }
}

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
.eerr-clickable { cursor: pointer; }
.eerr-clickable:hover { background: rgba(var(--v-theme-primary), 0.06); }

/* Matriz mes a mes */
.matriz-eerr {
  border-collapse: collapse;
  width: 100%;
  font-size: 12px;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.matriz-eerr th, .matriz-eerr td {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  padding: 4px 10px;
}
.matriz-eerr thead th {
  background: rgba(var(--v-theme-primary), 0.10);
  font-weight: 700;
  position: sticky; top: 0; z-index: 2;
}
.matriz-eerr .sticky-col {
  position: sticky; left: 0; z-index: 1;
  background: rgb(var(--v-theme-surface));
  min-width: 230px;
}
.matriz-eerr thead .sticky-col { z-index: 3; background: rgba(var(--v-theme-primary), 0.10); }
.matriz-eerr .total-col { background: rgba(var(--v-theme-success), 0.08); font-weight: 600; }
.matriz-eerr .fila-ingreso td { background: rgba(var(--v-theme-success), 0.10); font-weight: 700; }
.matriz-eerr .fila-ingreso td.sticky-col { background: rgba(var(--v-theme-success), 0.14); }
.matriz-eerr .fila-seccion td { font-weight: 700; }
.matriz-eerr .fila-linea td { color: rgba(var(--v-theme-on-surface), 0.75); }
.matriz-eerr .fila-linea td.sticky-col { font-weight: 400; }
.matriz-eerr .fila-resultado td { background: rgba(var(--v-theme-primary), 0.12); font-weight: 800; border-top: 2px solid rgba(var(--v-theme-primary), 0.4); }
.matriz-eerr .fila-margen td { font-style: italic; color: rgba(var(--v-theme-on-surface), 0.6); }
.eerr-grupo-titulo {
  background: rgba(var(--v-border-color), 0.04);
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06);
  min-height: 28px;
}
</style>
