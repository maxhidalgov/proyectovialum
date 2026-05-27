<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Cuentas por Cobrar</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Facturas de venta emitidas a clientes (fuente: Bsale)</p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="primary"
          variant="tonal"
          size="small"
          :loading="sincronizando"
          prepend-icon="mdi-cloud-sync"
          @click="sincronizarDesideBsale"
        >
          Sincronizar desde Bsale
        </VBtn>
      </VCol>
    </VRow>

    <!-- Snackbar sync -->
    <VSnackbar v-model="syncSnack.show" :color="syncSnack.color" location="top right" :timeout="5000">
      {{ syncSnack.text }}
      <template #actions><VBtn variant="text" @click="syncSnack.show = false">Cerrar</VBtn></template>
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
              label="Buscar cliente"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-magnify"
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
              color="warning"
              @update:modelValue="cargar"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Clientes con deuda</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ totales.total_clientes || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total facturado</p>
            <p class="text-h5 font-weight-bold mb-0">{{ formatMonto(totales.total_facturado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total cobrado</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ formatMonto(totales.total_cobrado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Por cobrar</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ formatMonto(totales.total_pendiente || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla clientes -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="clientes"
        :loading="loading"
        item-value="cliente_id"
        density="compact"
        :expanded="expanded"
        show-expand
        @update:expanded="onExpand"
      >
        <!-- Cliente -->
        <template #item.razon_social="{ item }">
          <div>
            <span class="font-weight-medium">{{ item.razon_social }}</span>
            <div class="text-caption text-medium-emphasis">{{ item.identification }}</div>
          </div>
        </template>

        <!-- N° Facturas -->
        <template #item.cantidad_facturas="{ item }">
          <VChip size="x-small" variant="tonal" color="secondary">{{ item.cantidad_facturas }}</VChip>
        </template>

        <!-- Total facturado -->
        <template #item.total_facturado="{ item }">
          {{ formatMonto(item.total_facturado) }}
        </template>

        <!-- Cobrado -->
        <template #item.total_cobrado="{ item }">
          <span class="text-success">{{ formatMonto(item.total_cobrado) }}</span>
        </template>

        <!-- Pendiente -->
        <template #item.total_pendiente="{ item }">
          <span class="font-weight-bold" :class="item.total_pendiente > 0 ? 'text-warning' : 'text-success'">
            {{ formatMonto(item.total_pendiente) }}
          </span>
        </template>

        <!-- Barra progreso cobro -->
        <template #item.progreso="{ item }">
          <div style="min-width: 100px">
            <VProgressLinear
              :model-value="item.total_facturado > 0 ? (item.total_cobrado / item.total_facturado) * 100 : 0"
              color="success"
              bg-color="warning"
              height="6"
              rounded
            />
            <div class="text-caption text-medium-emphasis text-center mt-1">
              {{ item.total_facturado > 0 ? Math.round((item.total_cobrado / item.total_facturado) * 100) : 0 }}%
            </div>
          </div>
        </template>

        <!-- Fila expandida: facturas del cliente -->
        <template #expanded-row="{ item }">
          <tr>
            <td :colspan="headers.length + 1" class="pa-0">
              <div class="pa-4 bg-surface">
                <p class="text-body-2 font-weight-medium mb-3">Facturas de {{ item.razon_social }}</p>

                <div v-if="loadingFacturas[item.cliente_id]" class="text-center py-4">
                  <VProgressCircular indeterminate size="24" />
                </div>

                <VTable v-else-if="facturasCliente[item.cliente_id]?.length" density="compact">
                  <thead>
                    <tr>
                      <th>N° Doc</th>
                      <th>Tipo</th>
                      <th>Cotización</th>
                      <th>Fecha</th>
                      <th class="text-end">Monto</th>
                      <th class="text-end">Cobrado</th>
                      <th class="text-end">Pendiente</th>
                      <th>Estado</th>
                      <th></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="f in facturasCliente[item.cliente_id]" :key="f.id">
                      <td class="font-weight-medium">{{ f.numero_documento_bsale || '—' }}</td>
                      <td>
                        <VChip size="x-small" variant="tonal" color="info">{{ f.tipo }}</VChip>
                      </td>
                      <td class="text-caption text-medium-emphasis">#{{ f.cotizacion_id }}</td>
                      <td class="text-caption">{{ formatFecha(f.fecha_emision) }}</td>
                      <td class="text-end font-weight-medium">{{ formatMonto(f.monto) }}</td>
                      <td class="text-end text-success">{{ formatMonto(f.monto_cobrado) }}</td>
                      <td class="text-end font-weight-bold" :class="f.pendiente > 0 ? 'text-warning' : 'text-success'">
                        {{ formatMonto(f.pendiente) }}
                      </td>
                      <td>
                        <VChip size="x-small" :color="f.pendiente <= 0 ? 'success' : 'warning'" variant="tonal">
                          {{ f.pendiente <= 0 ? 'Cobrada' : 'Pendiente' }}
                        </VChip>
                      </td>
                      <td>
                        <VBtn size="x-small" variant="tonal" color="primary" @click="abrirConciliar(f, item)">
                          <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>Conciliar
                        </VBtn>
                      </td>
                      <td>
                        <VBtn
                          v-if="f.url_pdf_bsale"
                          size="x-small"
                          variant="text"
                          icon
                          :href="f.url_pdf_bsale"
                          target="_blank"
                        >
                          <VIcon size="14">mdi-file-pdf-box</VIcon>
                        </VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>

                <p v-else class="text-body-2 text-medium-emphasis">Sin facturas emitidas en este período.</p>
              </div>
            </td>
          </tr>
        </template>

        <template #bottom>
          <div class="pa-3 text-caption text-medium-emphasis">{{ clientes.length }} clientes</div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Modal Conciliar Factura de Venta ──────────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1200" scrollable>
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>Conciliar Factura de Venta</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 480px">

            <!-- Panel izquierdo: movimientos disponibles -->
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Ingresos Bancarios (Créditos)</p>

                <!-- Asignados ya -->
                <div v-if="asignados.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a esta factura:</p>
                  <VTable density="compact">
                    <tbody>
                      <tr v-for="a in asignados" :key="a.pivot_id">
                        <td class="text-caption">{{ formatFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                          {{ a.descripcion }}
                        </td>
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

                <!-- Lista movimientos crédito disponibles -->
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
                        <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                          Sin ingresos bancarios disponibles
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </div>
            </VCol>

            <!-- Panel derecho: documento -->
            <VCol cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Factura de venta</p>
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis">{{ formatFecha(facturaActiva.fecha_emision) }}</span>
                    <VChip size="x-small" color="info" variant="tonal">{{ facturaActiva.tipo }}</VChip>
                  </div>
                  <p class="font-weight-bold mb-0">{{ clienteActivo?.razon_social }}</p>
                  <p class="text-caption text-medium-emphasis mb-1">{{ clienteActivo?.identification }}</p>
                  <p v-if="facturaActiva.numero_documento_bsale" class="text-caption mb-3">
                    Doc. N° <strong>{{ facturaActiva.numero_documento_bsale }}</strong> · Cot. #{{ facturaActiva.cotizacion_id }}
                  </p>
                  <VDivider class="mb-3" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">Total factura</span>
                    <span class="font-weight-bold">{{ formatMonto(facturaActiva.monto) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Cobrado</span>
                    <span class="text-success">{{ formatMonto(facturaActiva.monto - saldoPorCobrar) }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2 font-weight-bold" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                      Por cobrar
                    </span>
                    <span class="font-weight-bold text-h6" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                      {{ formatMonto(saldoPorCobrar) }}
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="facturaActiva.monto > 0 ? ((facturaActiva.monto - saldoPorCobrar) / facturaActiva.monto) * 100 : 0"
                    color="success"
                    bg-color="warning"
                    height="8"
                    rounded
                    class="mt-3"
                  />
                  <VChip v-if="saldoPorCobrar <= 0" color="success" variant="tonal" class="mt-3 w-100" style="justify-content:center">
                    <VIcon start size="14">mdi-check-circle</VIcon> Factura completamente cobrada
                  </VChip>
                </VCard>
              </div>
            </VCol>

          </VRow>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'

const loading        = ref(false)
const sincronizando  = ref(false)
const syncSnack      = ref({ show: false, text: '', color: 'success' })
const clientes = ref([])
const totales  = ref({})
const expanded = ref([])
const facturasCliente  = ref({})
const loadingFacturas  = ref({})

const dialogConciliar  = ref(false)
const facturaActiva    = ref(null)
const clienteActivo    = ref(null)
const asignados        = ref([])
const disponibles      = ref([])
const saldoPorCobrar   = ref(0)
const buscarMov        = ref('')
const loadingDisponibles = ref(false)
const loadingAsignar   = ref({})
const loadingDesasignar = ref({})

const hoy        = new Date().toISOString().slice(0, 10)
const haceUnAño  = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde:           haceUnAño,
  hasta:           hoy,
  buscar:          '',
  solo_pendientes: true,
})

const headers = [
  { title: 'Cliente', key: 'razon_social', sortable: true },
  { title: 'Facturas', key: 'cantidad_facturas', align: 'center', sortable: true },
  { title: 'Total facturado', key: 'total_facturado', align: 'end', sortable: true },
  { title: 'Cobrado', key: 'total_cobrado', align: 'end', sortable: true },
  { title: 'Por cobrar', key: 'total_pendiente', align: 'end', sortable: true },
  { title: 'Avance', key: 'progreso', align: 'center', sortable: false },
]

function formatMonto(v) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)
}

function formatFecha(f) {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function cargar() {
  loading.value = true
  try {
    const params = {
      desde:           filtros.value.desde,
      hasta:           filtros.value.hasta,
      buscar:          filtros.value.buscar || undefined,
      solo_pendientes: filtros.value.solo_pendientes,
    }
    const { data } = await axios.get('/api/cuentas-por-cobrar', { params })
    clientes.value = data.clientes
    totales.value  = data.totales
    facturasCliente.value = {}
    expanded.value = []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function cargarFacturas(clienteId) {
  if (facturasCliente.value[clienteId]) return
  loadingFacturas.value[clienteId] = true
  try {
    const params = {
      desde: filtros.value.desde,
      hasta: filtros.value.hasta,
    }
    const { data } = await axios.get(`/api/cuentas-por-cobrar/${clienteId}/facturas`, { params })
    facturasCliente.value[clienteId] = data
  } catch (e) {
    console.error(e)
  } finally {
    loadingFacturas.value[clienteId] = false
  }
}

function onExpand(newExpanded) {
  expanded.value = newExpanded
  newExpanded.forEach(id => cargarFacturas(id))
}

async function abrirConciliar(factura, cliente) {
  facturaActiva.value  = factura
  clienteActivo.value  = cliente
  buscarMov.value      = ''
  asignados.value      = []
  disponibles.value    = []
  dialogConciliar.value = true
  await cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!facturaActiva.value) return
  try {
    const { data } = await axios.get(`/api/ventas/${facturaActiva.value.id}/movimientos`)
    asignados.value    = data.asignados
    saldoPorCobrar.value = data.saldo_por_cobrar
  } catch (e) { console.error(e) }
  await cargarDisponibles()
}

async function cargarDisponibles() {
  if (!facturaActiva.value) return
  loadingDisponibles.value = true
  try {
    const { data } = await axios.get(`/api/ventas/${facturaActiva.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMov.value || undefined }
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
    await cargarEstadoConciliar()
    await refrescarFacturasCliente()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignar.value[mov.id] = false
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    await axios.delete(`/api/ventas/${facturaActiva.value.id}/movimientos/${pivotId}`)
    await cargarEstadoConciliar()
    await refrescarFacturasCliente()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value[pivotId] = false
  }
}

async function refrescarFacturasCliente() {
  if (!clienteActivo.value) return
  const id = clienteActivo.value.cliente_id
  delete facturasCliente.value[id]
  await cargarFacturas(id)
  await cargar()
}

async function sincronizarDesideBsale() {
  sincronizando.value = true
  try {
    const anioActual = new Date().getFullYear()
    const { data } = await axios.post('/api/ventas/sincronizar', {
      años: [anioActual - 1, anioActual],
    })
    syncSnack.value = {
      show: true,
      color: 'success',
      text: `Sync completado: ${data.nuevos} nuevos, ${data.omitidos} ya existían${data.errores ? `, ${data.errores} errores` : ''}`,
    }
    await cargar()
  } catch (e) {
    syncSnack.value = { show: true, color: 'error', text: 'Error al sincronizar con Bsale' }
  } finally {
    sincronizando.value = false
  }
}

onMounted(cargar)
</script>
