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
                  :color="r.conciliado ? 'success' : 'warning'"
                  size="small"
                  label
                >
                  {{ r.conciliado ? '✓ Conciliado' : 'Por conciliar' }}
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
                  v-if="!r.conciliado"
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
    <VDialog v-model="dialogConciliar" max-width="600">
      <VCard>
        <VCardTitle class="pa-4">
          Conciliar boletas {{ conciliarTarget?.periodo }} / {{ labelFormaPago(conciliarTarget?.forma_pago) }}
        </VCardTitle>
        <VDivider />
        <VCardText>
          <p class="mb-4">
            Monto a conciliar: <strong>{{ fmt(conciliarTarget?.monto_total) }}</strong>
          </p>

          <!-- Buscador de movimientos -->
          <VTextField
            v-model="busquedaMov"
            label="Buscar movimiento (descripción o monto)"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            class="mb-3"
            @input="buscarMovimientos"
          />

          <VList v-if="movimientosBuscados.length" density="compact" border rounded style="max-height:250px;overflow-y:auto">
            <VListItem
              v-for="m in movimientosBuscados"
              :key="m.id"
              :class="{ 'bg-primary-subtle': movSeleccionado?.id === m.id }"
              @click="movSeleccionado = m"
              style="cursor:pointer"
            >
              <VListItemTitle class="text-body-2">
                {{ m.fecha_contable }} — {{ m.descripcion }}
              </VListItemTitle>
              <VListItemSubtitle>{{ fmt(m.monto) }}</VListItemSubtitle>
            </VListItem>
          </VList>

          <VTextField
            v-if="movSeleccionado"
            v-model.number="montoConciliar"
            label="Monto a asignar"
            type="number"
            density="compact"
            class="mt-3"
          />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialogConciliar = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            :disabled="!movSeleccionado || !montoConciliar"
            :loading="guardando"
            @click="guardarConciliacion"
          >
            Vincular
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
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
const movimientosBuscados = ref([])
const movSeleccionado   = ref(null)
const montoConciliar    = ref(0)

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
  movimientosBuscados.value = []
  movSeleccionado.value = null
  montoConciliar.value  = r.monto_total
  dialogConciliar.value = true
}

let buscarTimer = null
function buscarMovimientos() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(async () => {
    if (!busquedaMov.value || busquedaMov.value.length < 2) return
    const { data } = await axios.get('/api/movimientos', {
      params: {
        buscar: busquedaMov.value,
        tipo: 'C',
        limit: 20,
        conciliado: 0,
      },
    })
    movimientosBuscados.value = data.data ?? data
  }, 350)
}

async function guardarConciliacion() {
  guardando.value = true
  try {
    await axios.post(`/api/boletas/resumenes/${conciliarTarget.value.id}/conciliar`, {
      movimiento_id: movSeleccionado.value.id,
      monto: montoConciliar.value,
    })
    dialogConciliar.value = false
    await cargar()
  } finally {
    guardando.value = false
  }
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
  return (r.movimientos ?? []).reduce((s, m) => s + Number(m.monto), 0)
}

function labelFormaPago(fp) {
  const map = {
    tarjeta_credito: 'Tarjeta Crédito',
    tarjeta_debito:  'Tarjeta Débito',
    transferencia:   'Transferencia',
    efectivo:        'Efectivo',
    cheque:          'Cheque',
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
  }
  return map[fp] ?? 'default'
}
</script>
