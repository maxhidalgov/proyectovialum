<script setup>
import { ref, computed } from 'vue'
import api from '@/axiosInstance'
import logoVialum from '@/assets/images/logo-vialum.png'

// ── Componente compartido: emisión de documento (mostrador) ──────────────────
// Un solo componente en dos modos:
//   mode="venta"      → Boleta / Factura (entra plata). Botón "Realizar venta".
//   mode="cotizacion" → Cotización de productos/vidrios (no emite). Cliente obligatorio.
// PROTOTIPO: el botón principal NO emite todavía, solo muestra el resumen.
const props = defineProps({
  mode: { type: String, default: 'venta' }, // 'venta' | 'cotizacion'
})
const esCotizacion = computed(() => props.mode === 'cotizacion')

const IVA = 0.19
const CLP = n => '$' + Math.round(Number(n) || 0).toLocaleString('es-CL')
const miles = n => (Number(n) || 0).toLocaleString('es-CL', { maximumFractionDigits: 2 })
const parseMiles = v => {
  const s = String(v).replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '')
  return s === '' ? 0 : parseFloat(s)
}

const tipo  = ref(props.mode === 'cotizacion' ? 'cotizacion' : 'boleta') // boleta | factura | cotizacion
const fecha = ref(new Date().toISOString().slice(0, 10))

// ── Cliente ─────────────────────────────────────────────────────────────────
const cliente       = ref(null)
const clienteSearch = ref('')
const clientes      = ref([])
let cliTimer

function buscarClientes(q) {
  clearTimeout(cliTimer)
  if (!q || q.length < 2) { clientes.value = []; return }
  cliTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/clientes/buscar', { params: { q } })
      clientes.value = data
    } catch { clientes.value = [] }
  }, 300)
}

// Crear cliente (prototipo: lo agrega localmente; el módulo real llama a Bsale)
const nuevoCli = ref({ show: false, razon_social: '', identification: '', direccion: '', comuna: '', ciudad: '' })
function abrirNuevoCliente() {
  nuevoCli.value = { show: true, razon_social: clienteSearch.value || '', identification: '', direccion: '', comuna: '', ciudad: '' }
}
function guardarCliente() {
  const c = nuevoCli.value
  cliente.value = { razon_social: c.razon_social, identification: c.identification, direccion: [c.direccion, c.comuna, c.ciudad].filter(Boolean).join(', ') }
  nuevoCli.value.show = false
}

// ── Productos ────────────────────────────────────────────────────────────────
const prodSearch  = ref('')
const prodResults = ref([])
const prodLoading = ref(false)
let prodTimer

function buscarProductos(q) {
  clearTimeout(prodTimer)
  if (!q || q.length < 2) { prodResults.value = []; return }
  prodLoading.value = true
  prodTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/venta-express/productos', { params: { q } })
      prodResults.value = data
    } catch { prodResults.value = [] }
    finally { prodLoading.value = false }
  }, 300)
}

function agregarProducto(p) {
  if (!p) return
  prodSearch.value = ''
  prodResults.value = []
  if (p.es_vidrio) {
    medidas.value = { show: true, producto: p, ancho: null, alto: null, piezas: 1, pulido: false }
    return
  }
  items.value.push({ nombre: p.nombre, cantidad: 1, precio: p.precio_venta, costo: p.costo, descuento: 0, producto_id: p.producto_id, es_vidrio: false })
}
function agregarManual() {
  items.value.push({ nombre: 'Nuevo ítem', cantidad: 1, precio: 0, costo: 0, descuento: 0, producto_id: null, es_vidrio: false })
}
function quitar(i) { items.value.splice(i, 1) }

// ── Vidrio: medidas por m² (igual que Venta Express) ─────────────────────────
const PULIDO_PCT = 0.20
const medidas = ref({ show: false, producto: null, ancho: null, alto: null, piezas: 1, pulido: false })
const m2Calc = computed(() => {
  const a = Number(medidas.value.ancho) || 0
  const al = Number(medidas.value.alto) || 0
  const pz = Number(medidas.value.piezas) || 0
  return +((a / 1000) * (al / 1000) * pz).toFixed(4)
})
const precioM2 = computed(() => {
  const base = medidas.value.producto?.precio_venta || 0
  return Math.round(base * (medidas.value.pulido ? 1 + PULIDO_PCT : 1))
})
function confirmarMedidas() {
  const m = medidas.value
  if (!(Number(m.ancho) > 0) || !(Number(m.alto) > 0) || !(Number(m.piezas) > 0)) return
  items.value.push({
    nombre: m.producto.nombre + (m.pulido ? ' · con pulido' : '') + ` (${m.ancho}×${m.alto}mm ×${m.piezas})`,
    cantidad: m2Calc.value, precio: precioM2.value, costo: m.producto.costo, descuento: 0,
    producto_id: m.producto.producto_id, es_vidrio: true,
  })
  medidas.value.show = false
}

// ── Ítems ────────────────────────────────────────────────────────────────────
const items = ref([
  { nombre: 'Ventana PVC corredera 2 hojas 1200×1200', cantidad: 2, precio: 189900, costo: 120000, descuento: 0, producto_id: null, es_vidrio: false },
  { nombre: 'Kit instalación + silicona neutra',        cantidad: 1, precio: 24900,  costo: 12000,  descuento: 0, producto_id: null, es_vidrio: false },
])

const subtotal = it => (Number(it.cantidad) || 0) * (Number(it.precio) || 0) * (1 - (Number(it.descuento) || 0) / 100)
const neto  = computed(() => items.value.reduce((s, it) => s + subtotal(it), 0))
const iva   = computed(() => neto.value * IVA)
const total = computed(() => neto.value + iva.value)

const MARGEN_MIN = 0.20
const margen = it => {
  const c = Number(it.costo) || 0, p = Number(it.precio) || 0
  return c > 0 && p > 0 ? (p - c) / p : null
}

// ── Pago / opciones ───────────────────────────────────────────────────────────
const formaPago = ref('tarjeta_debito')
const voucher   = ref('')
const pagoLabels = { tarjeta_debito: 'Tarjeta débito', tarjeta_credito: 'Tarjeta crédito', transferencia: 'Transferencia', efectivo: 'Efectivo' }
const pagoItems = Object.entries(pagoLabels).map(([value, title]) => ({ value, title }))
const necesitaVoucher = computed(() => ['tarjeta_debito', 'tarjeta_credito'].includes(formaPago.value))

const optCorte = ref(false)
const optRef   = ref(false)
const optDesc  = ref(true)
const nota     = ref('')

const tipoHint = computed(() => {
  if (tipo.value === 'boleta')  return { color: 'info',    icon: 'mdi-receipt-text-outline', text: 'Boleta: cliente opcional. Se emite a Bsale y entra al resumen mensual de boletas.' }
  if (tipo.value === 'factura') return { color: 'warning', icon: 'mdi-file-document-outline', text: 'Factura: exige cliente con RUT y datos en Bsale (comuna/ciudad).' }
  return { color: 'secondary', icon: 'mdi-note-text-outline', text: 'Cotización: no emite a Bsale. Crea una cotización interna en Evaluación. Cliente obligatorio.' }
})
// En modo venta: solo Boleta/Factura (la cotización se hace en su propio módulo).
const tipoDocOpts = [
  { value: 'boleta', label: 'Boleta' },
  { value: 'factura', label: 'Factura' },
]
const btnLabel = computed(() => esCotizacion.value ? 'Guardar cotización' : 'Realizar venta')

// ── Preview / resumen ─────────────────────────────────────────────────────────
const previewOpen = ref(false)
const clienteNombre = computed(() => cliente.value ? (cliente.value.razon_social || cliente.value.nombre) : 'Consumidor Final')
const tipoLabel = computed(() => ({ boleta: 'Boleta electrónica', factura: 'Factura electrónica', cotizacion: 'Cotización' }[tipo.value]))
function imprimir() { window.print() }

const resumen = ref(null)
function realizar() {
  resumen.value = {
    tipo: tipo.value, cliente: clienteNombre.value, forma_pago: pagoLabels[formaPago.value],
    voucher: necesitaVoucher.value ? (voucher.value || '— (falta)') : null,
    neto: CLP(neto.value), iva: CLP(iva.value), total: CLP(total.value), items: items.value.length,
  }
}
</script>

<template>
  <div>
    <div class="d-flex align-center flex-wrap gap-2 mb-6">
      <h4 class="text-h4 mb-0">{{ esCotizacion ? 'Cotización rápida' : 'Venta Express' }}</h4>
      <VChip size="small" color="primary" variant="tonal" label>Prototipo</VChip>
      <span class="text-medium-emphasis text-body-2">· {{ esCotizacion ? 'Cotizar productos / vidrios (sin armador)' : 'Emisión de boleta o factura (mostrador)' }}</span>
    </div>

    <VRow>
      <!-- ══════════ Documento ══════════ -->
      <VCol cols="12" md="9">
        <VCard class="pa-6 pa-sm-8">
          <!-- Header -->
          <div class="d-flex flex-wrap justify-space-between gap-6 rounded pa-6 mb-6" style="background: rgba(var(--v-theme-on-surface), 0.04)">
            <div>
              <img :src="logoVialum" alt="VIALUM" height="34" class="mb-1" style="display:block">
              <div class="text-caption text-primary font-weight-medium mb-3" style="letter-spacing:.14em">VENTANAS PVC · ALUMINIO</div>
              <p class="text-high-emphasis font-weight-medium mb-0">HIDALGO E HIDALGO LIMITADA · RUT 76.096.031-4</p>
              <p class="text-medium-emphasis mb-0">Balmaceda 454, Los Ángeles</p>
              <p class="text-medium-emphasis mb-0">contacto@vialum.cl · +56 43 2 311859</p>
            </div>

            <div class="d-flex flex-column gap-3">
              <div v-if="!esCotizacion">
                <div class="text-caption text-medium-emphasis text-end mb-1">Tipo de documento</div>
                <VBtnToggle v-model="tipo" mandatory color="primary" variant="outlined" divided class="doc-toggle">
                  <VBtn v-for="o in tipoDocOpts" :key="o.value" :value="o.value" size="small" class="text-none">{{ o.label }}</VBtn>
                </VBtnToggle>
              </div>
              <div v-else class="d-flex align-center justify-end gap-2">
                <VChip color="secondary" variant="tonal" label>Cotización</VChip>
              </div>
              <div class="d-flex align-center justify-end gap-3">
                <span class="text-medium-emphasis">N°</span>
                <span class="font-weight-medium text-medium-emphasis">— (al {{ esCotizacion ? 'guardar' : 'emitir' }})</span>
              </div>
              <div class="d-flex align-center justify-end gap-3">
                <span class="text-medium-emphasis">Fecha</span>
                <VTextField v-model="fecha" type="date" density="compact" hide-details style="max-inline-size: 175px" />
              </div>
            </div>
          </div>

          <!-- Cliente + Pago -->
          <VRow>
            <VCol cols="12" :sm="esCotizacion ? 12 : 7">
              <h6 class="text-h6 mb-3">
                {{ esCotizacion ? 'Cliente' : (tipo === 'boleta' ? 'Cliente' : 'Facturar a') }}
                <span v-if="esCotizacion" class="text-error text-body-2">· obligatorio</span>
              </h6>
              <div class="d-flex align-center gap-2 mb-3" style="max-inline-size: 380px">
                <VAutocomplete
                  v-model="cliente"
                  v-model:search="clienteSearch"
                  :items="clientes"
                  :item-title="i => i.razon_social || i.nombre || ''"
                  return-object no-filter clearable hide-details density="compact"
                  placeholder="Buscar cliente por nombre o RUT…"
                  @update:search="buscarClientes"
                >
                  <template #no-data>
                    <div class="px-4 py-2 text-caption text-medium-emphasis">
                      {{ clienteSearch ? 'Sin resultados' : 'Escribe para buscar… (vacío = Consumidor Final)' }}
                    </div>
                  </template>
                </VAutocomplete>
                <VBtn icon variant="tonal" color="primary" size="small" @click="abrirNuevoCliente" title="Nuevo cliente">
                  <VIcon>mdi-account-plus</VIcon>
                </VBtn>
              </div>

              <template v-if="cliente">
                <p class="font-weight-medium mb-0">{{ cliente.razon_social || cliente.nombre }}</p>
                <p class="text-medium-emphasis mb-0" v-if="cliente.identification">RUT {{ cliente.identification }}</p>
                <p class="text-medium-emphasis mb-0" v-if="cliente.direccion">{{ cliente.direccion }}</p>
              </template>
              <template v-else>
                <p class="font-weight-medium mb-0">Consumidor Final</p>
                <p class="text-medium-emphasis mb-0">Venta de mostrador sin datos de cliente.</p>
              </template>
            </VCol>

            <VCol v-if="!esCotizacion" cols="12" sm="5">
              <h6 class="text-h6 mb-3">Pago</h6>
              <table class="text-body-2">
                <tbody>
                  <tr><td class="text-medium-emphasis pe-6 py-1">Forma de pago</td><td class="font-weight-medium">{{ pagoLabels[formaPago] }}</td></tr>
                  <tr><td class="text-medium-emphasis pe-6 py-1">Total a pagar</td><td class="font-weight-medium">{{ CLP(total) }}</td></tr>
                  <tr><td class="text-medium-emphasis pe-6 py-1">Vendedor</td><td class="font-weight-medium">Mostrador</td></tr>
                </tbody>
              </table>
            </VCol>
          </VRow>

          <VDivider class="my-6 border-dashed" />

          <!-- Ítems -->
          <div class="items-grid items-head text-caption font-weight-bold text-medium-emphasis text-uppercase mb-2" style="letter-spacing:.05em">
            <div>Detalle</div>
            <div class="text-end">Cantidad</div>
            <div class="text-end">Precio unit.</div>
            <div class="text-end">Desc. %</div>
            <div class="text-end">Subtotal</div>
            <div></div>
          </div>

          <div v-for="(it, i) in items" :key="i" class="items-grid item-row mb-3">
            <div class="detalle-cell">
              <VTextField v-model="it.nombre" density="compact" hide-details variant="plain"
                :readonly="it.es_vidrio" class="font-weight-medium" />
              <div class="d-flex align-center gap-2 px-3">
                <VChip v-if="it.es_vidrio" size="x-small" color="primary" variant="tonal" label>VIDRIO · m²</VChip>
                <span v-if="margen(it) !== null" class="text-caption"
                  :class="margen(it) < MARGEN_MIN ? 'text-error font-weight-bold' : 'text-medium-emphasis'">
                  margen {{ Math.round(margen(it) * 100) }}%
                </span>
              </div>
            </div>
            <VTextField v-model.number="it.cantidad" type="number" density="compact" hide-details reverse />
            <VTextField :model-value="miles(it.precio)" @update:model-value="v => it.precio = parseMiles(v)"
              density="compact" hide-details prefix="$" reverse inputmode="numeric" />
            <VTextField v-model.number="it.descuento" type="number" density="compact" hide-details reverse suffix="%" />
            <div class="text-end font-weight-bold align-self-center">{{ CLP(subtotal(it)) }}</div>
            <VBtn icon variant="text" size="small" color="error" class="align-self-center" @click="quitar(i)">
              <VIcon size="20">mdi-close</VIcon>
            </VBtn>
          </div>

          <div class="d-flex flex-wrap align-center gap-3 mt-2">
            <VAutocomplete
              v-model:search="prodSearch"
              :items="prodResults" :loading="prodLoading"
              item-title="nombre" return-object no-filter hide-details density="compact"
              placeholder="Buscar producto de lista de precios…" prepend-inner-icon="mdi-magnify"
              style="min-inline-size: 280px; max-inline-size: 380px"
              @update:search="buscarProductos"
              @update:model-value="agregarProducto"
            >
              <template #item="{ props: p, item }">
                <VListItem v-bind="p" :title="item.raw.nombre" :subtitle="CLP(item.raw.precio_venta) + (item.raw.es_vidrio ? ' /m²' : '')" />
              </template>
              <template #no-data>
                <div class="px-4 py-2 text-caption text-medium-emphasis">{{ prodSearch ? 'Sin productos' : 'Escribe para buscar productos…' }}</div>
              </template>
            </VAutocomplete>
            <VBtn size="small" variant="tonal" prepend-icon="mdi-plus" @click="agregarManual">Ítem manual</VBtn>
          </div>

          <VDivider class="my-6 border-dashed" />

          <!-- Totales -->
          <div class="d-flex flex-wrap justify-space-between gap-6">
            <div>
              <p class="mb-1"><span class="font-weight-medium">Vendedor:</span> Mostrador</p>
              <p class="text-medium-emphasis text-body-2 mb-0">Gracias por su compra.</p>
            </div>
            <div style="min-inline-size: 260px">
              <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">Neto</span><span class="font-weight-medium">{{ CLP(neto) }}</span></div>
              <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">IVA 19%</span><span class="font-weight-medium">{{ CLP(iva) }}</span></div>
              <VDivider class="my-2" />
              <div class="d-flex justify-space-between align-baseline">
                <span class="text-h6">Total</span>
                <span class="text-h4 font-weight-bold text-success">{{ CLP(total) }}</span>
              </div>
            </div>
          </div>

          <VDivider class="my-6 border-dashed" />
          <h6 class="text-h6 mb-2">Observaciones</h6>
          <VTextarea v-model="nota" rows="2" placeholder="Nota que aparece en el documento (opcional)…" hide-details />
        </VCard>
      </VCol>

      <!-- ══════════ Panel de acciones ══════════ -->
      <VCol cols="12" md="3">
        <VCard class="mb-4">
          <VCardText class="d-flex flex-column gap-3">
            <VBtn block color="success" prepend-icon="mdi-flash" @click="realizar">{{ btnLabel }}</VBtn>
            <VBtn block color="secondary" variant="tonal" prepend-icon="mdi-eye-outline" @click="previewOpen = true">Vista previa</VBtn>
            <VBtn block color="secondary" variant="tonal" prepend-icon="mdi-content-save-outline">Guardar borrador</VBtn>
          </VCardText>
        </VCard>

        <VCard v-if="!esCotizacion" class="mb-4">
          <VCardText>
            <p class="text-caption font-weight-bold text-uppercase text-disabled mb-2" style="letter-spacing:.06em">Forma de pago</p>
            <VSelect v-model="formaPago" :items="pagoItems" density="compact" hide-details />
            <VTextField v-if="necesitaVoucher" v-model="voucher" class="mt-3" density="compact" hide-details placeholder="N° voucher Transbank (obligatorio)" />
          </VCardText>
        </VCard>

        <VCard class="mb-4">
          <VCardText>
            <p class="text-caption font-weight-bold text-uppercase text-disabled mb-2" style="letter-spacing:.06em">Opciones</p>
            <VSwitch v-model="optCorte" label="Requiere orden de corte" density="compact" hide-details color="primary" />
            <VSwitch v-model="optRef" label="Agregar referencias (OC / guía)" density="compact" hide-details color="primary" />
            <VSwitch v-model="optDesc" label="Aplicar descuento por cliente" density="compact" hide-details color="primary" />
          </VCardText>
        </VCard>

        <VAlert :type="tipoHint.color" variant="tonal" density="compact" :icon="tipoHint.icon" class="text-body-2">{{ tipoHint.text }}</VAlert>

        <VAlert v-if="resumen" type="success" variant="tonal" class="mt-4 text-body-2">
          <div class="font-weight-bold mb-1">Prototipo — resumen de {{ resumen.tipo }}</div>
          Cliente: {{ resumen.cliente }}<br>
          Forma de pago: {{ resumen.forma_pago }}<span v-if="resumen.voucher"> · voucher {{ resumen.voucher }}</span><br>
          Ítems: {{ resumen.items }} · Neto {{ resumen.neto }} · IVA {{ resumen.iva }}<br>
          <span class="text-h6">Total {{ resumen.total }}</span>
          <div class="text-caption text-medium-emphasis mt-1">(No se emitió nada — es solo el prototipo.)</div>
        </VAlert>
      </VCol>
    </VRow>

    <!-- ══════════ Modal: medidas de vidrio ══════════ -->
    <VDialog v-model="medidas.show" max-width="440">
      <VCard>
        <VCardItem>
          <VCardTitle>Medidas del vidrio</VCardTitle>
          <VCardSubtitle>{{ medidas.producto?.nombre }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="6"><VTextField v-model.number="medidas.ancho" type="number" label="Ancho (mm)" density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model.number="medidas.alto"  type="number" label="Alto (mm)"  density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model.number="medidas.piezas" type="number" label="Piezas" density="compact" /></VCol>
            <VCol cols="6" class="d-flex align-center"><VSwitch v-model="medidas.pulido" label="Con pulido (+20%)" color="primary" hide-details density="compact" /></VCol>
          </VRow>
          <VAlert type="info" variant="tonal" density="compact" class="text-body-2">
            {{ m2Calc }} m² × {{ CLP(precioM2) }}/m² = <strong>{{ CLP(m2Calc * precioM2) }}</strong> neto
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="medidas.show = false">Cancelar</VBtn>
          <VBtn color="primary" :disabled="!(m2Calc > 0)" @click="confirmarMedidas">Agregar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ══════════ Modal: nuevo cliente ══════════ -->
    <VDialog v-model="nuevoCli.show" max-width="480">
      <VCard>
        <VCardItem><VCardTitle>Nuevo cliente</VCardTitle></VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12"><VTextField v-model="nuevoCli.razon_social" label="Razón social / Nombre" density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model="nuevoCli.identification" label="RUT" density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model="nuevoCli.direccion" label="Dirección" density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model="nuevoCli.comuna" label="Comuna" density="compact" /></VCol>
            <VCol cols="6"><VTextField v-model="nuevoCli.ciudad" label="Ciudad" density="compact" /></VCol>
          </VRow>
          <VAlert type="info" variant="tonal" density="compact" class="text-caption">Prototipo: se selecciona localmente. En el módulo real se crea en Bsale (POST /api/bsale-clientes/crear).</VAlert>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="nuevoCli.show = false">Cancelar</VBtn>
          <VBtn color="primary" :disabled="!nuevoCli.razon_social" @click="guardarCliente">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ══════════ Vista Previa del documento ══════════ -->
    <VDialog v-model="previewOpen" max-width="820" scrollable>
      <VCard class="doc-preview">
        <VCardText class="pa-0">
          <div id="doc-print" class="pa-8 pa-sm-10">
            <div class="d-flex flex-wrap justify-space-between gap-6 mb-8">
              <div>
                <img :src="logoVialum" alt="VIALUM" height="32" class="mb-1" style="display:block">
                <div class="text-caption text-primary mb-2" style="letter-spacing:.14em">VENTANAS PVC · ALUMINIO</div>
                <p class="mb-0 font-weight-medium">HIDALGO E HIDALGO LIMITADA</p>
                <p class="mb-0 text-medium-emphasis">RUT 76.096.031-4 · Balmaceda 454, Los Ángeles</p>
                <p class="mb-0 text-medium-emphasis">contacto@vialum.cl · +56 43 2 311859</p>
              </div>
              <div class="doc-stamp text-center">
                <div class="text-error font-weight-bold">R.U.T. 76.096.031-4</div>
                <div class="text-error font-weight-bold text-uppercase my-1">{{ tipoLabel }}</div>
                <div class="text-error font-weight-bold">N° — (al emitir)</div>
                <div class="text-caption text-medium-emphasis mt-1">S.I.I. - Los Ángeles</div>
              </div>
            </div>

            <VRow class="mb-6">
              <VCol cols="12" sm="7">
                <div class="text-overline text-medium-emphasis mb-1">{{ tipo === 'boleta' ? 'Cliente' : 'Señor(es)' }}</div>
                <p class="mb-0 font-weight-medium">{{ clienteNombre }}</p>
                <template v-if="cliente">
                  <p class="mb-0 text-medium-emphasis" v-if="cliente.identification">RUT {{ cliente.identification }}</p>
                  <p class="mb-0 text-medium-emphasis" v-if="cliente.direccion">{{ cliente.direccion }}</p>
                </template>
              </VCol>
              <VCol cols="12" sm="5">
                <table class="w-100 text-body-2">
                  <tbody>
                    <tr><td class="text-medium-emphasis py-1">{{ esCotizacion ? 'Fecha' : 'Fecha emisión' }}</td><td class="text-end">{{ fecha }}</td></tr>
                    <tr v-if="!esCotizacion"><td class="text-medium-emphasis py-1">Forma de pago</td><td class="text-end">{{ pagoLabels[formaPago] }}</td></tr>
                  </tbody>
                </table>
              </VCol>
            </VRow>

            <VTable class="doc-table border mb-6">
              <thead>
                <tr>
                  <th>DETALLE</th>
                  <th class="text-end">CANT.</th>
                  <th class="text-end">P. UNIT</th>
                  <th class="text-end">DESC.</th>
                  <th class="text-end">SUBTOTAL</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in items" :key="i">
                  <td>{{ it.nombre }}</td>
                  <td class="text-end">{{ it.cantidad }}</td>
                  <td class="text-end">{{ CLP(it.precio) }}</td>
                  <td class="text-end">{{ it.descuento ? it.descuento + '%' : '—' }}</td>
                  <td class="text-end font-weight-medium">{{ CLP(subtotal(it)) }}</td>
                </tr>
              </tbody>
            </VTable>

            <div class="d-flex justify-end">
              <table style="min-inline-size: 280px">
                <tbody>
                  <tr><td class="text-medium-emphasis py-1 pe-8">Neto</td><td class="text-end">{{ CLP(neto) }}</td></tr>
                  <tr><td class="text-medium-emphasis py-1 pe-8">IVA 19%</td><td class="text-end">{{ CLP(iva) }}</td></tr>
                  <tr class="doc-total"><td class="py-2 pe-8 text-h6">TOTAL</td><td class="text-end text-h6 font-weight-bold">{{ CLP(total) }}</td></tr>
                </tbody>
              </table>
            </div>

            <template v-if="nota">
              <VDivider class="my-6 border-dashed" />
              <p class="mb-0"><span class="font-weight-medium me-1">Nota:</span>{{ nota }}</p>
            </template>
          </div>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 d-print-none">
          <VChip size="small" color="secondary" variant="tonal" label>Vista previa · no emitida</VChip>
          <VSpacer />
          <VBtn variant="tonal" color="secondary" prepend-icon="mdi-printer" @click="imprimir">Imprimir</VBtn>
          <VBtn color="primary" @click="previewOpen = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.doc-toggle { block-size: 34px; }
.doc-toggle .v-btn { min-inline-size: 74px; }

.items-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 88px 140px 78px 120px 40px;
  gap: 10px;
  align-items: center;
}
.items-head { display: none; padding-inline: 13px; }
@media (min-width: 960px) { .items-head { display: grid; } }

.item-row {
  padding: 8px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
.item-row:hover { border-color: rgba(var(--v-border-color), 0.9); }
.detalle-cell { min-inline-size: 0; }

@media (max-width: 959px) {
  .items-grid { grid-template-columns: 1fr 1fr; }
  .detalle-cell { grid-column: 1 / -1; }
}

.doc-stamp { border: 2px solid rgb(var(--v-theme-error)); border-radius: 8px; padding: 10px 16px; min-inline-size: 190px; block-size: fit-content; }
.doc-table :deep(th) { font-size: .72rem; letter-spacing: .05em; }
.doc-total td { border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
