<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardTitle class="d-flex align-center gap-3 pa-4">
          <VIcon icon="mdi-receipt-text-outline" color="primary" />
          <span>Boletas — Resúmenes Mensuales</span>
          <VSpacer />
          <VSelect
            v-model="periodoFiltro"
            :items="periodos"
            label="Periodo"
            density="compact"
            hide-details
            clearable
            style="max-width:160px"
          />
          <VBtn
            variant="outlined"
            size="small"
            color="warning"
            :loading="backfilling"
            prepend-icon="mdi-download-outline"
            @click="backfillFormaPago"
            title="Consulta Bsale y rellena la forma de pago en boletas 2026 ya sincronizadas"
          >
            Backfill Forma Pago
          </VBtn>
          <VBtn
            variant="outlined"
            size="small"
            :loading="recalculando"
            prepend-icon="mdi-refresh"
            @click="recalcular"
          >
            Recalcular
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VAlert v-if="backfillMsg" type="info" density="compact" class="ma-3" closable @click:close="backfillMsg=''">
          {{ backfillMsg }}
        </VAlert>

        <VCardText v-if="loading" class="text-center pa-8">
          <VProgressCircular indeterminate color="primary" />
        </VCardText>

        <VTable v-else density="compact">
          <thead>
            <tr>
              <th>Periodo</th>
              <th>Forma de Pago</th>
              <th class="text-right">N° Boletas</th>
              <th class="text-right">Monto Total</th>
              <th class="text-right">Monto Conciliado</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="resumenes.length === 0">
              <tr>
                <td colspan="7" class="text-center pa-6 text-medium-emphasis">
                  Sin resúmenes. Sincroniza boletas 2026 y luego haz clic en "Recalcular".
                </td>
              </tr>
            </template>
            <tr v-for="r in resumenes" :key="r.id">
              <td>{{ r.periodo }}</td>
              <td>
                <VChip :color="colorFormaPago(r.forma_pago)" size="small" label>
                  {{ labelFormaPago(r.forma_pago) }}
                </VChip>
              </td>
              <td class="text-right">{{ r.total_boletas }}</td>
              <td class="text-right">{{ fmt(r.monto_total) }}</td>
              <td class="text-right">{{ fmt(montoVinculado(r)) }}</td>
              <td>
                <VChip
                  :color="estaConciliado(r) ? 'success' : 'warning'"
                  size="small"
                  label
                >
                  {{ estaConciliado(r)
                     ? (Number(r.conciliado_transbank) > 0 && Number(r.conciliado) === 0 ? '✓ Conciliado (Transbank)' : '✓ Conciliado')
                     : 'Por conciliar' }}
                </VChip>
              </td>
              <td>
                <VBtn
                  icon size="small" variant="text"
                  @click="abrirDetalle(r)"
                  title="Ver boletas"
                >
                  <VIcon icon="mdi-eye-outline" />
                </VBtn>
                <VBtn
                  v-if="!estaConciliado(r)"
                  icon size="small" variant="text" color="primary"
                  @click="abrirConciliar(r)"
                  title="Conciliar"
                >
                  <VIcon icon="mdi-bank-plus" />
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>

    <!-- ── Dialog Detalle boletas individuales ─────────────────────────── -->
    <VDialog v-model="dialogDetalle" max-width="800">
      <VCard>
        <VCardTitle class="pa-4">
          Boletas — {{ detalle?.resumen?.periodo }} / {{ labelFormaPago(detalle?.resumen?.forma_pago) }}
        </VCardTitle>
        <VDivider />
        <VCardText style="max-height:60vh;overflow-y:auto">
          <!-- movimientos vinculados -->
          <div v-if="detalle?.resumen?.movimientos?.length" class="mb-4">
            <p class="text-caption text-medium-emphasis mb-1">Movimientos bancarios vinculados</p>
            <VList density="compact" border rounded>
              <VListItem
                v-for="m in detalle.resumen.movimientos"
                :key="m.id"
              >
                <template #prepend>
                  <VIcon icon="mdi-bank-check" color="success" size="18" />
                </template>
                <VListItemTitle class="text-body-2">
                  {{ m.fecha }} — {{ m.descripcion }} — <strong>{{ fmt(m.monto) }}</strong>
                </VListItemTitle>
                <template #append>
                  <VBtn
                    icon size="x-small" variant="text" color="error"
                    @click="desvincular(m.id)"
                  >
                    <VIcon icon="mdi-close" />
                  </VBtn>
                </template>
              </VListItem>
            </VList>
          </div>

          <VTable density="compact">
            <thead>
              <tr>
                <th>N° Boleta</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th class="text-right">Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in detalle?.boletas" :key="b.id">
                <td>{{ b.numero_documento_bsale }}</td>
                <td>{{ b.bsale_cliente_nombre }}</td>
                <td>{{ b.fecha_emision }}</td>
                <td class="text-right">{{ fmt(b.monto) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn @click="dialogDetalle = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog Conciliar ────────────────────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1040" scrollable>
      <VCard v-if="conciliarTarget">
        <VCardTitle class="d-flex align-center pa-4">
          Conciliar boletas {{ conciliarTarget.periodo }} / {{ labelFormaPago(conciliarTarget.forma_pago) }}
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow>
            <!-- Panel izquierdo: resumen + asignados -->
            <VCol cols="12" md="5">
              <VCard variant="tonal" class="pa-3 mb-3">
                <p class="text-overline text-medium-emphasis mb-1">Resumen de boletas</p>
                <div class="d-flex justify-space-between text-caption"><span>Monto total</span><strong>{{ fmt(conciliarTarget.monto_total) }}</strong></div>
                <div class="d-flex justify-space-between text-caption"><span>Ya conciliado</span><strong class="text-success">{{ fmt(montoYaConciliado) }}</strong></div>
                <div class="d-flex justify-space-between mt-1"><span>Saldo por asignar</span><strong class="text-warning">{{ fmt(saldoResumen) }}</strong></div>
                <VProgressLinear :model-value="conciliarTarget.monto_total > 0 ? (montoYaConciliado / conciliarTarget.monto_total) * 100 : 0"
                  color="success" bg-color="warning" rounded height="6" class="mt-2" />
              </VCard>

              <p class="text-overline text-medium-emphasis mb-1">Movimientos asignados</p>
              <p v-if="!conciliarTarget.movimientos?.length" class="text-caption text-medium-emphasis">Ningún movimiento asignado aún</p>
              <VCard v-for="mv in conciliarTarget.movimientos" :key="mv.id" variant="tonal" color="success" class="mb-2 pa-2">
                <div class="d-flex align-center justify-space-between">
                  <div class="text-caption">
                    {{ (mv.fecha || '').slice(0,10) }} — {{ mv.descripcion }}
                    <div class="font-weight-bold">{{ fmt(mv.monto) }}</div>
                  </div>
                  <VBtn icon size="x-small" variant="text" color="error" @click="desvincularMov(mv.id)"><VIcon size="16">mdi-close</VIcon></VBtn>
                </div>
              </VCard>
            </VCol>

            <!-- Panel derecho: ingresos disponibles con filtros -->
            <VCol cols="12" md="7">
              <p class="text-overline text-medium-emphasis mb-2">Ingresos del banco (crédito) — asigna los que correspondan</p>
              <div class="d-flex flex-wrap mb-2" style="gap:8px">
                <VTextField v-model="busquedaMov" placeholder="Buscar descripción..." prepend-inner-icon="mdi-magnify"
                  density="compact" variant="outlined" hide-details clearable style="flex:1;min-width:180px" @update:modelValue="cargarDisponibles" />
                <VTextField v-model="filtroMonto" placeholder="Monto exacto" prepend-inner-icon="mdi-cash"
                  density="compact" variant="outlined" hide-details clearable style="max-width:150px" @update:modelValue="cargarDisponibles" />
              </div>
              <div class="d-flex flex-wrap mb-3" style="gap:8px">
                <VTextField v-model="filtroDesde" type="date" label="Desde" density="compact" variant="outlined" hide-details style="max-width:160px" clearable @update:modelValue="cargarDisponibles" />
                <VTextField v-model="filtroHasta" type="date" label="Hasta" density="compact" variant="outlined" hide-details style="max-width:160px" clearable @update:modelValue="cargarDisponibles" />
              </div>

              <div v-if="loadingDisp" class="text-center py-6"><VProgressCircular indeterminate size="28" /></div>
              <template v-else>
                <p v-if="!movDisponibles.length" class="text-caption text-medium-emphasis text-center py-6">
                  No hay ingresos crédito con saldo por asignar (ajusta los filtros).
                </p>
                <div v-else style="max-height:360px;overflow-y:auto">
                  <VTable density="compact">
                    <thead><tr><th>Fecha</th><th>Descripción</th><th class="text-right">Saldo</th><th></th></tr></thead>
                    <tbody>
                      <tr v-for="m in movDisponibles" :key="m.id"
                        :class="{ 'bg-success-lighten-5': Math.abs(Number(m.saldo_por_asignar) - saldoResumen) < 1 }">
                        <td class="text-caption text-no-wrap">{{ (m.fecha_contable || '').slice(0,10) }}</td>
                        <td class="text-caption">{{ m.descripcion }}</td>
                        <td class="text-right font-weight-bold text-success">{{ fmt(m.saldo_por_asignar) }}</td>
                        <td class="text-right" style="width:100px">
                          <VBtn size="x-small" color="primary" variant="tonal"
                            :loading="asignandoId === m.id" :disabled="saldoResumen <= 0" @click="asignarMov(m)">Seleccionar</VBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </template>
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="dialogConciliar = false">Listo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from '@/axiosInstance'

const loading      = ref(false)
const recalculando = ref(false)
const guardando    = ref(false)
const backfilling  = ref(false)
const backfillMsg  = ref('')

const resumenes     = ref([])
const periodos      = ref([])
const periodoFiltro = ref(null)

const dialogDetalle  = ref(false)
const detalle        = ref(null)

const dialogConciliar   = ref(false)
const conciliarTarget   = ref(null)
const busquedaMov       = ref('')
const filtroMonto       = ref('')
const filtroDesde       = ref('')
const filtroHasta       = ref('')
const movDisponibles    = ref([])
const loadingDisp       = ref(false)
const asignandoId       = ref(null)

// Monto ya conciliado del resumen (suma de movimientos vinculados)
const montoYaConciliado = computed(() =>
  (conciliarTarget.value?.movimientos || []).reduce((s, m) => s + Number(m.monto || 0), 0)
)
const saldoResumen = computed(() =>
  Math.max(0, Number(conciliarTarget.value?.monto_total || 0) - montoYaConciliado.value)
)

// ── Cargar resúmenes ──────────────────────────────────────────────────────

async function cargar() {
  loading.value = true
  try {
    const params = periodoFiltro.value ? { periodo: periodoFiltro.value } : {}
    const { data } = await axios.get('/api/boletas/resumenes', { params })
    resumenes.value = data.resumenes
    periodos.value  = data.periodos
  } finally {
    loading.value = false
  }
}

watch(periodoFiltro, cargar)
onMounted(cargar)

// ── Backfill forma_pago ───────────────────────────────────────────────────

async function backfillFormaPago() {
  backfilling.value = true
  backfillMsg.value = ''
  let total = 0
  try {
    while (true) {
      const { data } = await axios.post('/api/ventas/backfill-forma-pago', { limit: 50 })
      total += data.actualizados
      backfillMsg.value = `Procesando… ${total} actualizadas, ${data.pendientes} pendientes`
      if (data.pendientes === 0) break
    }
    backfillMsg.value = `✓ Listo — ${total} boletas actualizadas`
    await recalcular()
  } finally {
    backfilling.value = false
  }
}

// ── Recalcular ────────────────────────────────────────────────────────────

async function recalcular() {
  recalculando.value = true
  try {
    const params = periodoFiltro.value ? { periodo: periodoFiltro.value } : {}
    await axios.post('/api/boletas/resumenes/recalcular', params)
    await cargar()
  } finally {
    recalculando.value = false
  }
}

// ── Detalle ───────────────────────────────────────────────────────────────

async function abrirDetalle(r) {
  const { data } = await axios.get(`/api/boletas/resumenes/${r.id}/boletas`)
  detalle.value   = { resumen: r, boletas: data.boletas }
  dialogDetalle.value = true
}

// ── Conciliar ─────────────────────────────────────────────────────────────

function abrirConciliar(r) {
  conciliarTarget.value = r
  busquedaMov.value     = ''
  filtroMonto.value     = ''
  filtroDesde.value     = ''
  filtroHasta.value     = ''
  movDisponibles.value  = []
  dialogConciliar.value = true
  cargarDisponibles()
}

let buscarTimer = null
function cargarDisponibles() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(async () => {
    if (!conciliarTarget.value) return
    loadingDisp.value = true
    try {
      const { data } = await axios.get(
        `/api/boletas/resumenes/${conciliarTarget.value.id}/movimientos-disponibles`,
        { params: {
          buscar: busquedaMov.value || undefined,
          monto:  filtroMonto.value || undefined,
          desde:  filtroDesde.value || undefined,
          hasta:  filtroHasta.value || undefined,
        } }
      )
      movDisponibles.value = data.data ?? data
    } finally {
      loadingDisp.value = false
    }
  }, 250)
}

// Refresca el resumen activo (movimientos vinculados) desde la lista recargada
function refrescarTarget() {
  const id = conciliarTarget.value?.id
  const actualizado = resumenes.value.find(r => r.id === id)
  if (actualizado) conciliarTarget.value = actualizado
}

// Asigna UN movimiento al resumen (monto = menor entre su saldo y el saldo del resumen)
async function asignarMov(m) {
  const monto = Math.min(Number(m.saldo_por_asignar || 0), saldoResumen.value)
  if (monto <= 0) return
  asignandoId.value = m.id
  try {
    await axios.post(`/api/boletas/resumenes/${conciliarTarget.value.id}/conciliar`, {
      movimiento_id: m.id,
      monto,
    })
    await cargar()
    refrescarTarget()
    await cargarDisponibles()
  } finally {
    asignandoId.value = null
  }
}

async function desvincularMov(pivotId) {
  await axios.delete(`/api/boletas/resumenes/movimiento/${pivotId}`)
  await cargar()
  refrescarTarget()
  await cargarDisponibles()
}

// ── Desvincular ───────────────────────────────────────────────────────────

async function desvincular(pivotId) {
  await axios.delete(`/api/boletas/resumenes/movimiento/${pivotId}`)
  await cargar()
  if (detalle.value) {
    const { data } = await axios.get(`/api/boletas/resumenes/${detalle.value.resumen.id}/boletas`)
    detalle.value.resumen = resumenes.value.find(r => r.id === detalle.value.resumen.id) ?? detalle.value.resumen
    detalle.value.boletas = data.boletas
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────

function fmt(v) {
  return '$' + Number(v ?? 0).toLocaleString('es-CL')
}

function montoVinculado(r) {
  const vinculado = (r.movimientos ?? []).reduce((s, m) => s + Number(m.monto), 0)
  // Las boletas de tarjeta se concilian vía Transbank (marca conciliado_transbank)
  const transbank = Number(r.conciliado_transbank) > 0 ? Number(r.monto_total) : 0
  return vinculado + transbank
}

function estaConciliado(r) {
  return Number(r.conciliado) > 0 || Number(r.conciliado_transbank) > 0
}

function labelFormaPago(fp) {
  const map = {
    tarjeta_credito: 'Tarjeta Crédito',
    tarjeta_debito:  'Tarjeta Débito',
    transferencia:   'Transferencia',
    efectivo:        'Efectivo',
    cheque:          'Cheque',
    credito:         'Crédito',
    nota_credito:    'Nota Crédito',
    otros:           'Otros',
    sin_informacion: 'Sin Información',
  }
  return map[fp] ?? fp ?? '—'
}

function colorFormaPago(fp) {
  const map = {
    tarjeta_credito: 'primary',
    tarjeta_debito:  'info',
    transferencia:   'secondary',
    efectivo:        'success',
    cheque:          'warning',
    credito:         'purple',
    nota_credito:    'deep-purple',
    otros:           'grey',
    sin_informacion: 'error',
  }
  return map[fp] ?? 'default'
}
</script>
