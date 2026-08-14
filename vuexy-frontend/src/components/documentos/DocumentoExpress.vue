<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/axiosInstance'
import logoVialum from '@/assets/images/logo-vialum.png'
import MoneyField from './MoneyField.vue'

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

// Descuento % configurado en la ficha del cliente (mismo campo que Venta Express)
function descuentoCliente() { return Number(cliente.value?.descuento || 0) }

// Crear cliente (prototipo: lo agrega localmente; el módulo real llama a Bsale)
const nuevoCliVacio = () => ({ show: false, tipo: 'empresa', razon_social: '', identification: '', giro: '', email: '', telefono: '', direccion: '', comuna: '', ciudad: '' })
const nuevoCli = ref(nuevoCliVacio())
function abrirNuevoCliente() {
  nuevoCli.value = { ...nuevoCliVacio(), show: true, razon_social: clienteSearch.value || '' }
}
function guardarCliente() {
  const c = nuevoCli.value
  cliente.value = {
    razon_social: c.razon_social, identification: c.identification, giro: c.giro,
    email: c.email, telefono: c.telefono, tipo: c.tipo,
    direccion: [c.direccion, c.comuna, c.ciudad].filter(Boolean).join(', '),
  }
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
  items.value.push({ nombre: p.nombre, cantidad: 1, precio: p.precio_venta, costo: p.costo, descuento: optDesc.value ? descuentoCliente() : 0, producto_id: p.producto_id, stock: p.stock, es_vidrio: false })
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
    cantidad: m2Calc.value, precio: precioM2.value, costo: m.producto.costo, descuento: optDesc.value ? descuentoCliente() : 0,
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
const bajoMargen = it => { const m = margen(it); return m !== null && m < MARGEN_MIN }
const hayBajoMargen = computed(() => items.value.some(bajoMargen))

// ── Pago / opciones ───────────────────────────────────────────────────────────
const formaPago = ref('tarjeta_debito')
const voucher   = ref('')
const pagoLabels = { tarjeta_debito: 'Tarjeta débito', tarjeta_credito: 'Tarjeta crédito', transferencia: 'Transferencia', efectivo: 'Efectivo' }
const pagoItems = Object.entries(pagoLabels).map(([value, title]) => ({ value, title }))
const necesitaVoucher = computed(() => ['tarjeta_debito', 'tarjeta_credito'].includes(formaPago.value))
// Voucher obligatorio en pagos con tarjeta (igual que Venta Express: vouchersOk)
const voucherFalta = computed(() => !esCotizacion.value && necesitaVoucher.value && !String(voucher.value).trim())

const optCorte = ref(false)
const optRef   = ref(false)
const optDesc  = ref(true)
const nota     = ref('')
const validez  = ref(15) // días de validez de la cotización

// Descuento por cliente: al elegir cliente o (des)activar el switch, aplicar a
// las líneas de catálogo (con producto_id). Las líneas manuales no se tocan.
function aplicarDescuentoCliente() {
  const d = (optDesc.value && cliente.value) ? descuentoCliente() : 0
  items.value.forEach(it => { if (it.producto_id) it.descuento = d })
}
watch([cliente, optDesc], aplicarDescuentoCliente)

// Puede realizar: reglas de bloqueo (margen, voucher, cliente en cotización)
const puedeRealizar = computed(() => {
  if (!items.value.length) return false
  if (hayBajoMargen.value) return false
  if (esCotizacion.value) return !!cliente.value
  return !voucherFalta.value
})
const bloqueoMsg = computed(() => {
  if (!items.value.length) return 'Agrega al menos un ítem.'
  if (hayBajoMargen.value) return `Hay una línea bajo el margen mínimo (${Math.round(MARGEN_MIN * 100)}%). Sube el precio.`
  if (esCotizacion.value && !cliente.value) return 'La cotización requiere cliente.'
  if (voucherFalta.value) return 'Falta el N° de voucher Transbank.'
  return ''
})

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
  if (!puedeRealizar.value) return
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
                <p class="font-weight-medium mb-0">
                  {{ cliente.razon_social || cliente.nombre }}
                  <VChip v-if="descuentoCliente() > 0" size="x-small" color="success" variant="tonal" label class="ml-1">Descuento {{ descuentoCliente() }}%</VChip>
                </p>
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
                <VChip v-if="it.stock != null && it.stock <= 0" size="x-small" color="warning" variant="tonal" label>sin stock</VChip>
              </div>
            </div>
            <VTextField v-model.number="it.cantidad" type="number" density="compact" hide-details reverse />
            <MoneyField v-model="it.precio" />
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
                <VListItem v-bind="p" :title="item.raw.nombre"
                  :subtitle="CLP(item.raw.precio_venta) + (item.raw.es_vidrio ? ' /m²' : '') + (item.raw.stock != null ? ' · ' + (item.raw.stock > 0 ? item.raw.stock + ' disp.' : 'sin stock') : '')" />
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
            <VBtn block color="success" prepend-icon="mdi-flash" :disabled="!puedeRealizar" @click="realizar">{{ btnLabel }}</VBtn>
            <p v-if="bloqueoMsg" class="text-caption text-error mb-0 mt-n1">{{ bloqueoMsg }}</p>
            <VBtn block color="secondary" variant="tonal" prepend-icon="mdi-eye-outline" @click="previewOpen = true">Vista previa</VBtn>
            <VBtn block color="secondary" variant="tonal" prepend-icon="mdi-content-save-outline">Guardar borrador</VBtn>
          </VCardText>
        </VCard>

        <VCard v-if="!esCotizacion" class="mb-4">
          <VCardText>
            <p class="text-caption font-weight-bold text-uppercase text-disabled mb-2" style="letter-spacing:.06em">Forma de pago</p>
            <VSelect v-model="formaPago" :items="pagoItems" density="compact" hide-details />
            <VTextField v-if="necesitaVoucher" v-model="voucher" class="mt-3" density="compact"
              placeholder="N° voucher Transbank (obligatorio)"
              :error="voucherFalta" :messages="voucherFalta ? 'Obligatorio para pago con tarjeta' : ''" />
          </VCardText>
        </VCard>

        <VCard class="mb-4">
          <VCardText>
            <p class="text-caption font-weight-bold text-uppercase text-disabled mb-2" style="letter-spacing:.06em">Opciones</p>
            <VSwitch v-if="!esCotizacion" v-model="optCorte" label="Requiere orden de corte" density="compact" hide-details color="primary" />
            <VSwitch v-if="!esCotizacion" v-model="optRef" label="Agregar referencias (OC / guía)" density="compact" hide-details color="primary" />
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
    <VDialog v-model="nuevoCli.show" max-width="560">
      <VCard>
        <VCardItem>
          <VCardTitle>Nuevo cliente</VCardTitle>
          <VCardSubtitle>Se crea en Bsale y queda seleccionado</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12" sm="8"><VTextField v-model="nuevoCli.razon_social" label="Razón social / Nombre" density="compact" /></VCol>
            <VCol cols="12" sm="4">
              <VSelect v-model="nuevoCli.tipo" :items="[{title:'Empresa',value:'empresa'},{title:'Persona',value:'persona'}]" label="Tipo" density="compact" />
            </VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.identification" label="RUT" density="compact" placeholder="12.345.678-9" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.giro" label="Giro" density="compact" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.email" label="Email" type="email" density="compact" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.telefono" label="Teléfono" density="compact" /></VCol>
            <VCol cols="12"><VTextField v-model="nuevoCli.direccion" label="Dirección" density="compact" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.comuna" label="Comuna" density="compact" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="nuevoCli.ciudad" label="Ciudad" density="compact" /></VCol>
          </VRow>
          <VAlert type="info" variant="tonal" density="compact" class="text-caption">
            Comuna y ciudad son obligatorias para facturar. Prototipo: se selecciona localmente; en el módulo real se crea en Bsale (POST /api/bsale-clientes/crear).
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="nuevoCli.show = false">Cancelar</VBtn>
          <VBtn color="primary" :disabled="!nuevoCli.razon_social || !nuevoCli.identification" @click="guardarCliente">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ══════════ Vista Previa del documento ══════════ -->
    <VDialog v-model="previewOpen" :max-width="esCotizacion ? 840 : 640" scrollable>
      <VCard>
        <!-- ═══ COTIZACIÓN: hoja carta bien presentada ═══ -->
        <VCardText v-if="esCotizacion" class="pa-0">
          <div id="doc-print" class="coti-paper">
            <div class="coti-head">
              <div>
                <img :src="logoVialum" alt="VIALUM" height="40" style="display:block">
                <div class="coti-sub">VENTANAS PVC · ALUMINIO</div>
                <p class="coti-strong mb-0">HIDALGO E HIDALGO LIMITADA</p>
                <p class="coti-muted mb-0">RUT 76.096.031-4 · Vidriería, aluminios y ferretería</p>
                <p class="coti-muted mb-0">Balmaceda 454, Los Ángeles</p>
                <p class="coti-muted mb-0">contacto@vialum.cl · +56 43 2 311859</p>
              </div>
              <div class="coti-box">
                <div class="coti-box-title">COTIZACIÓN</div>
                <table>
                  <tbody>
                    <tr><td>N°</td><td>—</td></tr>
                    <tr><td>Fecha</td><td>{{ fecha }}</td></tr>
                    <tr><td>Validez</td><td>{{ validez }} días</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="coti-rule"></div>

            <div class="coti-label">Señor(es)</div>
            <p class="coti-strong mb-0">{{ clienteNombre }}</p>
            <template v-if="cliente">
              <p class="coti-muted mb-0" v-if="cliente.identification">RUT {{ cliente.identification }}</p>
              <p class="coti-muted mb-0" v-if="cliente.direccion">{{ cliente.direccion }}</p>
              <p class="coti-muted mb-0" v-if="cliente.email">{{ cliente.email }}</p>
            </template>

            <p class="coti-intro">Junto con saludar, tenemos el agrado de presentar la siguiente cotización según su solicitud:</p>

            <table class="coti-table">
              <thead>
                <tr><th>Detalle</th><th class="r">Cant.</th><th class="r">P. Unit</th><th class="r">Desc.</th><th class="r">Subtotal</th></tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in items" :key="i">
                  <td>{{ it.nombre }}</td>
                  <td class="r">{{ it.cantidad }}</td>
                  <td class="r">{{ CLP(it.precio) }}</td>
                  <td class="r">{{ it.descuento ? it.descuento + '%' : '—' }}</td>
                  <td class="r">{{ CLP(subtotal(it)) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="coti-totales">
              <table>
                <tbody>
                  <tr><td>Neto</td><td class="r">{{ CLP(neto) }}</td></tr>
                  <tr><td>IVA 19%</td><td class="r">{{ CLP(iva) }}</td></tr>
                  <tr class="tot"><td>TOTAL</td><td class="r">{{ CLP(total) }}</td></tr>
                </tbody>
              </table>
            </div>

            <div v-if="nota" class="coti-nota"><strong>Nota:</strong> {{ nota }}</div>

            <div class="coti-cond">
              <div class="coti-label">Condiciones comerciales</div>
              <ul>
                <li>Validez de la oferta: {{ validez }} días corridos.</li>
                <li>Valores en pesos chilenos, IVA incluido.</li>
                <li>Forma de pago y plazo de entrega: a convenir.</li>
                <li>Despacho e instalación se cotizan por separado si aplica.</li>
              </ul>
            </div>

            <div class="coti-firma">
              <div>
                <div class="coti-line"></div>
                <div class="coti-muted">VIALUM · contacto@vialum.cl</div>
              </div>
            </div>
          </div>
        </VCardText>

        <!-- ═══ VENTA: el documento final es de Bsale ═══ -->
        <VCardText v-else class="pa-6">
          <div class="d-flex align-center gap-3 mb-4">
            <VIcon color="info" size="30">mdi-information-outline</VIcon>
            <div>
              <div class="text-h6">El documento final lo genera Bsale</div>
              <div class="text-medium-emphasis text-body-2">La {{ tipo }} se emite con el formato oficial de Bsale. Esto es solo un resumen.</div>
            </div>
          </div>
          <VTable class="border">
            <thead><tr><th>Detalle</th><th class="text-end">Cant.</th><th class="text-end">P. Unit</th><th class="text-end">Subtotal</th></tr></thead>
            <tbody>
              <tr v-for="(it, i) in items" :key="i">
                <td>{{ it.nombre }}</td><td class="text-end">{{ it.cantidad }}</td><td class="text-end">{{ CLP(it.precio) }}</td><td class="text-end">{{ CLP(subtotal(it)) }}</td>
              </tr>
            </tbody>
          </VTable>
          <div class="d-flex justify-space-between align-baseline mt-4">
            <span class="text-medium-emphasis">Total (IVA incluido)</span>
            <span class="text-h5 font-weight-bold text-success">{{ CLP(total) }}</span>
          </div>
        </VCardText>

        <VDivider />
        <VCardActions class="pa-4 d-print-none">
          <VChip size="small" color="secondary" variant="tonal" label>Vista previa · no {{ esCotizacion ? 'guardada' : 'emitida' }}</VChip>
          <VSpacer />
          <VBtn v-if="esCotizacion" variant="tonal" color="secondary" prepend-icon="mdi-printer" @click="imprimir">Imprimir</VBtn>
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

/* ── Hoja carta de cotización (papel blanco, se ve igual en claro/oscuro y al imprimir) ── */
.coti-paper { background: #fff; color: #2b2f42; padding: 48px 52px; font-size: 13px; line-height: 1.5; }
.coti-head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.coti-sub { color: #1e4d8b; letter-spacing: .14em; font-size: 10px; font-weight: 700; margin: 3px 0 12px; }
.coti-strong { font-weight: 700; }
.coti-muted { color: #6b7180; font-size: 12px; }
.coti-box { border: 1.5px solid #1e4d8b; border-radius: 8px; padding: 10px 16px; min-inline-size: 180px; block-size: fit-content; }
.coti-box-title { color: #1e4d8b; font-weight: 800; letter-spacing: .08em; text-align: center; border-bottom: 1px solid #d9dee6; padding-bottom: 6px; margin-bottom: 6px; }
.coti-box table { inline-size: 100%; }
.coti-box td { padding: 2px 0; font-size: 12px; }
.coti-box td:last-child { text-align: right; font-weight: 600; }
.coti-rule { block-size: 3px; background: #1e4d8b; border-radius: 2px; margin: 20px 0; }
.coti-label { text-transform: uppercase; letter-spacing: .06em; font-size: 10px; color: #6b7180; font-weight: 700; margin-bottom: 4px; }
.coti-intro { margin: 18px 0; }
.coti-table { inline-size: 100%; border-collapse: collapse; margin-top: 8px; }
.coti-table th { background: #f2f5f9; color: #4a5060; text-align: left; font-size: 11px; letter-spacing: .04em; padding: 8px 10px; border-bottom: 2px solid #1e4d8b; }
.coti-table th.r, .coti-table td.r { text-align: right; }
.coti-table td { padding: 8px 10px; border-bottom: 1px solid #eceef3; font-size: 12.5px; }
.coti-totales { display: flex; justify-content: flex-end; margin-top: 16px; }
.coti-totales table { min-inline-size: 260px; }
.coti-totales td { padding: 4px 0; color: #6b7180; }
.coti-totales td.r { text-align: right; color: #2b2f42; font-weight: 600; }
.coti-totales tr.tot td { border-top: 2px solid #d9dee6; padding-top: 10px; font-size: 16px; font-weight: 800; color: #1e4d8b; }
.coti-nota { margin-top: 18px; padding: 10px 12px; background: #f6f8fb; border-left: 3px solid #1e4d8b; border-radius: 4px; font-size: 12.5px; }
.coti-cond { margin-top: 24px; }
.coti-cond ul { margin: 6px 0 0; padding-inline-start: 18px; color: #4a5060; font-size: 12px; }
.coti-cond li { margin: 3px 0; }
.coti-firma { margin-top: 48px; display: flex; justify-content: flex-end; text-align: center; }
.coti-line { inline-size: 220px; border-top: 1px solid #9aa1b0; margin-bottom: 4px; }
</style>

<!-- Impresión: aislar solo la hoja de cotización -->
<style>
@media print {
  body * { visibility: hidden !important; }
  #doc-print, #doc-print * { visibility: visible !important; }
  #doc-print { position: absolute; inset-block-start: 0; inset-inline-start: 0; inline-size: 100%; padding: 0 !important; }
}
</style>
