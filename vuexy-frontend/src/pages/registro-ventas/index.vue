<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Registro de Ventas</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Listado de documentos de venta emitidos (fuente: Bsale)</p>
      </VCol>
    </VRow>

    <!-- Snackbar -->
    <VSnackbar v-model="snack.show" :color="snack.color" location="top right" :timeout="4000">
      {{ snack.text }}
      <template #actions><VBtn variant="text" @click="snack.show = false">Cerrar</VBtn></template>
    </VSnackbar>

    <!-- Filtros -->
    <VCard class="mb-4">
      <VCardText>
        <VRow dense align="center">
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.desde"
              label="Desde"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.hasta"
              label="Hasta"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="4">
            <VTextField
              v-model="filtros.buscar"
              label="Buscar folio, razón social, RUT..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-magnify"
              hide-details
              clearable
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VSwitch
              v-model="filtros.solo_pendientes"
              label="Solo pendientes"
              density="compact"
              hide-details
              color="primary"
              @update:modelValue="cargar"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- KPIs -->
    <VRow dense class="mb-4">
      <VCol cols="12" sm="4">
        <VCard variant="tonal" color="primary">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Documentos</div>
            <div class="text-h6 font-weight-bold">{{ totales.total_docs ?? 0 }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard variant="tonal" color="info">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Monto Total</div>
            <div class="text-h6 font-weight-bold">{{ formatMonto(totales.total_monto) }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard variant="tonal" color="warning">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Por Cobrar</div>
            <div class="text-h6 font-weight-bold">{{ formatMonto(totales.total_pendiente) }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="documentos"
        :loading="loading"
        :items-per-page="50"
        density="compact"
        hover
      >
        <!-- Folio + tipo badge -->
        <template #item.folio="{ item }">
          <div class="d-flex align-center" style="gap:6px">
            <VChip
              size="x-small"
              variant="tonal"
              :color="tipoColor(item)"
              style="min-width:58px; justify-content:center"
            >
              {{ tipoPrefix(item) }}
            </VChip>
            <span class="font-weight-medium">{{ item.numero_documento_bsale || '—' }}</span>
          </div>
        </template>

        <!-- Razón social + RUT -->
        <template #item.razon_social="{ item }">
          <div class="text-body-2">{{ item.razon_social || '—' }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.identification || '' }}</div>
        </template>

        <!-- Fecha -->
        <template #item.fecha_emision="{ item }">
          <span class="text-caption">{{ formatFecha(item.fecha_emision) }}</span>
        </template>

        <!-- Monto total -->
        <template #item.monto="{ item }">
          <span :class="item.es_nc ? 'text-success font-weight-medium' : 'font-weight-medium'">
            {{ item.es_nc ? '−' : '' }}{{ formatMonto(item.monto) }}
          </span>
        </template>

        <!-- Por cobrar -->
        <template #item.pendiente="{ item }">
          <div>
            <span
              class="font-weight-bold"
              :class="(item.pendiente > 0 && !item.es_nc) ? 'text-warning' : 'text-success'"
            >
              {{ item.es_nc ? formatMonto(Math.abs(item.pendiente)) : formatMonto(item.pendiente) }}
            </span>
            <VChip
              v-if="item.chipax_monto_por_cobrar !== null"
              size="x-small"
              color="secondary"
              variant="tonal"
              class="ml-1"
            >Chipax</VChip>
          </div>
        </template>

        <!-- Acciones -->
        <template #item.acciones="{ item }">
          <div class="d-flex align-center" style="gap:4px">
            <VBtn
              v-if="!item.es_nc && item.pendiente > 0"
              size="x-small"
              variant="tonal"
              color="primary"
              @click="abrirConciliar(item)"
            >
              <VIcon size="13" class="mr-1">mdi-link-variant</VIcon>Conciliar
            </VBtn>
            <VBtn
              v-else-if="!item.es_nc && item.pendiente <= 0"
              size="x-small"
              variant="text"
              color="success"
              @click="abrirConciliar(item)"
            >
              <VIcon size="13">mdi-eye-outline</VIcon>
            </VBtn>
            <VTooltip
              v-if="!item.es_nc && item.pendiente > 0"
              location="bottom"
              text="Marcar como cobrada (pago sin mov. bancario)"
            >
              <template #activator="{ props: tp }">
                <VBtn
                  v-bind="tp"
                  size="x-small"
                  icon
                  variant="tonal"
                  color="secondary"
                  :loading="loadingMarcar[item.id]"
                  @click="abrirMarcarCobrada(item)"
                >
                  <VIcon size="13">mdi-cash-check</VIcon>
                </VBtn>
              </template>
            </VTooltip>
            <VChip
              v-if="item.monto_cobrado_manual > 0"
              size="x-small"
              color="secondary"
              variant="tonal"
              closable
              :disabled="!!loadingMarcar[item.id]"
              @click:close="desmarcarCobradoManual(item)"
            >
              <VIcon start size="11">mdi-cash</VIcon>Manual
            </VChip>
            <VBtn
              v-if="item.url_pdf_bsale"
              size="x-small"
              variant="text"
              icon
              :href="item.url_pdf_bsale"
              target="_blank"
            >
              <VIcon size="14">mdi-file-pdf-box</VIcon>
            </VBtn>
          </div>
        </template>

        <template #bottom>
          <div class="pa-3 text-caption text-medium-emphasis">{{ documentos.length }} documentos</div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Modal Conciliar ──────────────────────────────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1200" scrollable>
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>{{ facturaActiva.pendiente <= 0 ? 'Detalle de cobros' : 'Conciliar Documento' }}</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <!-- Info documento activo -->
        <div class="px-4 pb-2">
          <VAlert density="compact" variant="tonal" color="info" class="text-caption">
            <strong>{{ tipoPrefix(facturaActiva) }} {{ facturaActiva.numero_documento_bsale }}</strong>
            · {{ facturaActiva.razon_social }}
            · {{ formatMonto(facturaActiva.monto) }}
            · Pendiente: <strong>{{ formatMonto(Math.abs(facturaActiva.pendiente)) }}</strong>
          </VAlert>
        </div>

        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 480px">
            <!-- Panel izquierdo: movimientos disponibles -->
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Ingresos Bancarios (Créditos)</p>

                <!-- Asignados -->
                <div v-if="asignados.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a este documento:</p>
                  <VTable density="compact">
                    <tbody>
                      <tr v-for="a in asignados" :key="a.pivot_id">
                        <td class="text-caption">{{ formatFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ a.descripcion }}</td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ formatMonto(a.monto_asignado) }}</td>
                        <td>
                          <VBtn size="x-small" icon variant="text" color="error"
                            :loading="loadingDesasignar[a.pivot_id]"
                            @click="desasignar(a.pivot_id)">
                            <VIcon size="14">mdi-close</VIcon>
                          </VBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                  <VDivider class="my-3" />
                </div>

                <!-- Buscador -->
                <VTextField
                  v-model="buscarMov"
                  placeholder="Buscar por descripción del ingreso..."
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="mdi-magnify"
                  hide-details
                  class="mb-3"
                  clearable
                  @update:modelValue="cargarDisponibles"
                />

                <!-- Lista -->
                <div v-if="loadingDisponibles" class="text-center py-6">
                  <VProgressCircular indeterminate size="28" />
                </div>
                <div v-else style="overflow-x: auto">
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th style="white-space:nowrap">Saldo disponible</th>
                        <th>Monto total</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="mov in disponibles" :key="mov.id">
                        <td class="font-weight-bold text-success">{{ formatMonto(mov.saldo_por_asignar) }}</td>
                        <td>{{ formatMonto(mov.monto) }}</td>
                        <td class="text-caption">{{ formatFecha(mov.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                          {{ mov.descripcion }}
                          <span v-if="mov.glosa" class="text-medium-emphasis d-block">{{ mov.glosa }}</span>
                        </td>
                        <td>
                          <VBtn
                            size="x-small"
                            color="success"
                            variant="tonal"
                            :loading="loadingAsignar[mov.id]"
                            :disabled="saldoPorCobrar <= 0"
                            @click="asignar(mov)"
                          >Seleccionar</VBtn>
                        </td>
                      </tr>
                      <tr v-if="!disponibles.length">
                        <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">Sin ingresos bancarios disponibles</td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </div>
            </VCol>

            <!-- Panel derecho: resumen -->
            <VCol cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Resumen del documento</p>
                <VTable density="compact">
                  <tbody>
                    <tr>
                      <td class="text-caption text-medium-emphasis">Monto total</td>
                      <td class="text-end font-weight-medium">{{ formatMonto(facturaActiva.monto) }}</td>
                    </tr>
                    <tr>
                      <td class="text-caption text-medium-emphasis">Cobrado</td>
                      <td class="text-end text-success font-weight-bold">{{ formatMonto(facturaActiva.monto_cobrado) }}</td>
                    </tr>
                    <tr>
                      <td class="text-caption text-medium-emphasis font-weight-bold">Saldo por cobrar</td>
                      <td class="text-end font-weight-bold" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                        {{ formatMonto(saldoPorCobrar) }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogConciliar = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Marcar Cobrada ─────────────────────────────────────────────────── -->
    <VDialog v-model="dialogMarcar" max-width="500">
      <VCard v-if="facturaParaMarcar">
        <VCardTitle class="pa-4 pb-2">
          <VIcon start color="secondary">mdi-cash-check</VIcon>Marcar como cobrada
        </VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4 text-caption">
            Registrar un cobro que no tiene movimiento bancario asociado (efectivo, cheque, etc.)
          </VAlert>
          <VRow dense>
            <VCol cols="12">
              <VTextField
                v-model.number="montoMarcar"
                label="Monto cobrado"
                type="number"
                :max="facturaParaMarcar.pendiente"
                min="1"
                density="compact"
                variant="outlined"
                prefix="$"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="notaMarcar"
                label="Nota (opcional)"
                density="compact"
                variant="outlined"
                placeholder="Ej: Cobrado en efectivo 01/06/2026"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogMarcar = false">Cancelar</VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            :disabled="!montoMarcar || montoMarcar <= 0"
            :loading="guardandoMarcar"
            @click="guardarMarcarCobrada"
          >
            <VIcon start size="16">mdi-check</VIcon>Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Estado ────────────────────────────────────────────────────────────────────
const loading     = ref(false)
const documentos  = ref([])
const totales     = ref({})
const snack       = ref({ show: false, text: '', color: 'success' })

// ── Dialog Conciliar ──────────────────────────────────────────────────────────
const dialogConciliar    = ref(false)
const facturaActiva      = ref(null)
const asignados          = ref([])
const disponibles        = ref([])
const saldoPorCobrar     = ref(0)
const buscarMov          = ref('')
const loadingDisponibles = ref(false)
const loadingAsignar     = ref({})
const loadingDesasignar  = ref({})

// ── Dialog Marcar Cobrada ─────────────────────────────────────────────────────
const dialogMarcar     = ref(false)
const facturaParaMarcar = ref(null)
const montoMarcar       = ref(0)
const notaMarcar        = ref('')
const guardandoMarcar   = ref(false)
const loadingMarcar     = ref({})

// ── Filtros ───────────────────────────────────────────────────────────────────
const hoy       = new Date().toISOString().slice(0, 10)
const haceUnAño = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde:           haceUnAño,
  hasta:           hoy,
  buscar:          '',
  solo_pendientes: false,
})

// ── Headers ───────────────────────────────────────────────────────────────────
const headers = [
  { title: 'Folio',        key: 'folio',        sortable: false },
  { title: 'Razón Social', key: 'razon_social',  sortable: true },
  { title: 'Fecha',        key: 'fecha_emision', sortable: true },
  { title: 'Monto Total',  key: 'monto',         align: 'end', sortable: true },
  { title: 'Por Cobrar',   key: 'pendiente',     align: 'end', sortable: true },
  { title: '',             key: 'acciones',      sortable: false, width: '180px' },
]

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatMonto(v) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)
}

function formatFecha(f) {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function tipoPrefix(item) {
  if (!item) return '—'
  if (item.tipo_documento_bsale_id === 2)  return 'NC'
  if (item.tipo_documento_bsale_id === 1)  return 'BOL-EL'
  if (item.tipo_documento_bsale_id === 33) return 'FAC-EL'
  return 'DOC'
}

function tipoColor(item) {
  if (!item) return 'default'
  if (item.tipo_documento_bsale_id === 2)  return 'success'
  if (item.tipo_documento_bsale_id === 1)  return 'deep-purple'
  if (item.tipo_documento_bsale_id === 33) return 'info'
  return 'secondary'
}

// ── Carga principal ───────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/registro-ventas', {
      params: {
        desde:           filtros.value.desde || undefined,
        hasta:           filtros.value.hasta || undefined,
        buscar:          filtros.value.buscar || undefined,
        solo_pendientes: filtros.value.solo_pendientes,
      },
    })
    documentos.value = data.documentos
    totales.value    = data.totales
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// ── Conciliar ─────────────────────────────────────────────────────────────────
async function abrirConciliar(factura) {
  facturaActiva.value   = { ...factura }
  buscarMov.value       = ''
  asignados.value       = []
  disponibles.value     = []
  dialogConciliar.value = true
  await cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!facturaActiva.value) return
  try {
    const { data } = await axios.get(`/api/ventas/${facturaActiva.value.id}/movimientos`)
    asignados.value      = data.asignados
    saldoPorCobrar.value = data.saldo_por_cobrar
  } catch (e) { console.error(e) }
  await cargarDisponibles()
}

async function cargarDisponibles() {
  if (!facturaActiva.value) return
  loadingDisponibles.value = true
  try {
    const { data } = await axios.get(`/api/ventas/${facturaActiva.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMov.value || undefined },
    })
    disponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
  finally { loadingDisponibles.value = false }
}

async function asignar(mov) {
  loadingAsignar.value[mov.id] = true
  try {
    const monto = Math.min(mov.saldo_por_asignar, saldoPorCobrar.value)
    await axios.post(`/api/ventas/${facturaActiva.value.id}/movimientos`, {
      movimiento_id: mov.id,
      monto,
    })
    // Refresh dialog + row
    await cargarEstadoConciliar()
    const idx = documentos.value.findIndex(d => d.id === facturaActiva.value.id)
    if (idx !== -1) {
      const { data } = await axios.get('/api/registro-ventas', {
        params: { buscar: facturaActiva.value.numero_documento_bsale, desde: filtros.value.desde, hasta: filtros.value.hasta },
      })
      const updated = data.documentos.find(d => d.id === facturaActiva.value.id)
      if (updated) {
        documentos.value[idx] = updated
        facturaActiva.value   = { ...updated }
      }
    }
  } catch (e) {
    console.error(e)
    snack.value = { show: true, color: 'error', text: 'Error al asignar movimiento' }
  } finally {
    loadingAsignar.value[mov.id] = false
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    await axios.delete(`/api/ventas/${facturaActiva.value.id}/movimientos/${pivotId}`)
    await cargarEstadoConciliar()
    const idx = documentos.value.findIndex(d => d.id === facturaActiva.value.id)
    if (idx !== -1) {
      const { data } = await axios.get('/api/registro-ventas', {
        params: { buscar: facturaActiva.value.numero_documento_bsale, desde: filtros.value.desde, hasta: filtros.value.hasta },
      })
      const updated = data.documentos.find(d => d.id === facturaActiva.value.id)
      if (updated) {
        documentos.value[idx] = updated
        facturaActiva.value   = { ...updated }
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    delete loadingDesasignar.value[pivotId]
  }
}

// ── Marcar Cobrada ────────────────────────────────────────────────────────────
function abrirMarcarCobrada(factura) {
  facturaParaMarcar.value = factura
  montoMarcar.value       = Math.max(0, factura.pendiente)
  notaMarcar.value        = ''
  dialogMarcar.value      = true
}

async function guardarMarcarCobrada() {
  if (!facturaParaMarcar.value) return
  guardandoMarcar.value = true
  try {
    await axios.put(`/api/cuentas-cobrar/${facturaParaMarcar.value.id}/cobro-manual`, {
      monto: montoMarcar.value,
      nota:  notaMarcar.value || 'Marcado manualmente como cobrado',
    })
    snack.value = { show: true, color: 'success', text: 'Documento marcado como cobrado' }
    dialogMarcar.value = false
    await cargar()
  } catch (e) {
    snack.value = { show: true, color: 'error', text: 'Error al guardar' }
  } finally {
    guardandoMarcar.value = false
  }
}

async function desmarcarCobradoManual(factura) {
  loadingMarcar.value[factura.id] = true
  try {
    await axios.delete(`/api/cuentas-cobrar/${factura.id}/cobro-manual`)
    snack.value = { show: true, color: 'info', text: 'Cobro manual eliminado' }
    await cargar()
  } catch (e) {
    snack.value = { show: true, color: 'error', text: 'Error al eliminar cobro manual' }
  } finally {
    delete loadingMarcar.value[factura.id]
  }
}

onMounted(cargar)
</script>
