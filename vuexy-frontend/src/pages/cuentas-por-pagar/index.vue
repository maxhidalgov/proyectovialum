<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Cuentas por Pagar</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Deuda con proveedores según facturas de compra</p>
      </VCol>
    </VRow>

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
              label="Buscar proveedor"
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
            <p class="text-body-2 text-medium-emphasis mb-1">Proveedores con deuda</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ totales.total_proveedores || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total facturas</p>
            <p class="text-h5 font-weight-bold mb-0">{{ formatMonto(totales.total_monto || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total pagado</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ formatMonto(totales.total_pagado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total pendiente</p>
            <p class="text-h5 font-weight-bold text-error mb-0">{{ formatMonto(totales.total_pendiente || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla proveedores -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="proveedores"
        :loading="loading"
        item-value="rut_emisor"
        density="compact"
        :expanded="expanded"
        show-expand
        @update:expanded="onExpand"
      >
        <!-- Nombre proveedor -->
        <template #item.nombre_emisor="{ item }">
          <div>
            <span class="font-weight-medium">{{ item.nombre_emisor }}</span>
            <div class="text-caption text-medium-emphasis">{{ item.rut_emisor }}</div>
          </div>
        </template>

        <!-- Facturas -->
        <template #item.cantidad_facturas="{ item }">
          <VChip size="x-small" variant="tonal" color="secondary">
            {{ item.cantidad_facturas }}
          </VChip>
        </template>

        <!-- Total facturas -->
        <template #item.total_facturas="{ item }">
          {{ formatMonto(item.total_facturas) }}
        </template>

        <!-- Total pagado -->
        <template #item.total_pagado="{ item }">
          <span class="text-success">{{ formatMonto(item.total_pagado) }}</span>
        </template>

        <!-- Total pendiente -->
        <template #item.total_pendiente="{ item }">
          <span class="font-weight-bold" :class="item.total_pendiente > 0 ? 'text-error' : 'text-success'">
            {{ formatMonto(item.total_pendiente) }}
          </span>
        </template>

        <!-- Barra de progreso pago -->
        <template #item.progreso="{ item }">
          <div style="min-width: 100px">
            <VProgressLinear
              :model-value="item.total_facturas > 0 ? (item.total_pagado / item.total_facturas) * 100 : 0"
              color="success"
              bg-color="error"
              height="6"
              rounded
            />
            <div class="text-caption text-medium-emphasis text-center mt-1">
              {{ item.total_facturas > 0 ? Math.round((item.total_pagado / item.total_facturas) * 100) : 0 }}%
            </div>
          </div>
        </template>

        <!-- Detalle expandido -->
        <template #expanded-row="{ item }">
          <tr>
            <td :colspan="headers.length + 1" class="pa-0">
              <div class="pa-4 bg-surface">
                <p class="text-body-2 font-weight-medium mb-3">
                  Facturas de {{ item.nombre_emisor }}
                </p>
                <div v-if="loadingFacturas[item.rut_emisor]" class="text-center py-4">
                  <VProgressCircular indeterminate size="24" />
                </div>
                <VTable v-else-if="facturasProveedor[item.rut_emisor]?.length" density="compact">
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Tipo</th>
                      <th>Fecha</th>
                      <th class="text-end">Total</th>
                      <th class="text-end">Pagado</th>
                      <th class="text-end">Pendiente</th>
                      <th>Estado</th>
                      <th>Conciliar</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="f in facturasProveedor[item.rut_emisor]" :key="f.id">
                      <td class="font-weight-medium">{{ f.folio }}</td>
                      <td>
                        <VChip size="x-small" variant="tonal" color="info">DTE {{ f.tipo_dte }}</VChip>
                      </td>
                      <td>{{ formatFecha(f.fecha_emision) }}</td>
                      <td class="text-end">{{ formatMonto(f.total) }}</td>
                      <td class="text-end text-success">{{ formatMonto(f.monto_pagado) }}</td>
                      <td class="text-end font-weight-bold" :class="f.pendiente > 0 ? 'text-error' : 'text-success'">
                        {{ formatMonto(f.pendiente) }}
                      </td>
                      <td>
                        <VChip
                          size="x-small"
                          :color="f.pendiente <= 0 ? 'success' : 'warning'"
                          variant="tonal"
                        >
                          {{ f.pendiente <= 0 ? 'Pagada' : 'Pendiente' }}
                        </VChip>
                      </td>
                      <td>
                        <VBtn
                          size="x-small"
                          variant="tonal"
                          color="primary"
                          @click="abrirConciliar(f)"
                        >
                          <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>
                          Conciliar
                        </VBtn>
                      </td>
                      <td>
                        <VBtn
                          v-if="f.pdf_url"
                          size="x-small"
                          variant="text"
                          icon
                          :href="f.pdf_url"
                          target="_blank"
                        >
                          <VIcon size="14">mdi-file-pdf-box</VIcon>
                        </VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
                <p v-else class="text-body-2 text-medium-emphasis">Sin facturas pendientes.</p>
              </div>
            </td>
          </tr>
        </template>

        <template #bottom>
          <div class="pa-3 text-caption text-medium-emphasis">
            {{ proveedores.length }} proveedores
          </div>
        </template>
      </VDataTable>
    </VCard>
    <!-- Modal Conciliar -->
    <VDialog v-model="dialogConciliar" max-width="1300" scrollable>
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>Conciliar Factura</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 500px">

            <!-- Panel izquierdo: movimientos disponibles -->
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Movimientos Bancarios</p>

                <!-- Asignados ya -->
                <div v-if="asignados.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a esta factura:</p>
                  <VTable density="compact">
                    <tbody>
                      <tr v-for="a in asignados" :key="a.pivot_id" class="bg-success-lighten">
                        <td class="text-caption">{{ formatFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ a.descripcion }}</td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ formatMonto(a.monto_asignado) }}</td>
                        <td>
                          <VBtn size="x-small" icon variant="text" color="error" :loading="loadingDesasignar[a.pivot_id]" @click="desasignar(a.pivot_id)">
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
                  placeholder="Buscar por descripción..."
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="mdi-magnify"
                  hide-details
                  class="mb-3"
                  clearable
                  @update:modelValue="cargarDisponibles"
                />

                <!-- Lista movimientos disponibles -->
                <div v-if="loadingDisponibles" class="text-center py-6">
                  <VProgressCircular indeterminate size="28" />
                </div>
                <div style="overflow-x: auto">
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th style="white-space:nowrap">Saldo por asignar</th>
                      <th>Monto</th>
                      <th>Fecha</th>
                      <th>Descripción</th>
                      <th style="white-space:nowrap"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="mov in disponibles" :key="mov.id">
                      <td class="font-weight-bold text-warning">{{ formatMonto(mov.saldo_por_asignar) }}</td>
                      <td>{{ formatMonto(mov.monto) }}</td>
                      <td class="text-caption">{{ formatFecha(mov.fecha_contable) }}</td>
                      <td class="text-caption" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                        {{ mov.descripcion }}
                        <span v-if="mov.glosa" class="text-medium-emphasis d-block">{{ mov.glosa }}</span>
                      </td>
                      <td>
                        <VBtn
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          :loading="loadingAsignar[mov.id]"
                          :disabled="saldoPorPagar <= 0"
                          @click="asignar(mov)"
                        >
                          Seleccionar
                        </VBtn>
                      </td>
                    </tr>
                    <tr v-if="!disponibles.length">
                      <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                        Sin movimientos disponibles
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
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Documento de respaldo</p>
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis">{{ formatFecha(facturaActiva.fecha_emision) }}</span>
                    <VChip size="x-small" color="info" variant="tonal">DTE {{ facturaActiva.tipo_dte }} · {{ facturaActiva.folio }}</VChip>
                  </div>
                  <p class="font-weight-bold mb-0">{{ proveedorActivo?.nombre_emisor }}</p>
                  <p class="text-caption text-medium-emphasis mb-3">{{ proveedorActivo?.rut_emisor }}</p>
                  <VDivider class="mb-3" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">Total factura</span>
                    <span class="font-weight-bold">{{ formatMonto(facturaActiva.total) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Pagado</span>
                    <span class="text-success">{{ formatMonto(facturaActiva.total - saldoPorPagar) }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2 font-weight-bold" :class="saldoPorPagar > 0 ? 'text-error' : 'text-success'">
                      Saldo por pagar
                    </span>
                    <span class="font-weight-bold text-h6" :class="saldoPorPagar > 0 ? 'text-error' : 'text-success'">
                      {{ formatMonto(saldoPorPagar) }}
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="facturaActiva.total > 0 ? ((facturaActiva.total - saldoPorPagar) / facturaActiva.total) * 100 : 0"
                    color="success"
                    bg-color="error"
                    height="8"
                    rounded
                    class="mt-3"
                  />
                  <VChip v-if="saldoPorPagar <= 0" color="success" variant="tonal" class="mt-3 w-100" style="justify-content:center">
                    <VIcon start size="14">mdi-check-circle</VIcon> Factura completamente pagada
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

const loading = ref(false)
const proveedores = ref([])
const totales = ref({})
const expanded = ref([])
const facturasProveedor = ref({})
const loadingFacturas = ref({})

// Modal conciliar
const dialogConciliar = ref(false)
const facturaActiva = ref(null)
const proveedorActivo = ref(null)
const asignados = ref([])
const disponibles = ref([])
const saldoPorPagar = ref(0)
const buscarMov = ref('')
const loadingDisponibles = ref(false)
const loadingAsignar = ref({})
const loadingDesasignar = ref({})

const hoy = new Date().toISOString().slice(0, 10)
const haceUnAño = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde: haceUnAño,
  hasta: hoy,
  buscar: '',
  solo_pendientes: true,
})

const headers = [
  { title: 'Proveedor', key: 'nombre_emisor', sortable: true },
  { title: 'Facturas', key: 'cantidad_facturas', align: 'center', sortable: true },
  { title: 'Total facturas', key: 'total_facturas', align: 'end', sortable: true },
  { title: 'Pagado', key: 'total_pagado', align: 'end', sortable: true },
  { title: 'Pendiente', key: 'total_pendiente', align: 'end', sortable: true },
  { title: 'Avance', key: 'progreso', align: 'center', sortable: false },
]

const formatMonto = (v) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

const formatFecha = (f) => {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function cargar() {
  loading.value = true
  try {
    const params = {
      desde: filtros.value.desde,
      hasta: filtros.value.hasta,
      buscar: filtros.value.buscar || undefined,
      solo_pendientes: filtros.value.solo_pendientes,
    }
    const { data } = await axios.get('/api/cuentas-por-pagar', { params })
    proveedores.value = data.proveedores
    totales.value = data.totales
    // Limpiar detalle al recargar
    facturasProveedor.value = {}
    expanded.value = []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function cargarFacturas(rut) {
  if (facturasProveedor.value[rut]) return
  loadingFacturas.value[rut] = true
  try {
    const params = {
      desde: filtros.value.desde,
      hasta: filtros.value.hasta,
      solo_pendientes: filtros.value.solo_pendientes,
    }
    const { data } = await axios.get(`/api/cuentas-por-pagar/${encodeURIComponent(rut)}/facturas`, { params })
    facturasProveedor.value[rut] = data
  } catch (e) {
    console.error(e)
  } finally {
    loadingFacturas.value[rut] = false
  }
}

function onExpand(newExpanded) {
  expanded.value = newExpanded
  newExpanded.forEach(rut => cargarFacturas(rut))
}

async function abrirConciliar(factura) {
  facturaActiva.value = factura
  // Buscar el proveedor activo en la lista expandida
  const rut = Object.keys(facturasProveedor.value).find(r =>
    facturasProveedor.value[r]?.some(f => f.id === factura.id)
  )
  proveedorActivo.value = proveedores.value.find(p => p.rut_emisor === rut) || null
  buscarMov.value = ''
  asignados.value = []
  disponibles.value = []
  dialogConciliar.value = true
  await cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!facturaActiva.value) return
  try {
    const { data } = await axios.get(`/api/compras/${facturaActiva.value.id}/movimientos`)
    asignados.value = data.asignados
    saldoPorPagar.value = data.saldo_por_pagar
    // Actualizar factura activa para que el panel derecho refleje el pago
    facturaActiva.value = { ...facturaActiva.value }
  } catch (e) { console.error(e) }
  await cargarDisponibles()
}

async function cargarDisponibles() {
  if (!facturaActiva.value) return
  loadingDisponibles.value = true
  try {
    const { data } = await axios.get(`/api/compras/${facturaActiva.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMov.value || undefined }
    })
    disponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
  finally { loadingDisponibles.value = false }
}

async function asignar(mov) {
  loadingAsignar.value[mov.id] = true
  try {
    const monto = Math.min(mov.saldo_por_asignar, saldoPorPagar.value)
    await axios.post(`/api/compras/${facturaActiva.value.id}/movimientos`, {
      movimiento_id: mov.id,
      monto,
    })
    // Refrescar estado y factura en la tabla
    await cargarEstadoConciliar()
    await refrescarFacturasProveedor()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignar.value[mov.id] = false
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    await axios.delete(`/api/compras/${facturaActiva.value.id}/movimientos/${pivotId}`)
    await cargarEstadoConciliar()
    await refrescarFacturasProveedor()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value[pivotId] = false
  }
}

async function refrescarFacturasProveedor() {
  if (!proveedorActivo.value) return
  const rut = proveedorActivo.value.rut_emisor
  delete facturasProveedor.value[rut]
  await cargarFacturas(rut)
  await cargar() // refresca totales
}

onMounted(cargar)
</script>
