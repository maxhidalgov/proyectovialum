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
    <VDialog v-model="dialogConciliar" max-width="720">
      <VCard>
        <VCardTitle class="pa-4">
          Conciliar boletas {{ conciliarTarget?.periodo }} / {{ labelFormaPago(conciliarTarget?.forma_pago) }}
        </VCardTitle>
        <VDivider />
        <VCardText>
          <!-- Resumen de montos -->
          <div class="d-flex flex-wrap justify-space-between mb-3" style="gap:8px">
            <span class="text-caption">Total: <strong>{{ fmt(conciliarTarget?.monto_total) }}</strong></span>
            <span class="text-caption">Ya conciliado: <strong class="text-success">{{ fmt(montoYaConciliado) }}</strong></span>
            <span class="text-caption">Por conciliar: <strong class="text-warning">{{ fmt(saldoResumen) }}</strong></span>
          </div>

          <!-- Movimientos ya vinculados -->
          <template v-if="conciliarTarget?.movimientos?.length">
            <p class="text-overline text-medium-emphasis mb-1">Movimientos vinculados</p>
            <VList density="compact" border rounded class="mb-4">
              <VListItem v-for="mv in conciliarTarget.movimientos" :key="mv.id">
                <VListItemTitle class="text-body-2">{{ (mv.fecha || '').slice(0,10) }} — {{ mv.descripcion }}</VListItemTitle>
                <VListItemSubtitle class="text-success">{{ fmt(mv.monto) }}</VListItemSubtitle>
                <template #append>
                  <VBtn icon size="x-small" variant="text" color="error" @click="desvincularMov(mv.id)"><VIcon size="16">mdi-close</VIcon></VBtn>
                </template>
              </VListItem>
            </VList>
          </template>

          <!-- Ingresos del banco disponibles (multi-selección) -->
          <div class="d-flex align-center justify-space-between mb-1">
            <p class="text-overline text-medium-emphasis mb-0">Ingresos disponibles (selecciona los que correspondan)</p>
            <span v-if="sumaSeleccion > 0" class="text-caption">
              Seleccionado: <strong :class="Math.abs(sumaSeleccion - saldoResumen) < 1 ? 'text-success' : ''">{{ fmt(sumaSeleccion) }}</strong>
            </span>
          </div>
          <VTextField v-model="busquedaMov" placeholder="Buscar por descripción..." prepend-inner-icon="mdi-magnify"
            density="compact" hide-details clearable class="mb-2" @update:modelValue="cargarDisponibles" />

          <div v-if="loadingDisp" class="text-center py-4"><VProgressCircular indeterminate size="24" /></div>
          <template v-else>
            <p v-if="!movDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
              No hay ingresos crédito con saldo por asignar.
            </p>
            <VList v-else density="compact" border rounded style="max-height:300px;overflow-y:auto">
              <VListItem v-for="m in movDisponibles" :key="m.id" @click="toggleMov(m)" style="cursor:pointer"
                :class="{ 'bg-primary-lighten-5': seleccionados.has(m.id) }">
                <template #prepend>
                  <VCheckboxBtn :model-value="seleccionados.has(m.id)" @click.stop="toggleMov(m)" />
                </template>
                <VListItemTitle class="text-body-2">{{ (m.fecha_contable || '').slice(0,10) }} — {{ m.descripcion }}</VListItemTitle>
                <VListItemSubtitle>
                  Saldo <strong class="text-success">{{ fmt(m.saldo_por_asignar) }}</strong>
                  <span v-if="Math.abs(Number(m.saldo_por_asignar) - saldoResumen) < 1" class="text-success"> · calza exacto</span>
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </template>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialogConciliar = false">Cerrar</VBtn>
          <VBtn color="primary" :disabled="!seleccionados.size" :loading="guardando" @click="guardarConciliacion">
            Vincular {{ seleccionados.size || '' }} ingreso(s)
          </VBtn>
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
const movDisponibles    = ref([])
const seleccionados     = ref(new Set())
const loadingDisp       = ref(false)

// Monto ya conciliado del resumen (suma de movimientos vinculados)
const montoYaConciliado = computed(() =>
  (conciliarTarget.value?.movimientos || []).reduce((s, m) => s + Number(m.monto || 0), 0)
)
const saldoResumen = computed(() =>
  Math.max(0, Number(conciliarTarget.value?.monto_total || 0) - montoYaConciliado.value)
)
const sumaSeleccion = computed(() =>
  movDisponibles.value
    .filter(m => seleccionados.value.has(m.id))
    .reduce((s, m) => s + Number(m.saldo_por_asignar || 0), 0)
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
  movDisponibles.value  = []
  seleccionados.value   = new Set()
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
        { params: { buscar: busquedaMov.value || undefined } }
      )
      movDisponibles.value = data.data ?? data
    } finally {
      loadingDisp.value = false
    }
  }, 250)
}

function toggleMov(m) {
  const s = new Set(seleccionados.value)
  s.has(m.id) ? s.delete(m.id) : s.add(m.id)
  seleccionados.value = s
}

// Refresca el resumen activo (movimientos vinculados) desde la lista recargada
function refrescarTarget() {
  const id = conciliarTarget.value?.id
  const actualizado = resumenes.value.find(r => r.id === id)
  if (actualizado) conciliarTarget.value = actualizado
}

async function guardarConciliacion() {
  guardando.value = true
  try {
    // Asigna cada ingreso seleccionado, acotando al saldo restante del resumen
    let restante = saldoResumen.value
    const elegidos = movDisponibles.value.filter(m => seleccionados.value.has(m.id))
    for (const m of elegidos) {
      if (restante <= 0) break
      const monto = Math.min(Number(m.saldo_por_asignar || 0), restante)
      if (monto <= 0) continue
      await axios.post(`/api/boletas/resumenes/${conciliarTarget.value.id}/conciliar`, {
        movimiento_id: m.id,
        monto,
      })
      restante -= monto
    }
    await cargar()
    refrescarTarget()
    seleccionados.value = new Set()
    await cargarDisponibles()
  } finally {
    guardando.value = false
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
