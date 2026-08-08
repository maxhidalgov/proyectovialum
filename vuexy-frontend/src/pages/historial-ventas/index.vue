<template>
  <VContainer fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VIcon icon="mdi-cart-outline" size="30" color="teal" />
      <div>
        <h1 class="text-h5 font-weight-bold">Ventas</h1>
        <p class="text-caption text-grey mt-1">Busca una boleta o factura y mira qué se vendió</p>
      </div>
    </div>

    <VTabs v-model="tab" class="mb-4">
      <VTab value="docs">Buscar boleta / factura</VTab>
      <VTab value="top">Top productos</VTab>
      <VTab value="prod">Historial por producto</VTab>
    </VTabs>

    <!-- ── TAB: TOP PRODUCTOS ─────────────────────────────────────────────── -->
    <div v-if="tab === 'top'">
      <VCard variant="outlined" class="mb-3">
        <VCardText>
          <VRow dense align="center">
            <VCol cols="6" sm="3"><VTextField v-model="topF.desde" type="date" label="Desde" density="compact" variant="outlined" hide-details @update:model-value="cargarTop" /></VCol>
            <VCol cols="6" sm="3"><VTextField v-model="topF.hasta" type="date" label="Hasta" density="compact" variant="outlined" hide-details @update:model-value="cargarTop" /></VCol>
            <VCol cols="6" sm="3"><VSelect v-model="topF.tipo" :items="canales" label="Canal" density="compact" variant="outlined" hide-details @update:model-value="cargarTop" /></VCol>
            <VCol cols="6" sm="3"><VSelect v-model="topF.orden" :items="ordenes" label="Ordenar por" density="compact" variant="outlined" hide-details @update:model-value="cargarTop" /></VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard variant="outlined">
        <VDataTable :headers="headersTop" :items="topItems" :loading="loadingTop" density="compact" :items-per-page="100">
          <template #item.rank="{ index }">
            <span class="font-weight-bold text-medium-emphasis">{{ index + 1 }}</span>
          </template>
          <template #item.unidades="{ item }">{{ fmtNum(item.unidades) }}</template>
          <template #item.ingreso="{ item }"><span class="font-weight-bold">{{ clp(item.ingreso) }}</span></template>
          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <VIcon size="40" class="mb-2">mdi-trophy-outline</VIcon>
              <p>Sin datos en el rango. Ajusta las fechas o el canal.</p>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- ── TAB: BUSCAR DOCUMENTO ──────────────────────────────────────────── -->
    <div v-if="tab === 'docs'">
      <VCard variant="outlined" class="mb-3">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="4">
              <VTextField v-model="docF.q" label="Folio, cliente o RUT" prepend-inner-icon="mdi-magnify"
                          density="compact" variant="outlined" hide-details clearable @update:model-value="buscarDocsDeb" />
            </VCol>
            <VCol cols="6" sm="3">
              <VSelect v-model="docF.tipo" :items="tiposDocFiltro" label="Tipo" density="compact" variant="outlined"
                       hide-details clearable @update:model-value="buscarDocs" />
            </VCol>
            <VCol cols="6" sm="2">
              <VTextField v-model="docF.desde" type="date" label="Desde" density="compact" variant="outlined" hide-details @update:model-value="buscarDocs" />
            </VCol>
            <VCol cols="6" sm="2">
              <VTextField v-model="docF.hasta" type="date" label="Hasta" density="compact" variant="outlined" hide-details @update:model-value="buscarDocs" />
            </VCol>
            <VCol cols="6" sm="1" class="d-flex align-center">
              <VBtn color="primary" variant="tonal" block :loading="loadingDocs" @click="buscarDocs">Ir</VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard variant="outlined">
        <VDataTable :headers="headersDocs" :items="docs" :loading="loadingDocs" density="compact" items-per-page="50">
          <template #item.tipo_documento_bsale_id="{ item }">
            <VChip size="x-small" variant="tonal"
                   :color="item.tipo_documento_bsale_id === 1 ? 'secondary' : (item.tipo_documento_bsale_id === 2 ? 'error' : 'info')">
              {{ tipoDoc(item.tipo_documento_bsale_id) }}{{ item.numero_documento_bsale ? ' ' + item.numero_documento_bsale : '' }}
            </VChip>
          </template>
          <template #item.fecha_emision="{ item }">{{ fmtFecha(item.fecha_emision) }}</template>
          <template #item.monto="{ item }">{{ clp(item.monto) }}</template>
          <template #item.forma_pago="{ item }">
            <VChip size="x-small" variant="tonal" color="primary" style="cursor:pointer" @click="abrirEditarPago(item)">
              {{ formaPagoLabel(item.forma_pago) }}
              <span v-if="item.nro_comprobante_transbank" class="ml-1">· {{ item.nro_comprobante_transbank }}</span>
              <VIcon end size="11">mdi-pencil</VIcon>
            </VChip>
          </template>
          <template #item.acciones="{ item }">
            <VBtn size="x-small" variant="tonal" color="primary" @click="verDetalle(item)">
              <VIcon start size="14">mdi-eye-outline</VIcon>Ver
            </VBtn>
            <VBtn v-if="item.url_pdf_bsale || item.id_documento_bsale" size="x-small" variant="text" icon :href="`/boleta/${item.id}/pdf`" target="_blank" class="ml-1" title="Ver PDF (formato completo)">
              <VIcon size="16">mdi-file-pdf-box</VIcon>
            </VBtn>
          </template>
          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <VIcon size="40" class="mb-2">mdi-receipt-text-outline</VIcon>
              <p>Busca por folio, cliente o fecha para ver las boletas y facturas.</p>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- ── TAB: HISTORIAL POR PRODUCTO ────────────────────────────────────── -->
    <div v-else>
      <div class="d-flex align-center gap-3 mb-3 flex-wrap">
        <VSpacer />
        <VChip v-if="pendientes !== null" :color="pendientes > 0 ? 'warning' : 'success'" variant="tonal" size="small">
          {{ pendientes > 0 ? `${pendientes} ventas sin importar` : 'Histórico al día' }}
        </VChip>
        <VBtn variant="tonal" color="teal" prepend-icon="mdi-cloud-download" :loading="importando"
              :disabled="pendientes === 0" @click="importarHistorico">
          {{ importando ? `Importando… ${importProgreso}` : 'Importar histórico Bsale' }}
        </VBtn>
      </div>

      <VCard variant="outlined" class="mb-3">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="5">
              <VTextField v-model="filtros.cliente" label="Cliente (nombre o RUT)" prepend-inner-icon="mdi-account-search"
                          density="compact" variant="outlined" hide-details clearable @update:model-value="buscarDebounced" />
            </VCol>
            <VCol cols="12" sm="5">
              <VTextField v-model="filtros.q" label="Producto" prepend-inner-icon="mdi-magnify"
                          density="compact" variant="outlined" hide-details clearable @update:model-value="buscarDebounced" />
            </VCol>
            <VCol cols="12" sm="2" class="d-flex align-center">
              <VBtn color="primary" variant="tonal" block @click="cargar">Buscar</VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VAlert v-if="importMsg" type="info" density="compact" variant="tonal" closable class="mb-3" @click:close="importMsg = ''">
        {{ importMsg }}
      </VAlert>

      <VCard variant="outlined">
        <VDataTable :headers="headers" :items="filas" :loading="loading" density="compact" items-per-page="50">
          <template #item.fecha_emision="{ item }">{{ fmtFecha(item.fecha_emision) }}</template>
          <template #item.tipo_documento_bsale_id="{ item }">
            <VChip size="x-small" :color="item.tipo_documento_bsale_id === 1 ? 'secondary' : (item.tipo_documento_bsale_id === 2 ? 'error' : 'info')" variant="tonal">
              {{ tipoDoc(item.tipo_documento_bsale_id) }}{{ item.numero_documento_bsale ? ' ' + item.numero_documento_bsale : '' }}
            </VChip>
          </template>
          <template #item.cantidad="{ item }">{{ fmtNum(item.cantidad) }}</template>
          <template #item.precio_unitario="{ item }">
            <span class="font-weight-bold">{{ clp(item.precio_unitario) }}</span><span class="text-caption text-grey"> neto</span>
          </template>
          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <VIcon size="40" class="mb-2">mdi-history</VIcon>
              <p>Sin resultados. Si es la primera vez, usa "Importar histórico Bsale" para traer las ventas pasadas.</p>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- ── Dialog: detalle del documento (qué se vendió) ──────────────────── -->
    <VDialog v-model="detalle.show" max-width="660">
      <VCard v-if="detalle.doc">
        <VCardTitle class="d-flex align-center gap-2">
          <VChip size="small" variant="tonal"
                 :color="detalle.doc.tipo_documento_bsale_id === 1 ? 'secondary' : (detalle.doc.tipo_documento_bsale_id === 2 ? 'error' : 'info')">
            {{ tipoDoc(detalle.doc.tipo_documento_bsale_id) }} {{ detalle.doc.numero_documento_bsale }}
          </VChip>
        </VCardTitle>
        <VCardText>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ detalle.doc.cliente || 'Consumidor Final' }} · {{ fmtFecha(detalle.doc.fecha_emision) }} · {{ clp(detalle.doc.monto) }}
          </div>
          <VDataTable :headers="headersItems" :items="detalle.items" :loading="detalle.loading" density="compact" hide-default-footer :items-per-page="-1">
            <template #item.cantidad="{ item }">{{ fmtNum(item.cantidad) }}</template>
            <template #item.precio_unitario="{ item }">{{ clp(item.precio_unitario) }}</template>
            <template #item.total_neto="{ item }">{{ clp(item.total_neto) }}</template>
            <template #no-data>
              <div class="pa-4 text-center text-caption text-medium-emphasis">
                Sin líneas registradas. Si es una boleta antigua de Bsale, usa "Importar histórico Bsale" (pestaña Historial por producto) para traer el detalle.
              </div>
            </template>
          </VDataTable>
        </VCardText>
        <VCardActions>
          <VBtn v-if="detalle.doc.url_pdf_bsale || detalle.doc.id_documento_bsale" variant="tonal" color="error" prepend-icon="mdi-file-pdf-box" :href="`/boleta/${detalle.doc.id}/pdf`" target="_blank">Ver PDF</VBtn>
          <VSpacer />
          <VBtn variant="text" @click="detalle.show = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: editar forma de pago + voucher ─────────────────────────── -->
    <VDialog v-model="editPago.show" max-width="420">
      <VCard v-if="editPago.doc">
        <VCardTitle>Editar pago</VCardTitle>
        <VCardText>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ tipoDoc(editPago.doc.tipo_documento_bsale_id) }} {{ editPago.doc.numero_documento_bsale }}
          </div>
          <VSelect v-model="editPago.forma" :items="formasPagoEdit" item-title="label" item-value="value"
                   label="Forma de pago" variant="outlined" density="compact" class="mb-3" hide-details />
          <VTextField v-if="esTarjetaEdit" v-model="editPago.voucher" label="N° voucher Transbank"
                      variant="outlined" density="compact" hide-details />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="editPago.show = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="editPago.loading" @click="guardarPago">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">{{ snack.msg }}</VSnackbar>
  </VContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'

const tab = ref('docs')
const snack = ref({ show: false, color: 'success', msg: '' })

// ── Buscar documento ───────────────────────────────────────────────────────
const docs = ref([])
const loadingDocs = ref(false)
const docF = ref({ q: '', tipo: null, desde: '', hasta: '' })
const tiposDocFiltro = [
  { title: 'Boleta', value: 1 },
  { title: 'Factura', value: 5 },
  { title: 'Nota de crédito', value: 2 },
]
const headersDocs = [
  { title: 'Documento', key: 'tipo_documento_bsale_id' },
  { title: 'Fecha', key: 'fecha_emision' },
  { title: 'Cliente', key: 'cliente' },
  { title: 'Monto', key: 'monto', align: 'end' },
  { title: 'Forma pago', key: 'forma_pago' },
  { title: '', key: 'acciones', align: 'end', sortable: false },
]

async function buscarDocs() {
  loadingDocs.value = true
  try {
    const { data } = await api.get('/api/ventas/buscar-documentos', {
      params: {
        q: docF.value.q || undefined,
        tipo: docF.value.tipo || undefined,
        desde: docF.value.desde || undefined,
        hasta: docF.value.hasta || undefined,
      },
    })
    docs.value = Array.isArray(data) ? data : []
  } catch {
    docs.value = []
  } finally {
    loadingDocs.value = false
  }
}
let docTimer = null
function buscarDocsDeb() { clearTimeout(docTimer); docTimer = setTimeout(buscarDocs, 400) }

// Editar forma de pago de un documento (boleta/factura)
const formasPagoEdit = [
  { label: 'Efectivo',        value: 'efectivo' },
  { label: 'Transferencia',   value: 'transferencia' },
  { label: 'Tarjeta Débito',  value: 'tarjeta_debito' },
  { label: 'Tarjeta Crédito', value: 'tarjeta_credito' },
  { label: 'Cheque',          value: 'cheque' },
  { label: 'Webpay',          value: 'webpay' },
]
function formaPagoLabel(v) {
  return formasPagoEdit.find(f => f.value === v)?.label || (v || 'Sin forma')
}

// Diálogo Editar pago (forma de pago + voucher si es tarjeta)
const editPago = ref({ show: false, loading: false, doc: null, forma: '', voucher: '' })
const esTarjetaEdit = computed(() => ['tarjeta_debito', 'tarjeta_credito'].includes(editPago.value.forma))

function abrirEditarPago(doc) {
  editPago.value = { show: true, loading: false, doc, forma: doc.forma_pago || 'efectivo', voucher: doc.nro_comprobante_transbank || '' }
}

async function guardarPago() {
  editPago.value.loading = true
  try {
    const body = { forma_pago: editPago.value.forma }
    if (esTarjetaEdit.value) body.voucher = editPago.value.voucher || ''
    await api.patch(`/api/ventas/${editPago.value.doc.id}/forma-pago`, body)
    editPago.value.doc.forma_pago = editPago.value.forma
    editPago.value.doc.nro_comprobante_transbank = esTarjetaEdit.value ? (editPago.value.voucher || null) : null
    editPago.value.show = false
    snack.value = { show: true, color: 'success', msg: 'Pago actualizado' }
  } catch (e) {
    snack.value = { show: true, color: 'error', msg: e.response?.data?.error || 'No se pudo actualizar' }
  } finally {
    editPago.value.loading = false
  }
}

// Detalle (líneas) de un documento
const detalle = ref({ show: false, loading: false, doc: null, items: [] })
async function verDetalle(doc) {
  detalle.value = { show: true, loading: true, doc, items: [] }
  try {
    const { data } = await api.get(`/api/ventas/documento/${doc.id}/items`)
    detalle.value.items = Array.isArray(data) ? data : []
  } catch {
    detalle.value.items = []
  } finally {
    detalle.value.loading = false
  }
}
const headersItems = [
  { title: 'Producto', key: 'nombre' },
  { title: 'Cant.', key: 'cantidad', align: 'end' },
  { title: 'P. Unit. (neto)', key: 'precio_unitario', align: 'end' },
  { title: 'Total (neto)', key: 'total_neto', align: 'end' },
]

// ── Top productos ──────────────────────────────────────────────────────────
const topItems = ref([])
const loadingTop = ref(false)
const _hoy = new Date().toISOString().slice(0, 10)
const _inicioAnio = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10)
const topF = ref({ desde: _inicioAnio, hasta: _hoy, canal: null, orden: 'ingreso' })
const canales = [
  { title: 'Todos', value: null },
  { title: 'Mostrador (consumidor final)', value: 'final' },
  { title: 'Con cliente registrado', value: 'cliente' },
]
const ordenes = [
  { title: 'Ingreso ($)', value: 'ingreso' },
  { title: 'Unidades', value: 'unidades' },
]
const headersTop = [
  { title: '#', key: 'rank', sortable: false, width: 48 },
  { title: 'Producto', key: 'producto' },
  { title: 'Unidades', key: 'unidades', align: 'end' },
  { title: 'Ingreso neto', key: 'ingreso', align: 'end' },
  { title: 'N° docs', key: 'documentos', align: 'end' },
]

async function cargarTop() {
  loadingTop.value = true
  try {
    const { data } = await api.get('/api/ventas/top-productos', {
      params: {
        desde: topF.value.desde || undefined,
        hasta: topF.value.hasta || undefined,
        canal: topF.value.canal || undefined,
        orden: topF.value.orden,
      },
    })
    topItems.value = Array.isArray(data) ? data : []
  } catch {
    topItems.value = []
  } finally {
    loadingTop.value = false
  }
}

// ── Historial por producto (existente) ─────────────────────────────────────
const filas   = ref([])
const loading = ref(false)
const filtros = ref({ cliente: '', q: '' })
const headers = [
  { title: 'Fecha',     key: 'fecha_emision' },
  { title: 'Documento', key: 'tipo_documento_bsale_id' },
  { title: 'Cliente',   key: 'cliente' },
  { title: 'Producto',  key: 'producto' },
  { title: 'Cant.',     key: 'cantidad', align: 'end' },
  { title: 'P. Unit.',  key: 'precio_unitario', align: 'end' },
]

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/ventas/historial-productos', {
      params: { cliente: filtros.value.cliente || undefined, q: filtros.value.q || undefined },
    })
    filas.value = Array.isArray(data) ? data : []
  } catch {
    filas.value = []
  } finally {
    loading.value = false
  }
}
let debTimer = null
function buscarDebounced() { clearTimeout(debTimer); debTimer = setTimeout(cargar, 400) }

// Importar histórico
const importando = ref(false)
const importMsg = ref('')
const importProgreso = ref('')
const pendientes = ref(null)

async function cargarPendientes() {
  try {
    const { data } = await api.get('/api/ventas/lineas-pendientes')
    pendientes.value = data.pendientes
  } catch { pendientes.value = null }
}

async function importarHistorico() {
  importando.value = true
  importMsg.value = ''
  let totalImportados = 0
  try {
    while (true) {
      const { data } = await api.post('/api/ventas/importar-lineas', { limit: 40 })
      totalImportados += data.importados
      pendientes.value = data.pendientes
      importProgreso.value = `${totalImportados} docs · faltan ${data.pendientes}`
      if (data.importados === 0 || data.pendientes === 0) break
    }
    importMsg.value = `✓ Listo — ${totalImportados} documentos importados`
    await cargar()
  } catch (e) {
    importMsg.value = 'Error al importar: ' + (e.response?.data?.error || e.message)
  } finally {
    importando.value = false
    importProgreso.value = ''
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function tipoDoc(t) {
  return { 1: 'Boleta', 2: 'NC', 5: 'Factura', 3: 'Nota Venta', 4: 'Liquidación' }[t] || 'Doc'
}
function fmtFecha(f) {
  if (!f) return '—'
  return new Date(String(f).slice(0, 10) + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
const fmtNum = v => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(Number(v) || 0)
const clp = v => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

onMounted(() => {
  buscarDocs()
  cargarTop()
  cargar()
  cargarPendientes()
})
</script>
