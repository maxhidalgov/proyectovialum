<template>
  <VContainer fluid class="pa-4">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-3">
      <div>
        <h1 class="text-h5 font-weight-bold">Compras</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">Facturas de compra recibidas desde Bsale</p>
      </div>
      <div class="d-flex align-center gap-2 flex-wrap">
        <VBtn
          v-if="xmlRestantes > 0"
          color="warning"
          variant="tonal"
          prepend-icon="mdi-file-download"
          :loading="cargandoXmls"
          @click="cargarXmlsPendientes"
        >
          Cargar líneas ({{ xmlRestantes }} pendientes)
        </VBtn>
        <VSelect
          v-model="maxSincronizar"
          :items="opcionesSinc"
          item-title="label"
          item-value="value"
          hide-details
          variant="outlined"
          density="compact"
          style="width: 160px"
        />
        <VBtn
          color="primary"
          prepend-icon="mdi-sync"
          :loading="sincronizando"
          @click="sincronizar"
        >
          Sincronizar
        </VBtn>
      </div>
    </div>

    <!-- Progreso bulk sync (auto-loop) -->
    <VAlert v-if="syncProgress" type="info" class="mb-4" variant="tonal">
      <div class="d-flex align-center gap-3">
        <VProgressCircular indeterminate size="20" width="2" />
        <span>Sincronizando... <strong>{{ syncProgress.nuevas }}</strong> facturas importadas (ronda {{ syncProgress.rondas }})</span>
      </div>
    </VAlert>

    <!-- Resultado sincronización -->
    <VAlert
      v-if="syncResult"
      :type="syncResult.errores > 0 ? 'warning' : 'success'"
      class="mb-4"
      closable
      @click:close="syncResult = null"
    >
      Sincronización completada: <strong>{{ syncResult.nuevas }}</strong> facturas nuevas importadas
      de <strong>{{ syncResult.total_bsale }}</strong> disponibles desde 2024.
      <span v-if="syncResult.errores > 0"> ({{ syncResult.errores }} errores)</span>
    </VAlert>

    <!-- Tabs -->
    <VTabs v-model="tab" class="mb-4">
      <VTab value="facturas">Facturas</VTab>
      <VTab value="buscar">Buscar Producto</VTab>
    </VTabs>

    <!-- TAB: FACTURAS -->
    <div v-if="tab === 'facturas'">
      <!-- Filtros -->
      <VRow class="mb-3" dense>
        <VCol cols="12" md="5">
          <VTextField
            v-model="filtros.search"
            label="Buscar por proveedor, RUT, folio o producto..."
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            variant="outlined"
            density="compact"
            @update:model-value="fetchCompras"
          />
        </VCol>
        <VCol cols="6" md="3">
          <VTextField
            v-model="filtros.desde"
            label="Desde"
            type="date"
            hide-details
            variant="outlined"
            density="compact"
            @update:model-value="fetchCompras"
          />
        </VCol>
        <VCol cols="6" md="3">
          <VTextField
            v-model="filtros.hasta"
            label="Hasta"
            type="date"
            hide-details
            variant="outlined"
            density="compact"
            @update:model-value="fetchCompras"
          />
        </VCol>
      </VRow>

      <!-- Tabla -->
      <VCard>
        <VDataTableServer
          :headers="headers"
          :items="compras"
          :items-length="totalCompras"
          :loading="loading"
          :items-per-page="25"
          @update:options="onTableOptions"
        >
          <template #item.fecha_emision="{ item }">
            {{ formatFecha(item.fecha_emision) }}
          </template>
          <template #item.total="{ item }">
            ${{ formatNum(item.total) }}
          </template>
          <template #item.neto="{ item }">
            ${{ formatNum(item.neto) }}
          </template>
          <template #item.estado="{ item }">
            <VChip
              size="x-small"
              :color="colorEstado(item.estado)"
              variant="tonal"
            >
              {{ item.estado || 'Sin estado' }}
            </VChip>
          </template>
          <template #item.acciones="{ item }">
            <VBtn
              size="x-small"
              variant="tonal"
              color="primary"
              icon="mdi-eye"
              @click="verDetalle(item)"
            />
          </template>
          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <VIcon size="48" class="mb-2">mdi-package-variant</VIcon>
              <div>No hay facturas. Presiona "Sincronizar" para importar desde Bsale.</div>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- TAB: BUSCAR PRODUCTO -->
    <div v-if="tab === 'buscar'">
      <VRow class="mb-4" dense>
        <VCol cols="12" md="6">
          <VTextField
            v-model="busquedaProducto"
            label="Buscar producto en facturas de compra..."
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            variant="outlined"
            @update:model-value="buscarProducto"
          />
        </VCol>
      </VRow>

      <div v-if="loadingBusqueda" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div v-else-if="resultadosProducto.length === 0 && busquedaProducto.length >= 2" class="text-center py-8 text-medium-emphasis">
        No se encontraron productos con "{{ busquedaProducto }}"
      </div>

      <VExpansionPanels v-else-if="resultadosProducto.length > 0">
        <VExpansionPanel
          v-for="(prod, i) in resultadosProducto"
          :key="i"
        >
          <VExpansionPanelTitle>
            <div class="d-flex align-center justify-space-between w-100 pr-4">
              <span class="font-weight-medium">{{ prod.nombre }}</span>
              <div class="d-flex gap-4 text-caption">
                <span>
                  <span class="text-medium-emphasis">Último precio neto:</span>
                  <strong class="text-success ml-1">${{ formatNum(prod.ultimo_precio_neto) }}</strong>
                  <span v-if="prod.ultimo_descuento > 0" class="text-warning ml-1">({{ prod.ultimo_descuento }}% desc.)</span>
                </span>
                <span>
                  <span class="text-medium-emphasis">Última compra:</span>
                  <strong class="ml-1">{{ formatFecha(prod.ultima_compra) }}</strong>
                </span>
                <span>
                  <span class="text-medium-emphasis">Proveedor:</span>
                  <strong class="ml-1">{{ prod.proveedor }}</strong>
                </span>
              </div>
            </div>
          </VExpansionPanelTitle>
          <VExpansionPanelText>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Proveedor</th>
                  <th>Folio</th>
                  <th>Cantidad</th>
                  <th>Unidad</th>
                  <th>P. Unitario</th>
                  <th>Desc.</th>
                  <th>P. Neto</th>
                  <th>Total Línea</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(h, j) in prod.historial" :key="j">
                  <td>{{ formatFecha(h.fecha) }}</td>
                  <td>{{ h.proveedor }}</td>
                  <td>
                    <a v-if="h.pdf_url" :href="h.pdf_url" target="_blank" class="text-primary font-weight-medium" style="text-decoration:none">
                      {{ h.folio }} <VIcon size="11">mdi-open-in-new</VIcon>
                    </a>
                    <span v-else>{{ h.folio }}</span>
                  </td>
                  <td>{{ h.cantidad }}</td>
                  <td>{{ h.unidad || '—' }}</td>
                  <td>${{ formatNum(h.precio_unitario) }}</td>
                  <td>{{ h.descuento > 0 ? h.descuento + '%' : '—' }}</td>
                  <td class="font-weight-medium text-success">${{ formatNum(h.precio_neto) }}</td>
                  <td>${{ formatNum(h.total_linea) }}</td>
                </tr>
              </tbody>
            </VTable>
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </div>

    <!-- DIALOG DETALLE FACTURA -->
    <VDialog v-model="dialogDetalle" max-width="800">
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span>Factura {{ facturaActiva.tipo_dte }} N° {{ facturaActiva.folio }}</span>
          <VBtn icon="mdi-close" variant="text" size="small" @click="dialogDetalle = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VRow dense class="mb-4">
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">Proveedor</div>
              <div class="font-weight-medium">{{ facturaActiva.nombre_emisor }}</div>
              <div class="text-caption">{{ facturaActiva.rut_emisor }}</div>
            </VCol>
            <VCol cols="3">
              <div class="text-caption text-medium-emphasis">Emisión</div>
              <div>{{ formatFecha(facturaActiva.fecha_emision) }}</div>
            </VCol>
            <VCol cols="3">
              <div class="text-caption text-medium-emphasis">Recepción</div>
              <div>{{ formatFecha(facturaActiva.fecha_recepcion) }}</div>
            </VCol>
          </VRow>

          <VTable density="compact" class="mb-4">
            <thead>
              <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Unidad</th>
                <th>P. Unitario</th>
                <th>Desc.</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!facturaActiva.items?.length">
                <td colspan="7" class="text-center py-4">
                  <div v-if="facturaActiva.xml_url">
                    <div class="text-medium-emphasis mb-2">Líneas no cargadas aún</div>
                    <VBtn size="small" color="primary" variant="tonal" :loading="cargandoXml" prepend-icon="mdi-download" @click="cargarXml">
                      Cargar líneas desde XML
                    </VBtn>
                  </div>
                  <span v-else class="text-medium-emphasis">XML no disponible para esta factura</span>
                </td>
              </tr>
              <tr v-for="item in facturaActiva.items" :key="item.id">
                <td class="text-caption">{{ item.codigo || '—' }}</td>
                <td>{{ item.nombre }}</td>
                <td>{{ item.cantidad }}</td>
                <td>{{ item.unidad || '—' }}</td>
                <td>${{ formatNum(item.precio_unitario) }}</td>
                <td>{{ item.descuento > 0 ? item.descuento + '%' : '—' }}</td>
                <td>${{ formatNum(item.total_linea) }}</td>
              </tr>
            </tbody>
          </VTable>

          <div class="d-flex justify-end gap-6">
            <div class="text-right">
              <div class="text-caption text-medium-emphasis">Neto</div>
              <div>${{ formatNum(facturaActiva.neto) }}</div>
            </div>
            <div class="text-right">
              <div class="text-caption text-medium-emphasis">IVA</div>
              <div>${{ formatNum(facturaActiva.iva) }}</div>
            </div>
            <div class="text-right">
              <div class="text-caption text-medium-emphasis">Total</div>
              <div class="text-h6 font-weight-bold">${{ formatNum(facturaActiva.total) }}</div>
            </div>
          </div>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            v-if="facturaActiva.pdf_url"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-file-pdf-box"
            :href="facturaActiva.pdf_url"
            target="_blank"
          >
            Ver PDF
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axiosInstance from '@/axiosInstance'
const API = '/api/compras'
import { useDebounceFn } from '@vueuse/core'

const tab               = ref('facturas')
const cargandoXml       = ref(false)
const cargandoXmls      = ref(false)
const xmlRestantes      = ref(0)
const maxSincronizar    = ref(20)
const opcionesSinc      = [
  { label: 'Últimas 20',   value: 20  },
  { label: 'Últimas 50',   value: 50  },
  { label: 'Últimas 100',  value: 100 },
  { label: 'Todas',        value: 0   },
]
const loading           = ref(false)
const sincronizando     = ref(false)
const loadingBusqueda   = ref(false)
const compras           = ref([])
const totalCompras      = ref(0)
const syncResult        = ref(null)
const syncProgress      = ref(null)  // { nuevas, rondas } durante auto-loop
const dialogDetalle     = ref(false)
const facturaActiva     = ref(null)
const resultadosProducto = ref([])
const busquedaProducto  = ref('')

const filtros = ref({ search: '', desde: '', hasta: '' })
let currentPage = 1

const headers = [
  { title: 'Folio',     key: 'folio',         width: 80 },
  { title: 'Proveedor', key: 'nombre_emisor',  sortable: false },
  { title: 'RUT',       key: 'rut_emisor',     sortable: false, width: 120 },
  { title: 'Emisión',   key: 'fecha_emision',  width: 110 },
  { title: 'Neto',      key: 'neto',           width: 120 },
  { title: 'Total',     key: 'total',          width: 130 },
  { title: 'Estado',    key: 'estado',         width: 110, sortable: false },
  { title: '',          key: 'acciones',       width: 60,  sortable: false },
]

function formatNum(n) {
  return Number(n ?? 0).toLocaleString('es-CL')
}

function formatFecha(f) {
  if (!f) return '—'
  // Normalizar: si es solo fecha (YYYY-MM-DD) agregar hora para evitar desfase UTC
  const d = /^\d{4}-\d{2}-\d{2}$/.test(f) ? new Date(f + 'T12:00:00') : new Date(f)
  return d.toLocaleDateString('es-CL')
}

function colorEstado(estado) {
  const mapa = { ACD: 'success', RCD: 'error', ERM: 'info', PAG: 'secondary' }
  return mapa[estado] ?? 'default'
}

async function fetchCompras(page = 1) {
  loading.value = true
  currentPage   = page
  try {
    const params = { page, search: filtros.value.search, desde: filtros.value.desde, hasta: filtros.value.hasta }
    const { data } = await axiosInstance.get(API, { params })
    compras.value     = data.data
    totalCompras.value = data.total
  } catch (e) {
    console.error('Error cargando compras', e)
  } finally {
    loading.value = false
  }
}

function onTableOptions({ page }) {
  fetchCompras(page)
}

async function sincronizar() {
  sincronizando.value = true
  syncResult.value    = null
  syncProgress.value  = null

  let totalNuevas    = 0
  let totalErrores   = 0
  let totalBsale     = 0
  let rondas         = 0
  const esBulk       = maxSincronizar.value === 0

  try {
    do {
      rondas++
      if (esBulk) syncProgress.value = { nuevas: totalNuevas, rondas }

      const { data } = await axiosInstance.post(`${API}/sincronizar`, { max: maxSincronizar.value })
      totalNuevas  += data.nuevas  ?? 0
      totalErrores += data.errores ?? 0
      totalBsale    = data.total_bsale ?? totalBsale

      if (!data.has_more || !esBulk) break
    } while (true)

    syncResult.value   = { nuevas: totalNuevas, errores: totalErrores, total_bsale: totalBsale }
    syncProgress.value = null
    fetchCompras()

    // Contar XMLs pendientes solo si no es bulk (en bulk no se traen XMLs)
    if (!esBulk) {
      const r = await axiosInstance.post(`${API}/cargar-xmls-pendientes`, { lote: 0 })
      xmlRestantes.value = r.data.restantes ?? 0
    }
  } catch (e) {
    console.error('Error sincronizando', e)
    syncProgress.value = null
  } finally {
    sincronizando.value = false
  }
}

async function verDetalle(factura) {
  facturaActiva.value = factura
  dialogDetalle.value = true
  if (!factura.items) {
    const { data } = await axiosInstance.get(`${API}/${factura.id}`)
    facturaActiva.value = data
  }
}

async function cargarXmlsPendientes() {
  cargandoXmls.value = true
  try {
    const { data } = await axiosInstance.post(`${API}/cargar-xmls-pendientes`, { lote: 150 })
    xmlRestantes.value = data.restantes
    if (data.procesadas > 0) fetchCompras()
  } catch (e) {
    console.error('Error cargando XMLs', e)
  } finally {
    cargandoXmls.value = false
  }
}

async function cargarXml() {
  if (!facturaActiva.value) return
  cargandoXml.value = true
  try {
    const { data } = await axiosInstance.post(`${API}/${facturaActiva.value.id}/cargar-xml`)
    facturaActiva.value = { ...facturaActiva.value, items: data.items }
  } catch (e) {
    console.error('Error cargando XML', e)
  } finally {
    cargandoXml.value = false
  }
}

const buscarProducto = useDebounceFn(async () => {
  if (busquedaProducto.value.length < 2) {
    resultadosProducto.value = []
    return
  }
  loadingBusqueda.value = true
  try {
    const { data } = await axiosInstance.get(`${API}/buscar-producto`, { params: { q: busquedaProducto.value } })
    resultadosProducto.value = data.data
  } catch (e) {
    console.error('Error buscando producto', e)
  } finally {
    loadingBusqueda.value = false
  }
}, 400)

async function checkXmlPendientes() {
  try {
    const { data } = await axiosInstance.post(`${API}/cargar-xmls-pendientes`, { lote: 0 })
    xmlRestantes.value = data.restantes ?? 0
  } catch {}
}

onMounted(() => {
  fetchCompras()
  checkXmlPendientes()
})
</script>
