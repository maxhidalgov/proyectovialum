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
      <VTab value="alertas">
        Alertas de Precio
        <VBadge v-if="alertas.length" :content="alertas.length" color="error" inline class="ml-1" />
      </VTab>
      <VTab value="sincodigo" @click="cargarSinCodigo">
        Sin Código
        <VBadge v-if="sinCodigo.length" :content="sinCodigo.length" color="warning" inline class="ml-1" />
      </VTab>
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
            <div class="panel-title-inner w-100 pr-2">
              <div class="panel-title-top">
                <span class="font-weight-medium panel-title-name">{{ prod.nombre }}</span>
                <VBtn
                  size="x-small"
                  color="success"
                  variant="tonal"
                  icon="mdi-plus"
                  title="Agregar a Productos"
                  class="panel-title-btn"
                  @click.stop="abrirAgregarProducto(prod)"
                />
              </div>
              <div class="panel-title-info text-caption">
                <span>
                  <span class="text-medium-emphasis">P. Neto:</span>
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
            <div style="overflow-x:auto">
            <VTable density="compact" style="min-width:600px">
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
            </div>
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </div>

    <!-- TAB: ALERTAS DE PRECIO -->
    <div v-if="tab === 'alertas'">
      <div class="d-flex align-center gap-3 mb-4 flex-wrap">
        <VBtn color="primary" prepend-icon="mdi-link-variant" :loading="matcheando" @click="ejecutarMatch">
          Ejecutar Match
        </VBtn>
        <VBtn color="secondary" variant="tonal" prepend-icon="mdi-refresh" :loading="loadingAlertas" @click="cargarAlertas">
          Actualizar alertas
        </VBtn>
        <div class="text-caption text-medium-emphasis">
          El match vincula automáticamente las líneas de compra con tus productos por código de proveedor.
        </div>
      </div>

      <VAlert v-if="matchResult" type="success" class="mb-4" closable @click:close="matchResult = null">
        Match completado: <strong>{{ matchResult.vinculados }}</strong> líneas vinculadas.
        Total vinculadas: <strong>{{ matchResult.total_vinculados }}</strong>.
        Sin match (sin código o código no registrado): <strong>{{ matchResult.sin_match }}</strong>.
      </VAlert>

      <div v-if="loadingAlertas" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="alertas.length">
        <div class="text-subtitle-2 mb-2">
          {{ alertas.length }} productos con precio de compra distinto al costo registrado (diferencia > 3%)
        </div>
        <VCard>
          <VTable density="compact">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Color</th>
                <th>Proveedor</th>
                <th class="text-right">Precio compra</th>
                <th class="text-right">Costo en BD</th>
                <th class="text-right">Diferencia</th>
                <th>Folio</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in alertas" :key="a.compra_item_id">
                <td class="text-caption">{{ formatFecha(a.fecha) }}</td>
                <td class="font-weight-medium">{{ a.producto }}</td>
                <td>{{ a.color }}</td>
                <td class="text-caption">{{ a.proveedor }}</td>
                <td class="text-right text-success font-weight-medium">${{ formatNum(a.precio_compra) }}</td>
                <td class="text-right">${{ formatNum(a.costo_bd) }}</td>
                <td class="text-right">
                  <VChip
                    size="x-small"
                    :color="a.diferencia_pct > 0 ? 'error' : 'success'"
                    variant="tonal"
                  >
                    {{ a.diferencia_pct > 0 ? '+' : '' }}{{ a.diferencia_pct }}%
                  </VChip>
                </td>
                <td>
                  <a v-if="a.pdf_url" :href="a.pdf_url" target="_blank" class="text-primary" style="text-decoration:none">
                    {{ a.folio }} <VIcon size="11">mdi-open-in-new</VIcon>
                  </a>
                  <span v-else>{{ a.folio }}</span>
                </td>
                <td>
                  <VBtn size="x-small" color="primary" variant="tonal" @click="actualizarCosto(a)">
                    Actualizar costo
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </template>

      <div v-else-if="!loadingAlertas && alertas.length === 0 && matchResult !== null" class="text-center py-8 text-medium-emphasis">
        <VIcon size="48" class="mb-2">mdi-check-circle</VIcon>
        <div>Sin alertas de precio. Todos los costos están actualizados.</div>
      </div>

      <div v-else class="text-center py-8 text-medium-emphasis">
        Ejecuta el match primero para detectar cambios de precio.
      </div>
    </div>

    <!-- TAB: SIN CÓDIGO -->
    <div v-if="tab === 'sincodigo'">
      <div class="d-flex align-center gap-3 mb-4">
        <VTextField
          v-model="filtroProd"
          placeholder="Filtrar por producto, color o proveedor..."
          prepend-inner-icon="mdi-magnify"
          clearable hide-details variant="outlined" density="compact"
          style="max-width:400px"
        />
        <span class="text-caption text-medium-emphasis">{{ sinCodigoFiltrado.length }} productos sin código</span>
      </div>

      <div v-if="loadingSinCodigo" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <VCard v-else-if="sinCodigoFiltrado.length">
        <VTable density="compact">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Color</th>
              <th>Proveedor</th>
              <th class="text-right">Costo</th>
              <th style="min-width:280px">Buscar en compras</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in sinCodigoFiltrado" :key="p.pcp_id">
              <td class="font-weight-medium">{{ p.producto }}</td>
              <td>{{ p.color }}</td>
              <td class="text-caption">{{ p.proveedor }}</td>
              <td class="text-right">${{ formatNum(p.costo) }}</td>
              <td>
                <VAutocomplete
                  v-model="p._itemSeleccionado"
                  :items="p._resultados ?? []"
                  :loading="p._buscando"
                  item-title="_label"
                  return-object
                  hide-details
                  density="compact"
                  variant="outlined"
                  placeholder="Buscar línea de compra..."
                  no-filter
                  clearable
                  @update:search="q => buscarItemParaPcp(p, q)"
                />
              </td>
              <td>
                <VBtn
                  size="small"
                  color="primary"
                  variant="tonal"
                  :disabled="!p._itemSeleccionado"
                  :loading="p._guardando"
                  @click="asignarCodigo(p)"
                >
                  Asignar
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>

      <div v-else class="text-center py-8 text-medium-emphasis">
        <VIcon size="48" class="mb-2">mdi-check-circle</VIcon>
        <div>Todos los productos tienen código asignado.</div>
      </div>
    </div>

    <!-- DIALOG AGREGAR A PRODUCTOS -->
    <VDialog v-model="dialogAgregar" max-width="560" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span>Agregar a Productos</span>
          <VBtn icon="mdi-close" variant="text" size="small" @click="dialogAgregar = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VRow dense>
            <VCol cols="12">
              <VTextField
                v-model="formProducto.nombre"
                label="Nombre del producto *"
                variant="outlined"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="formProducto.tipo_producto_id"
                :items="tiposProducto"
                item-title="nombre"
                item-value="id"
                label="Tipo de producto"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="formProducto.unidad_id"
                :items="unidades"
                item-title="nombre"
                item-value="id"
                label="Unidad"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </VCol>
            <VCol cols="12">
              <VDivider class="my-1" />
              <div class="text-caption text-medium-emphasis mb-2">Combinación Proveedor / Color / Costo</div>
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="formProducto.proveedor_id"
                :items="proveedores"
                item-title="nombre"
                item-value="id"
                label="Proveedor *"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VAutocomplete
                v-model="formProducto.color_id"
                :items="colores"
                item-title="nombre"
                item-value="id"
                label="Color *"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="formProducto.costo"
                label="Costo (neto) *"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                prefix="$"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formProducto.codigo_proveedor"
                label="Código proveedor"
                variant="outlined"
                density="compact"
                hide-details
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="formProducto.largo_total"
                label="Largo total (m)"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
              />
            </VCol>
          </VRow>
          <VAlert v-if="errorAgregar" type="error" class="mt-3" density="compact">{{ errorAgregar }}</VAlert>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogAgregar = false">Cancelar</VBtn>
          <VBtn
            color="success"
            :loading="guardandoProducto"
            :disabled="!formProducto.nombre || !formProducto.proveedor_id || !formProducto.color_id || !formProducto.costo"
            @click="guardarProducto"
          >
            Guardar producto
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
const maxSincronizar    = ref('smart')
const opcionesSinc      = [
  { label: 'Solo nuevas',  value: 'smart' },
  { label: 'Últimas 20',   value: 20      },
  { label: 'Últimas 50',   value: 50      },
  { label: 'Últimas 100',  value: 100     },
  { label: 'Todas',        value: 0       },
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

// Sin código
const sinCodigo        = ref([])
const loadingSinCodigo = ref(false)
const filtroProd       = ref('')

const sinCodigoFiltrado = computed(() => {
  const q = filtroProd.value.toLowerCase()
  if (!q) return sinCodigo.value
  return sinCodigo.value.filter(p =>
    (p.producto ?? '').toLowerCase().includes(q) ||
    (p.color ?? '').toLowerCase().includes(q) ||
    (p.proveedor ?? '').toLowerCase().includes(q)
  )
})

async function cargarSinCodigo() {
  if (sinCodigo.value.length) return
  loadingSinCodigo.value = true
  try {
    const { data } = await axiosInstance.get(`${API}/sin-codigo`)
    sinCodigo.value = data.data.map(p => ({ ...p, _itemSeleccionado: null, _resultados: [], _buscando: false, _guardando: false }))
  } catch (e) {
    console.error('Error cargando sin código', e)
  } finally {
    loadingSinCodigo.value = false
  }
}

const _debounceTimers = {}
function buscarItemParaPcp(pcp, q) {
  if (!q || q.length < 2) { pcp._resultados = []; return }
  clearTimeout(_debounceTimers[pcp.pcp_id])
  _debounceTimers[pcp.pcp_id] = setTimeout(async () => {
    pcp._buscando = true
    try {
      const { data } = await axiosInstance.get(`${API}/buscar-producto`, { params: { q } })
      pcp._resultados = data.data.flatMap(prod =>
        prod.historial.filter(h => h.codigo).map(h => ({
          _label: `[${h.codigo}] ${prod.nombre} — ${h.proveedor} (${formatFecha(h.fecha)})`,
          codigo: h.codigo,
          nombre: prod.nombre,
        }))
      ).filter((v, i, arr) => arr.findIndex(x => x.codigo === v.codigo) === i)
    } finally {
      pcp._buscando = false
    }
  }, 350)
}

async function asignarCodigo(pcp) {
  if (!pcp._itemSeleccionado) return
  pcp._guardando = true
  try {
    await axiosInstance.patch(`${API}/asignar-codigo`, {
      pcp_id: pcp.pcp_id,
      codigo: pcp._itemSeleccionado.codigo,
    })
    sinCodigo.value = sinCodigo.value.filter(p => p.pcp_id !== pcp.pcp_id)
  } catch (e) {
    console.error('Error asignando código', e)
  } finally {
    pcp._guardando = false
  }
}

// Alertas de precio / match
const alertas        = ref([])
const matchResult    = ref(null)
const matcheando     = ref(false)
const loadingAlertas = ref(false)

async function ejecutarMatch() {
  matcheando.value = true
  matchResult.value = null
  try {
    const { data } = await axiosInstance.post(`${API}/matchear`)
    matchResult.value = data
    await cargarAlertas()
  } catch (e) {
    console.error('Error en match', e)
  } finally {
    matcheando.value = false
  }
}

async function cargarAlertas() {
  loadingAlertas.value = true
  try {
    const { data } = await axiosInstance.get(`${API}/alertas-precio`)
    alertas.value = data.data
  } catch (e) {
    console.error('Error cargando alertas', e)
  } finally {
    loadingAlertas.value = false
  }
}

async function actualizarCosto(alerta) {
  try {
    await axiosInstance.patch(`${API}/actualizar-costo`, { pcp_id: alerta.pcp_id, costo: alerta.precio_compra })
    alertas.value = alertas.value.filter(a => a.compra_item_id !== alerta.compra_item_id)
  } catch (e) {
    console.error('Error actualizando costo', e)
  }
}

// Agregar a productos
const dialogAgregar     = ref(false)
const guardandoProducto = ref(false)
const errorAgregar      = ref(null)
const proveedores       = ref([])
const colores           = ref([])
const tiposProducto     = ref([])
const unidades          = ref([])
const formProducto      = ref({})

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
  const esSmart      = maxSincronizar.value === 'smart'
  const esBulk       = maxSincronizar.value === 0

  try {
    do {
      rondas++
      if (esBulk) syncProgress.value = { nuevas: totalNuevas, rondas }

      const payload = esSmart
        ? { smart: true }
        : { max: maxSincronizar.value }
      const { data } = await axiosInstance.post(`${API}/sincronizar`, payload)
      totalNuevas  += data.nuevas  ?? 0
      totalErrores += data.errores ?? 0
      totalBsale    = data.total_bsale ?? totalBsale

      if (!data.has_more || (!esBulk && !esSmart)) break
    } while (true)

    syncResult.value   = { nuevas: totalNuevas, errores: totalErrores, total_bsale: totalBsale }
    syncProgress.value = null
    fetchCompras()

    // Contar XMLs pendientes solo si no es bulk
    if (!esBulk || esSmart) {
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

async function abrirAgregarProducto(prod) {
  errorAgregar.value = null
  formProducto.value = {
    nombre:           prod.nombre,
    tipo_producto_id: null,
    unidad_id:        null,
    proveedor_id:     null,
    color_id:         null,
    costo:            prod.ultimo_precio_neto,
    codigo_proveedor: prod.historial?.[0]?.codigo ?? '',
    largo_total:      null,
  }
  dialogAgregar.value = true

  // Cargar catálogos si aún no están cargados
  if (!proveedores.value.length) {
    const [p, c, t, u] = await Promise.all([
      axiosInstance.get('/api/proveedores'),
      axiosInstance.get('/api/colores'),
      axiosInstance.get('/api/tipos_producto'),
      axiosInstance.get('/api/unidades'),
    ])
    proveedores.value   = p.data
    colores.value       = c.data
    tiposProducto.value = t.data
    unidades.value      = u.data
  }

  // Pre-seleccionar proveedor si el nombre coincide
  const nombreFactura = (prod.proveedor ?? '').toLowerCase()
  const match = proveedores.value.find(p =>
    nombreFactura.includes(p.nombre.toLowerCase()) ||
    p.nombre.toLowerCase().includes(nombreFactura.split(' ')[0])
  )
  if (match) formProducto.value.proveedor_id = match.id
}

async function guardarProducto() {
  guardandoProducto.value = true
  errorAgregar.value      = null
  try {
    await axiosInstance.post('/api/productos', {
      nombre:           formProducto.value.nombre,
      tipo_producto_id: formProducto.value.tipo_producto_id,
      unidad_id:        formProducto.value.unidad_id,
      largo_total:      formProducto.value.largo_total,
      producto_color_proveedor: [{
        proveedor_id:     formProducto.value.proveedor_id,
        color_id:         formProducto.value.color_id,
        costo:            formProducto.value.costo,
        codigo_proveedor: formProducto.value.codigo_proveedor,
      }],
    })
    dialogAgregar.value = false
  } catch (e) {
    errorAgregar.value = e.response?.data?.message ?? 'Error al guardar el producto'
  } finally {
    guardandoProducto.value = false
  }
}

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

<style scoped>
/* Desktop: nombre + info en una sola fila */
.panel-title-inner  { display: flex; align-items: center; gap: 12px; }
.panel-title-top    { display: contents; }
.panel-title-name   { flex-shrink: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.panel-title-info   { display: flex; align-items: center; flex-shrink: 0; gap: 16px; }
.panel-title-btn    { flex-shrink: 0; }

/* Móvil: nombre arriba, info abajo wrapping */
@media (max-width: 767px) {
  .panel-title-inner  { flex-direction: column; align-items: flex-start; gap: 4px; }
  .panel-title-top    { display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 8px; }
  .panel-title-name   { white-space: normal; overflow: visible; text-overflow: unset; }
  .panel-title-info   { flex-wrap: wrap; gap: 4px 12px; }
}
</style>
