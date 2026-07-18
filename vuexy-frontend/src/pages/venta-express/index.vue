<template>
  <VContainer fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4">
      <VIcon icon="mdi-flash" size="30" color="warning" />
      <div>
        <h1 class="text-h5 font-weight-bold">Venta Express</h1>
        <p class="text-caption text-grey mt-1">Emite una boleta o factura rápida directo a Bsale</p>
      </div>
      <VSpacer />
      <VBtnToggle v-model="tipo" mandatory color="primary" density="comfortable" variant="outlined">
        <VBtn value="boleta" prepend-icon="mdi-receipt-text-outline">Boleta</VBtn>
        <VBtn value="factura" prepend-icon="mdi-file-document-outline">Factura</VBtn>
      </VBtnToggle>
    </div>

    <VRow>
      <!-- ── Columna izquierda: documento ─────────────────────────────── -->
      <VCol cols="12" md="8">
        <VCard>
          <VCardText>
            <!-- Buscador de productos -->
            <VMenu v-model="prodMenu" :close-on-content-click="false" location="bottom">
              <template #activator="{ props }">
                <VTextField
                  v-bind="props"
                  v-model="prodSearch"
                  label="Buscar producto o servicio"
                  prepend-inner-icon="mdi-magnify"
                  variant="outlined"
                  density="compact"
                  hide-details
                  clearable
                  @update:model-value="buscarProductos"
                />
              </template>
              <VList v-if="prodResults.length" density="compact" style="max-height:280px">
                <VListItem
                  v-for="p in prodResults"
                  :key="p.id"
                  @click="agregarProducto(p)"
                >
                  <VListItemTitle>
                    {{ p.nombre }}
                    <VChip v-if="p.es_vidrio" size="x-small" color="info" variant="tonal" class="ml-1">por m²</VChip>
                  </VListItemTitle>
                  <template #append>
                    <span class="text-body-2 font-weight-bold">
                      {{ clp(p.precio_venta) }}<span v-if="p.es_vidrio" class="text-caption text-medium-emphasis">/m²</span>
                    </span>
                  </template>
                </VListItem>
              </VList>
              <VList v-else-if="prodSearch && prodSearch.length >= 2" density="compact">
                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">Sin resultados</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>

            <div class="d-flex justify-end mt-2">
              <VBtn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="agregarManual">
                Agregar ítem manual
              </VBtn>
            </div>

            <!-- Tabla de ítems -->
            <VTable density="compact" class="mt-2">
              <thead>
                <tr>
                  <th style="width:90px">Cant.</th>
                  <th>Detalle</th>
                  <th style="width:130px" class="text-right">$/unidad (neto)</th>
                  <th style="width:90px" class="text-right">% desc.</th>
                  <th style="width:120px" class="text-right">Subtotal</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!items.length">
                  <td colspan="6" class="text-center text-caption text-medium-emphasis py-6">
                    Busca un producto o agrega un ítem manual para empezar
                  </td>
                </tr>
                <tr v-for="(it, i) in items" :key="i">
                  <td>
                    <VTextField v-model.number="it.cantidad" type="number" min="0" density="compact" variant="plain" hide-details style="width:70px" />
                  </td>
                  <td>
                    <VTextField v-model="it.nombre" density="compact" variant="plain" hide-details placeholder="Detalle" />
                  </td>
                  <td>
                    <VTextField v-model.number="it.precio" type="number" min="0" density="compact" variant="plain" hide-details reverse />
                  </td>
                  <td>
                    <VTextField v-model.number="it.descuento" type="number" min="0" max="100" density="compact" variant="plain" hide-details reverse />
                  </td>
                  <td class="text-right font-weight-medium">{{ clp(subtotalItem(it)) }}</td>
                  <td>
                    <VBtn icon size="x-small" variant="text" color="error" @click="items.splice(i, 1)">
                      <VIcon size="16">mdi-close</VIcon>
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <!-- ── Columna derecha: cliente, pago, total ────────────────────── -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <!-- Cliente -->
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-subtitle-2 font-weight-bold">Cliente</span>
              <VChip v-if="tipo === 'factura'" size="x-small" color="error" variant="tonal">Obligatorio</VChip>
              <VChip v-else size="x-small" color="grey" variant="tonal">Opcional</VChip>
            </div>

            <div v-if="cliente" class="d-flex align-center justify-space-between pa-2 rounded bg-surface-variant mb-2">
              <div>
                <div class="text-body-2 font-weight-medium">{{ cliente.nombre }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ cliente.identification || 'Sin RUT' }}
                  <span v-if="!cliente.bsale_id" class="text-warning"> · sin Bsale</span>
                </div>
              </div>
              <VBtn icon size="x-small" variant="text" @click="cliente = null"><VIcon size="16">mdi-close</VIcon></VBtn>
            </div>

            <template v-else>
              <VMenu v-model="cliMenu" :close-on-content-click="false" location="bottom">
                <template #activator="{ props }">
                  <VTextField
                    v-bind="props"
                    v-model="cliSearch"
                    label="Buscar cliente (nombre o RUT)"
                    prepend-inner-icon="mdi-account-search"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    @update:model-value="buscarClientes"
                  />
                </template>
                <VList v-if="cliResults.length" density="compact" style="max-height:260px">
                  <VListItem v-for="c in cliResults" :key="c.id" @click="seleccionarCliente(c)">
                    <VListItemTitle>{{ nombreCli(c) }}</VListItemTitle>
                    <VListItemSubtitle>
                      {{ c.identification || 'Sin RUT' }}
                      <span v-if="!c.bsale_id" class="text-warning"> · sin Bsale</span>
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
                <VList v-else-if="cliSearch && cliSearch.length >= 2" density="compact">
                  <VListItem><VListItemTitle class="text-caption text-medium-emphasis">Sin resultados</VListItemTitle></VListItem>
                </VList>
              </VMenu>
              <VBtn size="small" variant="text" color="primary" prepend-icon="mdi-account-plus" class="mt-1" @click="abrirCrearCliente">
                Crear cliente nuevo
              </VBtn>
            </template>

            <VDivider class="my-3" />

            <!-- Forma de pago -->
            <VSelect
              v-model="formaPago"
              :items="formasPago"
              item-title="label"
              item-value="value"
              label="Forma de pago"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-3"
            />

            <!-- Totales -->
            <div class="d-flex justify-space-between text-body-2 mb-1">
              <span>Neto</span><span>{{ clp(totalNeto) }}</span>
            </div>
            <div class="d-flex justify-space-between text-body-2 mb-1">
              <span>IVA (19%)</span><span>{{ clp(totalIva) }}</span>
            </div>
            <div class="d-flex justify-space-between text-h6 font-weight-bold mt-1">
              <span>Total</span><span>{{ clp(totalBruto) }}</span>
            </div>

            <VBtn
              block
              size="large"
              color="primary"
              class="mt-4"
              :loading="emitiendo"
              :disabled="!puedeEmitir"
              prepend-icon="mdi-check"
              @click="emitir"
            >
              Emitir {{ tipo === 'boleta' ? 'Boleta' : 'Factura' }}
            </VBtn>
            <p v-if="tipo === 'factura' && (!cliente || !cliente.bsale_id)" class="text-caption text-warning mt-2 mb-0 text-center">
              La factura requiere un cliente con RUT sincronizado en Bsale.
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ── Dialog crear cliente ─────────────────────────────────────────── -->
    <VDialog v-model="cliDialog.show" max-width="520">
      <VCard>
        <VCardTitle class="pa-4 pb-2 d-flex align-center gap-2">
          <VIcon color="primary">mdi-account-plus</VIcon> Crear cliente
        </VCardTitle>
        <VCardText>
          <VBtnToggle v-model="cliDialog.tipo" mandatory color="primary" density="compact" variant="outlined" class="mb-3">
            <VBtn value="empresa" size="small">Empresa</VBtn>
            <VBtn value="persona" size="small">Persona</VBtn>
          </VBtnToggle>

          <VTextField v-if="cliDialog.tipo === 'empresa'" v-model="cliDialog.company" label="Razón social" variant="outlined" density="compact" class="mb-2" hide-details />
          <VRow v-else dense>
            <VCol cols="6"><VTextField v-model="cliDialog.firstName" label="Nombre" variant="outlined" density="compact" hide-details /></VCol>
            <VCol cols="6"><VTextField v-model="cliDialog.lastName" label="Apellido" variant="outlined" density="compact" hide-details /></VCol>
          </VRow>

          <VTextField v-model="cliDialog.code" label="RUT (ej: 12345678-9)" variant="outlined" density="compact" class="mt-2 mb-2" hide-details />
          <VRow dense>
            <VCol cols="6"><VTextField v-model="cliDialog.email" label="Email" variant="outlined" density="compact" hide-details /></VCol>
            <VCol cols="6"><VTextField v-model="cliDialog.phone" label="Teléfono" variant="outlined" density="compact" hide-details /></VCol>
          </VRow>
          <VTextField v-model="cliDialog.activity" label="Giro" variant="outlined" density="compact" class="mt-2" hide-details />
          <VTextField v-model="cliDialog.address" label="Dirección" variant="outlined" density="compact" class="mt-2" hide-details />
          <p class="text-caption text-medium-emphasis mt-2 mb-0">Se crea en Bsale y se guarda en la app.</p>
        </VCardText>
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="cliDialog.show = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="cliDialog.loading" @click="guardarCliente">Crear</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog medidas de vidrio ─────────────────────────────────────── -->
    <VDialog v-model="medidas.show" max-width="440">
      <VCard v-if="medidas.producto">
        <VCardTitle class="pa-4 pb-2 d-flex align-center gap-2">
          <VIcon color="info">mdi-ruler-square</VIcon> Medidas del vidrio
        </VCardTitle>
        <VCardText>
          <div class="text-body-2 font-weight-medium mb-1">{{ medidas.producto.nombre }}</div>
          <div class="text-caption text-medium-emphasis mb-3">{{ clp(medidas.producto.precio_venta) }} / m²</div>
          <VRow dense>
            <VCol cols="4"><VTextField v-model.number="medidas.ancho" label="Ancho (mm)" type="number" min="0" variant="outlined" density="compact" hide-details autofocus /></VCol>
            <VCol cols="4"><VTextField v-model.number="medidas.alto" label="Alto (mm)" type="number" min="0" variant="outlined" density="compact" hide-details /></VCol>
            <VCol cols="4"><VTextField v-model.number="medidas.piezas" label="Piezas" type="number" min="1" variant="outlined" density="compact" hide-details /></VCol>
          </VRow>

          <VCheckbox
            v-model="medidas.pulido"
            density="compact"
            hide-details
            color="info"
            class="mt-2"
            label="Pulido (+20%)"
          />

          <div class="mt-3 pa-2 rounded bg-surface-variant">
            <div class="d-flex justify-space-between text-body-2">
              <span>Vidrio · {{ m2Calculado }} m²</span>
              <span>{{ clp(vidrioNeto) }}</span>
            </div>
            <div v-if="medidas.pulido" class="d-flex justify-space-between text-body-2 text-info">
              <span>Pulido (20%)</span>
              <span>{{ clp(pulidoMonto) }}</span>
            </div>
            <VDivider class="my-1" />
            <div class="d-flex justify-space-between text-body-1 font-weight-bold">
              <span>Subtotal neto</span>
              <span>{{ clp(vidrioNeto + pulidoMonto) }}</span>
            </div>
          </div>
        </VCardText>
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="medidas.show = false">Cancelar</VBtn>
          <VBtn color="primary" :disabled="m2Calculado <= 0" @click="confirmarMedidas">Agregar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog resultado ─────────────────────────────────────────────── -->
    <VDialog v-model="resultado.show" max-width="420">
      <VCard>
        <VCardText class="text-center pa-6">
          <VIcon size="56" color="success" class="mb-2">mdi-check-circle</VIcon>
          <h2 class="text-h6 font-weight-bold mb-1">{{ resultado.tipo }} emitida</h2>
          <p class="text-body-2 mb-1">N° {{ resultado.numero }} · {{ resultado.cliente }}</p>
          <p class="text-h6 font-weight-bold text-success">{{ clp(resultado.total) }}</p>
          <div class="d-flex gap-2 justify-center mt-4">
            <VBtn v-if="resultado.pdf" color="error" variant="tonal" prepend-icon="mdi-file-pdf-box" :href="resultado.pdf" target="_blank">Ver PDF</VBtn>
            <VBtn color="primary" @click="nuevaVenta">Nueva venta</VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snack.show" :color="snack.color" timeout="4000" location="top">{{ snack.msg }}</VSnackbar>
  </VContainer>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/axiosInstance'

const tipo = ref('boleta')

// ── Ítems ────────────────────────────────────────────────────────────────
const items = ref([])

function subtotalItem(it) {
  const bruto = (Number(it.cantidad) || 0) * (Number(it.precio) || 0)
  return Math.round(bruto * (1 - (Number(it.descuento) || 0) / 100))
}
const totalNeto  = computed(() => items.value.reduce((s, it) => s + subtotalItem(it), 0))
const totalIva   = computed(() => Math.round(totalNeto.value * 0.19))
const totalBruto = computed(() => totalNeto.value + totalIva.value)

// ── Buscador de productos ────────────────────────────────────────────────
const prodSearch  = ref('')
const prodResults = ref([])
const prodMenu    = ref(false)
let prodTimer = null

function buscarProductos(q) {
  clearTimeout(prodTimer)
  if (!q || q.length < 2) { prodResults.value = []; prodMenu.value = false; return }
  prodTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/venta-express/productos', { params: { q } })
      prodResults.value = data
      prodMenu.value = true
    } catch { prodResults.value = [] }
  }, 300)
}

function agregarProducto(p) {
  prodMenu.value = false
  if (p.es_vidrio) {
    // Vidrio: pedir medidas para calcular m²
    medidas.value = { show: true, producto: p, ancho: null, alto: null, piezas: 1, pulido: false }
  } else {
    items.value.push({ nombre: p.nombre, cantidad: 1, precio: p.precio_venta, descuento: 0 })
  }
  prodSearch.value = ''
  prodResults.value = []
}

// ── Medidas de vidrio (venta por m²) ───────────────────────────────────────
const PULIDO_PCT = 0.20 // el pulido cuesta 20% más sobre el valor del vidrio
const medidas = ref({ show: false, producto: null, ancho: null, alto: null, piezas: 1, pulido: false })

const m2Calculado = computed(() => {
  const a = Number(medidas.value.ancho) || 0
  const al = Number(medidas.value.alto) || 0
  const pz = Number(medidas.value.piezas) || 0
  return +((a / 1000) * (al / 1000) * pz).toFixed(4)
})

const vidrioNeto = computed(() => Math.round(m2Calculado.value * (medidas.value.producto?.precio_venta || 0)))
const pulidoMonto = computed(() => medidas.value.pulido ? Math.round(vidrioNeto.value * PULIDO_PCT) : 0)

function confirmarMedidas() {
  const m = medidas.value
  if (!(Number(m.ancho) > 0) || !(Number(m.alto) > 0) || !(Number(m.piezas) > 0)) {
    snackMsg('Ingresa ancho, alto y piezas', 'warning')
    return
  }
  const pz = Number(m.piezas)
  // El pulido se integra en la misma línea: precio/m² +20% y se anota en el detalle
  const precioM2 = m.pulido
    ? Math.round((m.producto.precio_venta || 0) * (1 + PULIDO_PCT))
    : (m.producto.precio_venta || 0)
  const detalle = `${m.producto.nombre} · ${m.ancho}×${m.alto} mm${pz > 1 ? ` (${pz}u)` : ''}${m.pulido ? ' · con pulido' : ''}`
  items.value.push({
    nombre: detalle,
    cantidad: m2Calculado.value, // la cantidad es el total de m²
    precio: precioM2,            // precio por m² (con pulido si aplica)
    descuento: 0,
  })
  medidas.value.show = false
}

function agregarManual() {
  items.value.push({ nombre: '', cantidad: 1, precio: 0, descuento: 0 })
}

// ── Cliente ──────────────────────────────────────────────────────────────
const cliente    = ref(null)
const cliSearch  = ref('')
const cliResults = ref([])
const cliMenu    = ref(false)
let cliTimer = null

function nombreCli(c) {
  return c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Cliente'
}

function buscarClientes(q) {
  clearTimeout(cliTimer)
  if (!q || q.length < 2) { cliResults.value = []; cliMenu.value = false; return }
  cliTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/api/clientes/buscar', { params: { q } })
      cliResults.value = Array.isArray(data) ? data : (data.clientes || data.data || [])
      cliMenu.value = true
    } catch { cliResults.value = [] }
  }, 300)
}

function seleccionarCliente(c) {
  cliente.value = {
    id: c.id,
    nombre: nombreCli(c),
    identification: c.identification,
    bsale_id: c.bsale_id,
  }
  cliMenu.value = false
  cliSearch.value = ''
  cliResults.value = []
}

// Crear cliente
const cliDialog = ref({ show: false, loading: false, tipo: 'empresa', company: '', firstName: '', lastName: '', code: '', email: '', phone: '', activity: '', address: '' })

function abrirCrearCliente() {
  cliDialog.value = { show: true, loading: false, tipo: 'empresa', company: '', firstName: '', lastName: '', code: '', email: '', phone: '', activity: '', address: '' }
}

async function guardarCliente() {
  const d = cliDialog.value
  if (!d.code) { snackMsg('Ingresa el RUT del cliente', 'warning'); return }
  d.loading = true
  try {
    const payload = {
      code: d.code,
      email: d.email || undefined,
      phone: d.phone || undefined,
      activity: d.activity || undefined,
      address: d.address || undefined,
    }
    if (d.tipo === 'empresa') payload.company = d.company
    else { payload.firstName = d.firstName; payload.lastName = d.lastName }

    const { data } = await api.post('/api/bsale-clientes/crear', payload)
    const c = data.cliente
    seleccionarCliente(c)
    cliDialog.value.show = false
    snackMsg('Cliente creado en Bsale y en la app')
  } catch (e) {
    snackMsg(e.response?.data?.error || 'Error al crear el cliente', 'error')
  } finally {
    d.loading = false
  }
}

// ── Forma de pago ────────────────────────────────────────────────────────
const formaPago = ref('efectivo')
const formasPago = [
  { label: 'Efectivo',        value: 'efectivo' },
  { label: 'Transferencia',   value: 'transferencia' },
  { label: 'Tarjeta Débito',  value: 'tarjeta_debito' },
  { label: 'Tarjeta Crédito', value: 'tarjeta_credito' },
  { label: 'Cheque',          value: 'cheque' },
  { label: 'Webpay',          value: 'webpay' },
]

// ── Emitir ───────────────────────────────────────────────────────────────
const emitiendo = ref(false)
const resultado = ref({ show: false, tipo: '', numero: '', total: 0, pdf: '', cliente: '' })

const puedeEmitir = computed(() => {
  if (!items.value.length) return false
  if (items.value.some(it => !it.nombre || !(Number(it.cantidad) > 0))) return false
  if (tipo.value === 'factura' && (!cliente.value || !cliente.value.bsale_id)) return false
  return true
})

async function emitir() {
  if (!puedeEmitir.value) return
  emitiendo.value = true
  try {
    const { data } = await api.post('/api/venta-express/emitir', {
      tipo: tipo.value,
      cliente_id: cliente.value?.id || undefined,
      forma_pago: formaPago.value,
      items: items.value.map(it => ({
        nombre: it.nombre,
        cantidad: Number(it.cantidad),
        precio: Number(it.precio),
        descuento: Number(it.descuento) || 0,
      })),
    })
    resultado.value = {
      show: true,
      tipo: data.documento.tipo,
      numero: data.documento.numero,
      total: data.documento.total,
      pdf: data.documento.pdf,
      cliente: data.documento.cliente,
    }
  } catch (e) {
    snackMsg(e.response?.data?.error || 'No se pudo emitir el documento', 'error')
  } finally {
    emitiendo.value = false
  }
}

function nuevaVenta() {
  items.value = []
  cliente.value = null
  formaPago.value = 'efectivo'
  resultado.value.show = false
}

// ── Helpers ──────────────────────────────────────────────────────────────
const snack = ref({ show: false, color: 'success', msg: '' })
function snackMsg(msg, color = 'success') { snack.value = { show: true, color, msg } }
const clp = v => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)
</script>
