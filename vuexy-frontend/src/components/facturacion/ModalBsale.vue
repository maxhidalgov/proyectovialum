<template>
  <v-dialog v-model="dialog" max-width="520" persistent>
    <v-card v-if="cotizacion">
      <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
        <v-icon color="success">mdi-receipt-text</v-icon>
        Emitir documento
        <v-spacer />
        <v-btn icon size="x-small" variant="text" @click="cerrar">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-card-subtitle class="px-4 pb-3">
        {{ nombreCliente }} — Total: <strong>{{ clp(cotizacion.total) }}</strong>
        <span v-if="yaEmitido > 0" class="ml-2 text-caption">
          · Ya emitido: <strong class="text-success">{{ clp(yaEmitido) }}</strong>
          · Saldo: <strong class="text-warning">{{ clp(cotizacion.total - yaEmitido) }}</strong>
        </span>
      </v-card-subtitle>

      <v-divider />

      <v-card-text class="pa-4">
        <!-- Resumen previo si ya hay documentos -->
        <v-alert
          v-if="yaEmitido > 0"
          type="info" density="compact" variant="tonal" class="mb-3 text-caption"
        >
          Ya se emitieron <strong>{{ clp(yaEmitido) }}</strong> ({{ pctYaEmitido }}%).
          El saldo pendiente es <strong>{{ clp(cotizacion.total - yaEmitido) }}</strong> ({{ 100 - pctYaEmitido }}%).
        </v-alert>

        <!-- ¿Cuánto vas a facturar? -->
        <p class="text-caption text-medium-emphasis mb-2 font-weight-medium">¿Cuánto vas a facturar?</p>
        <div class="d-flex gap-2 flex-wrap mb-1">
          <v-chip
            v-for="opt in opcionesDisponibles"
            :key="opt.value"
            :color="porcentaje === opt.value && !personalizado ? 'primary' : undefined"
            :variant="porcentaje === opt.value && !personalizado ? 'flat' : 'outlined'"
            size="small"
            clickable
            @click="seleccionarPorcentaje(opt.value)"
          >
            {{ opt.label }}
          </v-chip>
          <v-chip
            :color="personalizado ? 'primary' : undefined"
            :variant="personalizado ? 'flat' : 'outlined'"
            size="small"
            clickable
            @click="personalizado = true"
          >
            Otro monto
          </v-chip>
        </div>

        <!-- Input personalizado -->
        <div v-if="personalizado" class="d-flex align-center gap-2 mt-2 mb-1">
          <v-text-field
            v-model.number="porcentaje"
            type="number"
            min="1" :max="100 - pctYaEmitido"
            density="compact"
            variant="outlined"
            hide-details
            suffix="%"
            style="max-width: 100px"
          />
          <span class="text-body-2 text-medium-emphasis">= {{ clp(montoCalculado) }}</span>
        </div>

        <!-- Monto resultante -->
        <div v-if="!personalizado" class="text-body-2 text-medium-emphasis mb-3">
          Monto a facturar: <strong class="text-success">{{ clp(montoCalculado) }}</strong>
        </div>

        <v-divider class="my-3" />

        <!-- Paso 2: Tipo de documento y cliente -->
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.tipo_documento"
              :items="tiposDocumento"
              item-value="id"
              item-title="name"
              label="Tipo de documento"
              density="compact"
              variant="outlined"
              hide-details
              :loading="cargando"
              :rules="[v => !!v || 'Requerido']"
            />
          </v-col>
          <v-col cols="12" class="mt-2">
            <v-autocomplete
              v-model="form.cliente_facturacion_id"
              :items="clientesSincronizados"
              item-value="id"
              :item-title="(c) => `${c.razon_social} (${c.identification || 'Sin RUT'})`"
              :label="form.tipo_documento == 1 ? 'Cliente (opcional para boleta)' : 'Cliente para factura'"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              :loading="cargandoClientes"
              :rules="form.tipo_documento != 1 ? [v => !!v || 'Requerido para facturas'] : []"
            >
              <template #no-data>
                <v-list-item>
                  <v-list-item-title class="text-caption">No hay clientes sincronizados con Bsale</v-list-item-title>
                </v-list-item>
              </template>
            </v-autocomplete>
          </v-col>
        </v-row>

        <!-- Formas de pago (una o varias) -->
        <div class="d-flex align-center justify-space-between mt-3 mb-1">
          <span class="text-subtitle-2 font-weight-bold">Formas de pago</span>
          <v-btn size="x-small" variant="text" color="primary" prepend-icon="mdi-plus" @click="agregarPago">Dividir</v-btn>
        </div>
        <div v-for="(p, i) in pagos" :key="i" class="mb-2">
          <div class="d-flex align-center gap-2">
            <v-select
              v-model="p.forma_pago"
              :items="metodosPago"
              item-title="text"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              style="max-width:170px"
            />
            <v-text-field v-model.number="p.monto" type="number" min="0" density="compact" variant="outlined" hide-details reverse prefix="$" />
            <v-btn v-if="pagos.length > 1" icon size="x-small" variant="text" color="error" @click="pagos.splice(i, 1)"><v-icon size="16">mdi-close</v-icon></v-btn>
          </div>
          <v-text-field
            v-if="esTarjeta(p.forma_pago)"
            v-model="p.voucher"
            label="N° voucher Transbank"
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-credit-card-outline"
            class="mt-2"
            :error="!p.voucher"
          />
        </div>
        <div v-if="pagos.length > 1" class="d-flex justify-space-between text-caption mb-2" :class="pagosOk ? 'text-success' : 'text-warning'">
          <span>Asignado</span>
          <span>{{ clp(totalPagos) }} / {{ clp(montoCalculado) }}{{ pagosOk ? ' ✓' : '' }}</span>
        </div>

        <!-- Observaciones -->
        <v-textarea
          v-model="form.observaciones"
          label="Observaciones / nota (opcional)"
          density="compact"
          variant="outlined"
          rows="2"
          auto-grow
          hide-details
          class="mt-2"
        />

        <!-- Referencias (ej. orden de compra) -->
        <div class="d-flex align-center justify-space-between mt-3 mb-1">
          <span class="text-subtitle-2 font-weight-bold">Referencias</span>
          <v-btn size="x-small" variant="text" color="primary" prepend-icon="mdi-plus" @click="agregarReferencia">Agregar</v-btn>
        </div>
        <div v-for="(r, i) in referencias" :key="'ref' + i" class="d-flex align-center gap-2 mb-2">
          <v-select v-model="r.code_sii" :items="tiposReferencia" item-title="label" item-value="value" density="compact" variant="outlined" hide-details style="max-width:150px" />
          <v-text-field v-model="r.numero" label="N°" density="compact" variant="outlined" hide-details style="max-width:100px" />
          <v-text-field v-model="r.fecha" type="date" density="compact" variant="outlined" hide-details title="Fecha del documento (opcional)" />
          <v-btn icon size="x-small" variant="text" color="error" @click="referencias.splice(i, 1)"><v-icon size="16">mdi-close</v-icon></v-btn>
        </div>

        <!-- Error -->
        <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mt-3">
          {{ error }}
        </v-alert>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn variant="text" @click="cerrar" :disabled="generando">Cancelar</v-btn>
        <v-spacer />
        <v-btn
          color="success"
          variant="flat"
          :loading="generando"
          :disabled="!form.tipo_documento || porcentaje < 1 || porcentaje > 100 || !pagosOk || !vouchersOk || referencias.some(r => !r.numero)"
          @click="generar"
        >
          <v-icon start>mdi-receipt</v-icon>
          Generar {{ clp(montoCalculado) }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/axiosInstance'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  cotizacion: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'documento-generado'])

const dialog = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

// ── Estado ───────────────────────────────────────────────────────────
const cargando          = ref(false)
const cargandoClientes  = ref(false)
const generando         = ref(false)
const error             = ref('')
const personalizado     = ref(false)
const porcentaje        = ref(100)
const tiposDocumento    = ref([])
const clientesSincronizados = ref([])

const form = ref({
  tipo_documento: null,
  observaciones: '',
  cliente_facturacion_id: null,
})

// Pagos (uno o varios), observaciones y referencias
const pagos = ref([{ forma_pago: 'transferencia', monto: 0, voucher: '' }])
const esTarjeta = (fp) => ['tarjeta_debito', 'tarjeta_credito'].includes(fp)
const totalPagos = computed(() => pagos.value.reduce((s, p) => s + (Number(p.monto) || 0), 0))
const pagosOk = computed(() => Math.abs(totalPagos.value - montoCalculado.value) < 1)
const vouchersOk = computed(() => pagos.value.every(p => !esTarjeta(p.forma_pago) || !!p.voucher))
function agregarPago() {
  pagos.value.push({ forma_pago: 'efectivo', monto: Math.max(0, montoCalculado.value - totalPagos.value), voucher: '' })
}

const referencias = ref([])
const tiposReferencia = [
  { label: 'Orden de Compra', value: 801 },
  { label: 'Nota de Pedido',  value: 802 },
  { label: 'Guía de Despacho', value: 52 },
  { label: 'Factura',         value: 33 },
]
function agregarReferencia() {
  referencias.value.push({ code_sii: 801, numero: '', fecha: '' })
}

// opciones base — se filtran/adaptan según saldo disponible
const OPCIONES_BASE = [
  { label: 'Total (100%)', value: 100 },
  { label: 'Anticipo 50%', value: 50 },
  { label: 'Anticipo 30%', value: 30 },
]

const metodosPago = [
  { text: 'Transferencia', value: 'transferencia' },
  { text: 'Efectivo',      value: 'efectivo' },
  { text: 'Cheque',        value: 'cheque' },
  { text: 'Tarjeta débito', value: 'tarjeta_debito' },
  { text: 'Tarjeta crédito', value: 'tarjeta_credito' },
]

// ── Computed ─────────────────────────────────────────────────────────
const yaEmitido = computed(() => {
  const docs = props.cotizacion?.documentos_facturacion || []
  return docs.filter(d => d.estado === 'emitido').reduce((s, d) => s + Number(d.monto), 0)
})

const pctYaEmitido = computed(() => {
  const total = props.cotizacion?.total || 0
  return total > 0 ? Math.round((yaEmitido.value / total) * 100) : 0
})

const pctSaldo = computed(() => 100 - pctYaEmitido.value)

// Opciones contextuales: si hay saldo, mostrar "Saldo (X%)" primero
const opcionesDisponibles = computed(() => {
  if (pctYaEmitido.value > 0) {
    // Ya hay algo emitido — ofrecer el saldo exacto + opciones menores
    const opciones = [{ label: `Saldo (${pctSaldo.value}%)`, value: pctSaldo.value }]
    OPCIONES_BASE.forEach(opt => {
      if (opt.value < pctSaldo.value) opciones.push(opt)
    })
    return opciones
  }
  return OPCIONES_BASE
})

const montoCalculado = computed(() =>
  Math.round((props.cotizacion?.total || 0) * (porcentaje.value || 0) / 100)
)

// Si hay una sola forma de pago, se mantiene igual al monto del documento
watch(montoCalculado, (t) => {
  if (pagos.value.length === 1) pagos.value[0].monto = t
}, { immediate: true })

const nombreCliente = computed(() => {
  const c = props.cotizacion?.cliente
  if (!c) return 'Sin cliente'
  return c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Sin nombre'
})

// ── Watcher: cargar datos al abrir ───────────────────────────────────
watch(() => props.modelValue, (val) => {
  if (val) inicializar()
})

async function inicializar() {
  error.value = ''
  personalizado.value = false
  form.value = { tipo_documento: null, observaciones: '', cliente_facturacion_id: null }
  pagos.value = [{ forma_pago: 'transferencia', monto: 0, voucher: '' }]
  referencias.value = []
  // Pre-seleccionar saldo si ya hay documentos emitidos
  porcentaje.value = pctSaldo.value > 0 && pctSaldo.value < 100 ? pctSaldo.value : 100

  cargando.value = true
  cargandoClientes.value = true
  try {
    const [tipos, clientes] = await Promise.all([
      api.get('/api/bsale-tipos-documento'),
      api.get('/api/bsale/clientes-sincronizados'),
    ])
    tiposDocumento.value = tipos.data.items?.map(t => ({ id: t.id, name: t.name })) || []
    clientesSincronizados.value = clientes.data.clientes || []

    // Preseleccionar cliente si la cotización ya tiene uno asignado
    if (props.cotizacion?.cliente_facturacion_id) {
      const found = clientesSincronizados.value.find(c => c.id === props.cotizacion.cliente_facturacion_id)
      if (found) form.value.cliente_facturacion_id = found.id
    } else if (props.cotizacion?.cliente_id) {
      const found = clientesSincronizados.value.find(c => c.id === props.cotizacion.cliente_id)
      if (found) form.value.cliente_facturacion_id = found.id
    }
  } catch {
    // tipos de documento fallback
    tiposDocumento.value = [
      { id: 1,  name: 'Boleta Electrónica' },
      { id: 5,  name: 'Factura Electrónica' },
      { id: 3,  name: 'Nota de Venta' },
      { id: 24, name: 'Cotización' },
    ]
  } finally {
    cargando.value = false
    cargandoClientes.value = false
  }
}

// ── Acciones ─────────────────────────────────────────────────────────
function seleccionarPorcentaje(val) {
  porcentaje.value = val
  personalizado.value = false
}

async function generar() {
  error.value = ''
  if (!form.value.tipo_documento) { error.value = 'Selecciona el tipo de documento'; return }
  if (form.value.tipo_documento != 1 && !form.value.cliente_facturacion_id) {
    error.value = 'Para facturas debes seleccionar un cliente con RUT en Bsale'; return
  }

  generando.value = true
  try {
    const { data } = await api.post('/api/bsale/documento', {
      cotizacion_id:          props.cotizacion.id,
      tipo_documento:         form.value.tipo_documento,
      cliente_facturacion_id: form.value.cliente_facturacion_id,
      observaciones:          form.value.observaciones || undefined,
      porcentaje:             porcentaje.value,
      pagos:                  pagos.value.map(p => ({ forma_pago: p.forma_pago, monto: Number(p.monto), voucher: p.voucher || undefined })),
      referencias:            referencias.value
        .filter(r => r.numero)
        .map(r => ({
          code_sii: r.code_sii,
          numero: String(r.numero),
          fecha: r.fecha || undefined,
          razon: `${(tiposReferencia.find(t => t.value === r.code_sii)?.label) || 'Ref'} ${r.numero}`,
        })),
    })

    if (data.success) {
      emit('documento-generado', data)
      if (data.documento?.urlPdf) window.open(data.documento.urlPdf, '_blank')
      cerrar()
    } else {
      error.value = data.error || 'Error al generar documento'
    }
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Error al generar documento'
  } finally {
    generando.value = false
  }
}

function cerrar() {
  dialog.value = false
}

const clp = (n) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)
</script>
