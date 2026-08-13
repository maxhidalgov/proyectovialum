<script setup>
import { ref, computed } from 'vue'
import api from '@/axiosInstance'

// ── Prototipo de Venta Express con el layout "Add Invoice" de Vuexy ──────────
// Página aislada (no toca /venta-express). Usa los endpoints reales de
// productos y clientes para que se sienta en vivo, pero el botón "Realizar
// venta" NO emite: solo muestra el resumen del payload, para poder iterar el
// diseño sin emitir documentos tributarios reales.

const IVA = 0.19
const CLP = n => '$' + Math.round(Number(n) || 0).toLocaleString('es-CL')

const tipo  = ref('boleta')            // boleta | factura | cotizacion
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

// ── Ítems ────────────────────────────────────────────────────────────────────
const items = ref([
  { nombre: 'Ventana PVC corredera 2 hojas 1200×1200', cantidad: 2, precio: 189900, costo: 120000, producto_id: null, es_vidrio: false },
  { nombre: 'Kit instalación + silicona neutra',        cantidad: 1, precio: 24900,  costo: 12000,  producto_id: null, es_vidrio: false },
])

function agregarProducto(p) {
  if (!p) return
  items.value.push({
    nombre: p.nombre, cantidad: 1, precio: p.precio_venta, costo: p.costo,
    producto_id: p.producto_id, es_vidrio: !!p.es_vidrio,
  })
  prodSearch.value = ''
  prodResults.value = []
}
function agregarManual() {
  items.value.push({ nombre: 'Nuevo ítem', cantidad: 1, precio: 0, costo: 0, producto_id: null, es_vidrio: false })
}
function quitar(i) { items.value.splice(i, 1) }

const subtotal = it => (Number(it.cantidad) || 0) * (Number(it.precio) || 0)
const neto  = computed(() => items.value.reduce((s, it) => s + subtotal(it), 0))
const iva   = computed(() => neto.value * IVA)
const total = computed(() => neto.value + iva.value)

// margen (como en el módulo real: rojo si < 20%)
const MARGEN_MIN = 0.20
const margen = it => {
  const c = Number(it.costo) || 0, p = Number(it.precio) || 0
  return c > 0 && p > 0 ? (p - c) / p : null
}

// ── Pago ──────────────────────────────────────────────────────────────────────
const formaPago = ref('tarjeta_debito')
const voucher   = ref('')
const pagoLabels = {
  tarjeta_debito: 'Tarjeta débito', tarjeta_credito: 'Tarjeta crédito',
  transferencia: 'Transferencia', efectivo: 'Efectivo',
}
const pagoItems = Object.entries(pagoLabels).map(([value, title]) => ({ value, title }))
const necesitaVoucher = computed(() => ['tarjeta_debito', 'tarjeta_credito'].includes(formaPago.value))

// ── Opciones ──────────────────────────────────────────────────────────────────
const optCorte = ref(false)
const optRef   = ref(false)
const optDesc  = ref(true)
const nota     = ref('')

const tipoHint = computed(() => {
  if (tipo.value === 'boleta')    return { color: 'info',    icon: 'mdi-receipt-text-outline', text: 'Boleta: cliente opcional. Se emite a Bsale y entra al resumen mensual de boletas.' }
  if (tipo.value === 'factura')   return { color: 'warning', icon: 'mdi-file-document-outline', text: 'Factura: exige cliente con RUT y datos en Bsale (comuna/ciudad).' }
  return { color: 'secondary', icon: 'mdi-note-text-outline', text: 'Cotización: no emite a Bsale. Crea una cotización interna en Evaluación. Cliente obligatorio.' }
})

const tipoDocOpts = [
  { value: 'boleta', label: 'Boleta' },
  { value: 'factura', label: 'Factura' },
  { value: 'cotizacion', label: 'Cotización' },
]

const btnLabel = computed(() => tipo.value === 'cotizacion' ? 'Guardar cotización' : 'Realizar venta')

const previewOpen = ref(false)
const clienteNombre = computed(() => cliente.value ? (cliente.value.razon_social || cliente.value.nombre) : 'Consumidor Final')
const tipoLabel = computed(() => ({ boleta: 'Boleta electrónica', factura: 'Factura electrónica', cotizacion: 'Cotización' }[tipo.value]))
function imprimir() { window.print() }

const resumen = ref(null)
function realizar() {
  resumen.value = {
    tipo: tipo.value,
    cliente: cliente.value ? (cliente.value.razon_social || cliente.value.nombre) : 'Consumidor Final',
    forma_pago: pagoLabels[formaPago.value],
    voucher: necesitaVoucher.value ? (voucher.value || '— (falta)') : null,
    neto: CLP(neto.value), iva: CLP(iva.value), total: CLP(total.value),
    items: items.value.length,
  }
}
</script>

<template>
  <div>
    <div class="d-flex align-center flex-wrap gap-2 mb-6">
      <h4 class="text-h4 mb-0">Venta Express</h4>
      <VChip size="small" color="primary" variant="tonal" label>Prototipo</VChip>
      <span class="text-medium-emphasis text-body-2">· Emisión de documento (mostrador)</span>
    </div>

    <VRow>
      <!-- ══════════ Documento ══════════ -->
      <VCol cols="12" md="9">
        <VCard class="pa-6 pa-sm-8">
          <!-- Header -->
          <div class="d-flex flex-wrap justify-space-between gap-6 rounded pa-6 mb-6" style="background: rgba(var(--v-theme-on-surface), 0.04)">
            <div>
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar color="primary" variant="tonal" rounded size="42">
                  <span class="text-h6 font-weight-bold">V</span>
                </VAvatar>
                <div>
                  <div class="text-h5 font-weight-bold" style="letter-spacing:.02em">VIALUM</div>
                  <div class="text-caption text-primary font-weight-medium" style="letter-spacing:.14em">VENTANAS PVC · ALUMINIO</div>
                </div>
              </div>
              <p class="text-high-emphasis font-weight-medium mb-0">HIDALGO E HIDALGO LIMITADA · RUT 76.096.031-4</p>
              <p class="text-medium-emphasis mb-0">Balmaceda 454, Los Ángeles</p>
              <p class="text-medium-emphasis mb-0">contacto@vialum.cl · +56 43 2 311859</p>
            </div>

            <div class="d-flex flex-column gap-3" style="min-inline-size: 260px">
              <div class="d-flex align-center justify-end gap-3">
                <span class="text-medium-emphasis">Documento</span>
                <VBtnToggle v-model="tipo" mandatory density="compact" color="primary" variant="outlined" divided>
                  <VBtn v-for="o in tipoDocOpts" :key="o.value" :value="o.value" size="small">{{ o.label }}</VBtn>
                </VBtnToggle>
              </div>
              <div class="d-flex align-center justify-end gap-3">
                <span class="text-medium-emphasis">N°</span>
                <span class="font-weight-medium text-medium-emphasis">— (al emitir)</span>
              </div>
              <div class="d-flex align-center justify-end gap-3">
                <span class="text-medium-emphasis">Fecha</span>
                <VTextField v-model="fecha" type="date" density="compact" hide-details style="max-inline-size: 170px" />
              </div>
            </div>
          </div>

          <!-- Cliente + Pago -->
          <VRow>
            <VCol cols="12" sm="7">
              <h6 class="text-h6 mb-3">{{ tipo === 'boleta' ? 'Cliente' : 'Facturar a' }}</h6>
              <VAutocomplete
                v-model="cliente"
                v-model:search="clienteSearch"
                :items="clientes"
                :item-title="i => i.razon_social || i.nombre || ''"
                return-object
                placeholder="Buscar cliente por nombre o RUT…"
                density="compact"
                no-filter
                clearable
                hide-details
                class="mb-3"
                style="max-inline-size: 320px"
                @update:search="buscarClientes"
              >
                <template #no-data>
                  <div class="px-4 py-2 text-caption text-medium-emphasis">
                    {{ clienteSearch ? 'Sin resultados' : 'Escribe para buscar… (vacío = Consumidor Final)' }}
                  </div>
                </template>
              </VAutocomplete>

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

            <VCol cols="12" sm="5">
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
          <div class="items-grid items-head d-none d-md-grid text-caption font-weight-bold text-medium-emphasis text-uppercase mb-2" style="letter-spacing:.06em">
            <div>Detalle</div>
            <div class="text-end">Cantidad</div>
            <div class="text-end">Precio unit. (neto)</div>
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
            <VTextField v-model.number="it.precio" type="number" density="compact" hide-details prefix="$" reverse />
            <div class="text-end font-weight-bold align-self-center">{{ CLP(subtotal(it)) }}</div>
            <VBtn icon variant="text" size="small" color="error" class="align-self-center" @click="quitar(i)">
              <VIcon size="20">mdi-close</VIcon>
            </VBtn>
          </div>

          <div class="d-flex flex-wrap align-center gap-3 mt-2">
            <VAutocomplete
              v-model:search="prodSearch"
              :items="prodResults"
              :loading="prodLoading"
              item-title="nombre"
              return-object
              placeholder="Buscar producto de lista de precios…"
              density="compact"
              no-filter
              hide-details
              prepend-inner-icon="mdi-magnify"
              style="min-inline-size: 280px; max-inline-size: 380px"
              @update:search="buscarProductos"
              @update:model-value="agregarProducto"
            >
              <template #item="{ props: p, item }">
                <VListItem v-bind="p" :title="item.raw.nombre" :subtitle="CLP(item.raw.precio_venta)" />
              </template>
              <template #no-data>
                <div class="px-4 py-2 text-caption text-medium-emphasis">
                  {{ prodSearch ? 'Sin productos' : 'Escribe para buscar productos…' }}
                </div>
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

        <VCard class="mb-4">
          <VCardText>
            <p class="text-caption font-weight-bold text-uppercase text-disabled mb-2" style="letter-spacing:.06em">Forma de pago</p>
            <VSelect v-model="formaPago" :items="pagoItems" density="compact" hide-details />
            <VTextField v-if="necesitaVoucher" v-model="voucher" class="mt-3" density="compact" hide-details
              placeholder="N° voucher Transbank (obligatorio)" />
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

        <VAlert :type="tipoHint.color" variant="tonal" density="compact" :icon="tipoHint.icon" class="text-body-2">
          {{ tipoHint.text }}
        </VAlert>

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

    <!-- ══════════ Vista Previa del documento ══════════ -->
    <VDialog v-model="previewOpen" max-width="820" scrollable>
      <VCard class="doc-preview">
        <VCardText class="pa-0">
          <div id="doc-print" class="pa-8 pa-sm-10">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-space-between gap-6 mb-8">
              <div>
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar color="primary" variant="tonal" rounded size="40"><span class="text-h6 font-weight-bold">V</span></VAvatar>
                  <div>
                    <div class="text-h5 font-weight-bold">VIALUM</div>
                    <div class="text-caption text-primary" style="letter-spacing:.14em">VENTANAS PVC · ALUMINIO</div>
                  </div>
                </div>
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
                    <tr><td class="text-medium-emphasis py-1">Fecha emisión</td><td class="text-end">{{ fecha }}</td></tr>
                    <tr><td class="text-medium-emphasis py-1">Forma de pago</td><td class="text-end">{{ pagoLabels[formaPago] }}</td></tr>
                  </tbody>
                </table>
              </VCol>
            </VRow>

            <VTable class="doc-table border mb-6">
              <thead>
                <tr>
                  <th>DETALLE</th>
                  <th class="text-end">CANT.</th>
                  <th class="text-end">P. UNIT (NETO)</th>
                  <th class="text-end">SUBTOTAL</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in items" :key="i">
                  <td>{{ it.nombre }}</td>
                  <td class="text-end">{{ it.cantidad }}</td>
                  <td class="text-end">{{ CLP(it.precio) }}</td>
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
.items-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 110px 160px 130px 40px;
  gap: 12px;
  align-items: center;
}
.items-head { padding-inline: 13px; }        /* alinea con el contenido de las filas (borde 1px + padding 12px) */
.items-head .text-end { text-align: end; }
.item-row {
  padding: 8px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
.item-row:hover { border-color: rgba(var(--v-border-color), 0.9); }
.detalle-cell { min-inline-size: 0; }

/* Responsivo: en móvil apila (el header md se oculta con d-none d-md-grid) */
@media (max-width: 959px) {
  .items-grid { grid-template-columns: 1fr 1fr; }
  .detalle-cell { grid-column: 1 / -1; }
}

/* Documento de vista previa */
.doc-stamp { border: 2px solid rgb(var(--v-theme-error)); border-radius: 8px; padding: 10px 16px; min-inline-size: 190px; }
.doc-table :deep(th) { font-size: .72rem; letter-spacing: .05em; }
.doc-total td { border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
