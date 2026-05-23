<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Conciliación Bancaria</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Banco de Chile — Cuenta {{ cuenta }}</p>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2">
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="mdi-refresh"
          :loading="loadingSaldo"
          @click="cargarSaldo"
        >Saldo</VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="mdi-link-variant"
          :loading="loadingMatch"
          @click="autoConcilar"
        >Auto-conciliar</VBtn>
        <VBtn
          color="primary"
          prepend-icon="mdi-cloud-download"
          :loading="loadingImport"
          @click="dialogImportar = true"
        >Importar</VBtn>
      </VCol>
    </VRow>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Saldo Disponible</p>
            <p class="text-h5 font-weight-bold text-primary mb-0">
              {{ saldo !== null ? formatMonto(saldo) : '—' }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Ingresos del período</p>
            <p class="text-h5 font-weight-bold text-success mb-0">
              {{ formatMonto(totales.total_creditos || 0) }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Egresos del período</p>
            <p class="text-h5 font-weight-bold text-error mb-0">
              {{ formatMonto(totales.total_debitos || 0) }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Pendientes conciliar</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">
              {{ totales.pendientes || 0 }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabs -->
    <VCard>
      <VTabs v-model="tab" color="primary">
        <VTab value="movimientos">Movimientos</VTab>
        <VTab value="flujo">Flujo de Caja</VTab>
      </VTabs>
      <VDivider />

      <!-- Tab Movimientos -->
      <VWindow v-model="tab">
        <VWindowItem value="movimientos">
          <!-- Filtros -->
          <VCardText>
            <VRow dense>
              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model="filtros.desde"
                  label="Desde"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarMovimientos"
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model="filtros.hasta"
                  label="Hasta"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarMovimientos"
                />
              </VCol>
              <VCol cols="6" md="2">
                <VSelect
                  v-model="filtros.tipo"
                  label="Tipo"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :items="[{ title: 'Todos', value: '' }, { title: 'Crédito', value: 'C' }, { title: 'Débito', value: 'D' }]"
                  item-title="title"
                  item-value="value"
                  @update:modelValue="cargarMovimientos"
                />
              </VCol>
              <VCol cols="6" md="2">
                <VSelect
                  v-model="filtros.conciliado"
                  label="Estado"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :items="[{ title: 'Todos', value: '' }, { title: 'Pendientes', value: 'false' }, { title: 'Conciliados', value: 'true' }]"
                  item-title="title"
                  item-value="value"
                  @update:modelValue="cargarMovimientos"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="filtros.buscar"
                  label="Buscar descripción"
                  density="compact"
                  variant="outlined"
                  hide-details
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  @update:modelValue="debounceBuscar"
                />
              </VCol>
            </VRow>
          </VCardText>

          <!-- Tabla -->
          <VDataTable
            :headers="headers"
            :items="movimientos"
            :loading="loadingTable"
            item-value="id"
            density="compact"
            class="text-no-wrap"
          >
            <!-- Fecha -->
            <template #item.fecha_contable="{ item }">
              {{ formatFecha(item.fecha_contable) }}
            </template>

            <!-- Monto con color -->
            <template #item.monto="{ item }">
              <span :class="item.tipo === 'C' ? 'text-success' : 'text-error'" class="font-weight-medium">
                {{ item.tipo === 'C' ? '+' : '-' }}{{ formatMonto(item.monto) }}
              </span>
            </template>

            <!-- Tipo chip -->
            <template #item.tipo="{ item }">
              <VChip
                size="x-small"
                :color="item.tipo === 'C' ? 'success' : 'error'"
                variant="tonal"
              >{{ item.tipo === 'C' ? 'Crédito' : 'Débito' }}</VChip>
            </template>

            <!-- Categoría editable -->
            <template #item.categoria="{ item }">
              <VSelect
                :model-value="item.categoria"
                :items="categorias"
                density="compact"
                variant="plain"
                hide-details
                placeholder="Sin categoría"
                style="min-width: 140px"
                @update:modelValue="(v) => actualizarMov(item, { categoria: v })"
              />
            </template>

            <!-- Conciliado toggle -->
            <template #item.conciliado="{ item }">
              <VSwitch
                :model-value="item.conciliado"
                density="compact"
                hide-details
                color="success"
                @update:modelValue="(v) => actualizarMov(item, { conciliado: v })"
              />
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <VBtn
                v-if="item.tipo === 'D' && !item.compra_id"
                size="x-small"
                variant="tonal"
                color="info"
                @click="abrirLinkCompra(item)"
              >
                <VIcon size="14">mdi-link</VIcon>
              </VBtn>
            </template>

            <template #bottom>
              <div class="pa-3 text-caption text-medium-emphasis">
                {{ movimientos.length }} movimientos mostrados
              </div>
            </template>
          </VDataTable>
        </VWindowItem>

        <!-- Tab Flujo de Caja -->
        <VWindowItem value="flujo">
          <VCardText>
            <VRow dense class="mb-4">
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="filtroFlujo.desde"
                  label="Desde"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarFlujo"
                />
              </VCol>
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="filtroFlujo.hasta"
                  label="Hasta"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarFlujo"
                />
              </VCol>
            </VRow>
            <VueApexCharts
              v-if="flujoCajaData.length"
              type="bar"
              height="320"
              :options="chartOptions"
              :series="chartSeries"
            />
            <p v-else class="text-center text-medium-emphasis py-8">Sin datos para el período</p>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Dialog Importar -->
    <VDialog v-model="dialogImportar" max-width="420">
      <VCard title="Importar Movimientos">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Descarga los movimientos desde Banco de Chile para el rango seleccionado.
          </p>
          <VRow dense>
            <VCol cols="6">
              <VTextField
                v-model="importForm.desde"
                label="Desde"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="importForm.hasta"
                label="Hasta"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
          <VAlert
            v-if="importResult"
            class="mt-4"
            :color="importResult.error ? 'error' : 'success'"
            variant="tonal"
          >
            <span v-if="importResult.error">{{ importResult.error }}</span>
            <span v-else>
              {{ importResult.nuevos }} nuevos · {{ importResult.duplicados }} duplicados
              ({{ importResult.total }} total)
              <span v-if="importResult.errores?.length" class="d-block text-caption mt-1">
                Errores ({{ importResult.errores.length }}): {{ importResult.errores[0] }}
              </span>
            </span>
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogImportar = false">Cerrar</VBtn>
          <VBtn color="primary" :loading="loadingImport" @click="importar">Importar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Link Compra -->
    <VDialog v-model="dialogLinkCompra" max-width="500">
      <VCard title="Vincular con Compra">
        <VCardText>
          <p class="text-body-2 mb-2">
            Movimiento: <strong>{{ movSeleccionado?.descripcion }}</strong>
            — {{ formatMonto(movSeleccionado?.monto) }}
          </p>
          <VTextField
            v-model="buscarCompra"
            label="Buscar compra por folio o proveedor"
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-magnify"
          />
          <VList v-if="comprasSugeridas.length" density="compact" class="mt-2">
            <VListItem
              v-for="c in comprasSugeridas"
              :key="c.id"
              :subtitle="`${c.folio} — ${formatMonto(c.neto)}`"
              :title="c.proveedor_nombre || c.emisor"
              @click="vincularCompra(c)"
            />
          </VList>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogLinkCompra = false">Cancelar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/axiosInstance'
import VueApexCharts from 'vue3-apexcharts'

const cuenta = computed(() => import.meta.env.VITE_BCH_CUENTA || '—')

// ── Estado ──────────────────────────────────────────────────────────────────

const tab = ref('movimientos')
const movimientos = ref([])
const totales = ref({})
const saldo = ref(null)
const flujoCajaData = ref([])

const loadingTable = ref(false)
const loadingSaldo = ref(false)
const loadingImport = ref(false)
const loadingMatch = ref(false)

const dialogImportar = ref(false)
const dialogLinkCompra = ref(false)
const movSeleccionado = ref(null)
const buscarCompra = ref('')
const comprasSugeridas = ref([])
const importResult = ref(null)

const hoy = new Date().toISOString().slice(0, 10)
const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

const filtros = ref({
  desde: primerDiaMes,
  hasta: hoy,
  tipo: '',
  conciliado: '',
  buscar: '',
})

const importForm = ref({ desde: primerDiaMes, hasta: hoy })

const filtroFlujo = ref({
  desde: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
  hasta: hoy,
})

const categorias = [
  'Compra proveedor',
  'Sueldo',
  'Arriendo',
  'Servicios básicos',
  'Impuestos',
  'Ingreso cliente',
  'Transferencia interna',
  'Comisión bancaria',
  'Otro',
]

// ── Headers tabla ────────────────────────────────────────────────────────────

const headers = [
  { title: 'Fecha', key: 'fecha_contable', sortable: true },
  { title: 'Descripción', key: 'descripcion', sortable: false },
  { title: 'N° Doc', key: 'numero_documento', sortable: false },
  { title: 'Monto', key: 'monto', align: 'end', sortable: true },
  { title: 'Tipo', key: 'tipo', align: 'center', sortable: false },
  { title: 'Categoría', key: 'categoria', sortable: false },
  { title: 'Conciliado', key: 'conciliado', align: 'center', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '50px' },
]

// ── API calls ────────────────────────────────────────────────────────────────

async function cargarMovimientos() {
  loadingTable.value = true
  try {
    const params = { ...filtros.value }
    const { data } = await axios.get('/api/conciliacion/movimientos', { params })
    movimientos.value = data.movimientos?.data || []
    totales.value = data.totales || {}
  } catch (e) {
    console.error(e)
  } finally {
    loadingTable.value = false
  }
}

async function cargarSaldo() {
  loadingSaldo.value = true
  try {
    const { data } = await axios.get('/api/conciliacion/saldo')
    saldo.value = data.saldoDisponible ?? data.saldo ?? null
  } catch (e) {
    console.error(e)
  } finally {
    loadingSaldo.value = false
  }
}

async function importar() {
  loadingImport.value = true
  importResult.value = null
  try {
    const { data } = await axios.post('/api/conciliacion/importar', importForm.value)
    importResult.value = data
    await cargarMovimientos()
  } catch (e) {
    importResult.value = { error: e.response?.data?.error || 'Error al importar' }
  } finally {
    loadingImport.value = false
  }
}

async function autoConcilar() {
  loadingMatch.value = true
  try {
    const { data } = await axios.post('/api/conciliacion/auto-concilar')
    alert(`Conciliación automática: ${data.matches} movimientos vinculados`)
    await cargarMovimientos()
  } catch (e) {
    console.error(e)
  } finally {
    loadingMatch.value = false
  }
}

async function actualizarMov(item, changes) {
  try {
    const { data } = await axios.patch(`/api/conciliacion/movimientos/${item.id}`, changes)
    Object.assign(item, data)
  } catch (e) {
    console.error(e)
  }
}

async function cargarFlujo() {
  try {
    const { data } = await axios.get('/api/conciliacion/flujo-caja', { params: filtroFlujo.value })
    flujoCajaData.value = data
  } catch (e) {
    console.error(e)
  }
}

// ── Vincular compra ──────────────────────────────────────────────────────────

function abrirLinkCompra(mov) {
  movSeleccionado.value = mov
  buscarCompra.value = ''
  comprasSugeridas.value = []
  dialogLinkCompra.value = true
}

async function vincularCompra(compra) {
  await actualizarMov(movSeleccionado.value, { compra_id: compra.id, conciliado: true })
  dialogLinkCompra.value = false
  await cargarMovimientos()
}

let buscarTimer = null
function debounceBuscar() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargarMovimientos, 350)
}

// ── Chart flujo de caja ──────────────────────────────────────────────────────

const chartSeries = computed(() => [
  { name: 'Ingresos', data: flujoCajaData.value.map(r => parseFloat(r.ingresos || 0)) },
  { name: 'Egresos',  data: flujoCajaData.value.map(r => parseFloat(r.egresos || 0)) },
])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, foreColor: '#a8aaae' },
  colors: ['#28c76f', '#ea5455'],
  xaxis: {
    categories: flujoCajaData.value.map(r => r.mes),
    labels: { style: { colors: '#a8aaae' } },
  },
  yaxis: { labels: { formatter: v => '$' + Math.round(v).toLocaleString('es-CL') } },
  tooltip: { theme: 'dark', y: { formatter: v => '$' + Math.round(v).toLocaleString('es-CL') } },
  grid: { borderColor: 'rgba(255,255,255,0.07)' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  dataLabels: { enabled: false },
  legend: { labels: { colors: '#a8aaae' } },
}))

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatMonto(v) {
  return '$' + parseFloat(v || 0).toLocaleString('es-CL', { minimumFractionDigits: 0 })
}

function formatFecha(str) {
  if (!str) return ''
  return str.slice(0, 10)
}

// ── Init ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await cargarMovimientos()
  await cargarFlujo()
  cargarSaldo()
})
</script>
