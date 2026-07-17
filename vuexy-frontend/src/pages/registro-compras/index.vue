<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Registro de Compras</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Listado de documentos de compra (fuente: Chipax)</p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="success"
          variant="tonal"
          size="small"
          :disabled="!compras.length"
          @click="exportarCSV"
        >
          <VIcon start size="16">mdi-download</VIcon>
          Exportar CSV
        </VBtn>
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
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.buscar"
              label="Buscar folio, proveedor, RUT..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-magnify"
              hide-details
              clearable
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField
              v-model="filtros.monto"
              label="Monto exacto"
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-currency-usd"
              hide-details
              clearable
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VSelect
              v-model="filtros.categoria"
              :items="['', ...categoriasDisponibles]"
              label="Categoría"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              placeholder="Todas"
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="1">
            <VSwitch
              v-model="filtros.solo_pendientes"
              label="Solo pendientes"
              density="compact"
              hide-details
              color="warning"
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="1">
            <VSwitch
              v-model="filtros.ocultar_historicas"
              label="Ocultar hist."
              density="compact"
              hide-details
              color="secondary"
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
        <VCard variant="tonal" color="error">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Monto Total</div>
            <div class="text-h6 font-weight-bold">{{ fmt(totales.total_monto) }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard variant="tonal" color="warning">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Por Pagar</div>
            <div class="text-h6 font-weight-bold">{{ fmt(totales.total_pendiente) }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla -->
    <VCard>
      <VDataTable
        v-model:page="page"
        :headers="headers"
        :items="compras"
        :loading="loading"
        :items-per-page="50"
        :items-per-page-options="[25, 50, 100, { title: 'Todos', value: -1 }]"
        density="compact"
        hover
      >
        <!-- Folio + tipo -->
        <template #item.folio="{ item }">
          <div class="d-flex align-center flex-wrap" style="gap:4px">
            <VChip
              size="x-small"
              variant="tonal"
              :color="tipoColor(item)"
              style="min-width:50px; justify-content:center"
            >
              {{ tipoLabel(item) }}
            </VChip>
            <span class="font-weight-medium">{{ item.folio || '—' }}</span>
            <VChip
              v-if="item.pagado_historico"
              size="x-small"
              variant="outlined"
              color="secondary"
              title="Marcada como pagada históricamente (importación Chipax ≤2024)"
            >Hist.</VChip>
          </div>
        </template>

        <!-- Proveedor -->
        <template #item.nombre_emisor="{ item }">
          <div class="text-body-2">{{ item.nombre_emisor || '—' }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.rut_emisor || '' }}</div>
        </template>

        <!-- Fecha -->
        <template #item.fecha_emision="{ item }">
          <span class="text-caption">{{ fmtFecha(item.fecha_emision) }}</span>
        </template>

        <!-- Categoría -->
        <template #item.categoria="{ item }">
          <div class="d-flex align-center" style="gap:4px">
            <VChip v-if="item.categoria" size="x-small" color="secondary" variant="tonal">
              {{ item.categoria }}
            </VChip>
            <span v-else class="text-caption text-disabled">Sin categoría</span>
            <VBtn
              size="x-small"
              variant="text"
              icon
              :color="item.categoria ? 'default' : 'primary'"
              :title="item.categoria ? 'Cambiar categoría' : 'Asignar categoría'"
              @click="abrirCategoria(item)"
            >
              <VIcon size="14">{{ item.categoria ? 'mdi-pencil' : 'mdi-plus-circle-outline' }}</VIcon>
            </VBtn>
          </div>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span :class="item.es_nc ? 'text-success font-weight-medium' : 'font-weight-medium'">
            {{ item.es_nc ? '−' : '' }}{{ fmt(item.total) }}
          </span>
        </template>

        <!-- Por pagar -->
        <template #item.pendiente="{ item }">
          <template v-if="item.pagado_historico && item.monto_pagado == 0">
            <span class="text-caption text-medium-emphasis">Pagada hist.</span>
          </template>
          <template v-else>
            <span
              class="font-weight-bold"
              :class="(item.pendiente > 0 && !item.es_nc) ? 'text-warning' : 'text-success'"
            >
              {{ item.es_nc ? fmt(Math.abs(item.pendiente)) : fmt(item.pendiente) }}
            </span>
            <VChip
              v-if="item.nc_revision_estado === 'requiere_revision'"
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-1"
            >NC</VChip>
          </template>
        </template>

        <!-- Acciones -->
        <template #item.acciones="{ item }">
          <div class="d-flex align-center" style="gap:4px">
            <VBtn
              v-if="!item.es_nc && !(item.pagado_historico && item.monto_pagado == 0)"
              size="x-small"
              variant="tonal"
              :color="item.pendiente > 0 ? 'primary' : 'success'"
              @click="abrirConciliar(item)"
            >
              <VIcon size="13" class="mr-1">{{ item.pendiente > 0 ? 'mdi-link-variant' : 'mdi-eye-outline' }}</VIcon>
              {{ item.pendiente > 0 ? 'Conciliar' : 'Ver' }}
            </VBtn>
            <VBtn
              v-if="item.pdf_url"
              size="x-small"
              variant="text"
              icon
              :href="item.pdf_url"
              target="_blank"
            >
              <VIcon size="14">mdi-file-pdf-box</VIcon>
            </VBtn>
          </div>
        </template>

      </VDataTable>
    </VCard>

    <!-- ── Modal Conciliar ─────────────────────────────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1100" scrollable>
      <VCard v-if="compraActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>{{ compraActiva.pendiente <= 0 ? 'Detalle de pagos' : 'Conciliar Compra' }}</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <!-- Info compra activa -->
        <div class="px-4 pb-2">
          <VAlert density="compact" variant="tonal" color="warning" class="text-caption">
            <strong>{{ tipoLabel(compraActiva) }} {{ compraActiva.folio }}</strong>
            · {{ compraActiva.nombre_emisor }}
            · {{ fmt(compraActiva.total) }}
            · Por pagar: <strong>{{ fmt(Math.max(0, compraActiva.pendiente)) }}</strong>
          </VAlert>
        </div>

        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 480px">
            <!-- Panel izquierdo: movimientos disponibles -->
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-warning">Egresos Bancarios (Débitos)</p>

                <!-- Asignados -->
                <div v-if="asignados.length || ncsAplicadas.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a esta compra:</p>
                  <VTable density="compact">
                    <tbody>
                      <tr v-for="a in asignados" :key="'b-' + a.pivot_id">
                        <td class="text-caption">{{ fmtFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ a.descripcion }}</td>
                        <td class="text-end text-caption font-weight-bold text-warning">{{ fmt(a.monto_asignado) }}</td>
                        <td>
                          <VBtn size="x-small" icon variant="text" color="error"
                            :loading="loadingDesasignar[a.pivot_id]"
                            @click="desasignar(a.pivot_id)">
                            <VIcon size="14">mdi-close</VIcon>
                          </VBtn>
                        </td>
                      </tr>
                      <tr v-for="nc in ncsAplicadas" :key="'nc-' + nc.pivot_id" style="opacity:0.85">
                        <td class="text-caption">{{ fmtFecha(nc.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                          <VChip size="x-small" color="success" variant="tonal" class="mr-1">NC</VChip>
                          {{ nc.descripcion }}
                        </td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ fmt(nc.monto_asignado) }}</td>
                        <td></td>
                      </tr>
                    </tbody>
                  </VTable>
                  <VDivider class="my-3" />
                </div>

                <!-- Buscador -->
                <VRow dense class="mb-3">
                  <VCol cols="8">
                    <VTextField
                      v-model="buscarMov"
                      placeholder="Buscar descripción..."
                      density="compact"
                      variant="outlined"
                      prepend-inner-icon="mdi-magnify"
                      hide-details
                      clearable
                      @update:modelValue="cargarDisponibles"
                    />
                  </VCol>
                  <VCol cols="4">
                    <VTextField
                      v-model="buscarMontoMov"
                      placeholder="Monto exacto"
                      density="compact"
                      variant="outlined"
                      prepend-inner-icon="mdi-currency-usd"
                      hide-details
                      clearable
                      @update:modelValue="cargarDisponibles"
                    />
                  </VCol>
                </VRow>

                <!-- Lista movimientos disponibles -->
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
                      <tr v-if="!disponibles.data?.length">
                        <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                          Sin egresos bancarios disponibles
                        </td>
                      </tr>
                      <tr
                        v-for="mov in (disponibles.data ?? [])"
                        :key="mov.id"
                        :class="mov.id === movSeleccionado?.id ? 'bg-warning-subtle' : ''"
                        style="cursor:pointer"
                        @click="seleccionarMov(mov)"
                      >
                        <td class="text-caption font-weight-bold text-warning">{{ fmt(mov.saldo_por_asignar) }}</td>
                        <td class="text-caption">{{ fmt(mov.monto) }}</td>
                        <td class="text-caption" style="white-space:nowrap">{{ fmtFecha(mov.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                          {{ mov.descripcion }}
                          <span v-if="mov.glosa" class="text-medium-emphasis"> · {{ mov.glosa }}</span>
                        </td>
                        <td>
                          <VBtn size="x-small" variant="tonal" color="warning" @click.stop="asignarDirecto(mov)">
                            Asignar
                          </VBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </div>
            </VCol>

            <!-- Panel derecho: resumen + confirmar -->
            <VCol cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3">Asignación</p>

                <template v-if="movSeleccionado">
                  <VAlert type="info" variant="tonal" density="compact" class="mb-3 text-caption">
                    <strong>{{ fmtFecha(movSeleccionado.fecha_contable) }}</strong><br>
                    {{ movSeleccionado.descripcion }}<br>
                    Saldo: <strong>{{ fmt(movSeleccionado.saldo_por_asignar) }}</strong>
                  </VAlert>

                  <VTextField
                    v-model.number="montoAsignar"
                    label="Monto a asignar"
                    type="number"
                    density="compact"
                    variant="outlined"
                    prefix="$"
                    class="mb-3"
                  />

                  <VBtn
                    block
                    color="warning"
                    :loading="loadingAsignar"
                    @click="confirmarAsignacion"
                  >
                    Confirmar
                  </VBtn>
                </template>
                <p v-else class="text-caption text-medium-emphasis">
                  Haz clic en un egreso bancario de la izquierda para seleccionarlo.
                </p>

                <!-- Resumen saldo -->
                <VDivider class="my-4" />
                <div class="d-flex justify-space-between text-caption mb-1">
                  <span class="text-medium-emphasis">Total compra</span>
                  <span class="font-weight-bold">{{ fmt(compraActiva.total) }}</span>
                </div>
                <div class="d-flex justify-space-between text-caption mb-1">
                  <span class="text-medium-emphasis">Pagado (banco)</span>
                  <span class="font-weight-bold text-success">{{ fmt(asignados.reduce((s, a) => s + Number(a.monto_asignado), 0)) }}</span>
                </div>
                <div v-if="ncsAplicadas.length" class="d-flex justify-space-between text-caption mb-1">
                  <span class="text-medium-emphasis">Nota(s) de crédito</span>
                  <span class="font-weight-bold text-success">{{ fmt(ncsAplicadas.reduce((s, a) => s + Number(a.monto_asignado), 0)) }}</span>
                </div>
                <VDivider class="my-1" />
                <div class="d-flex justify-space-between text-caption font-weight-bold">
                  <span>Por pagar</span>
                  <span :class="saldoPorPagar > 0 ? 'text-warning' : 'text-success'">{{ fmt(saldoPorPagar) }}</span>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Dialog Categorizar ──────────────────────────────────────────────────── -->
    <VDialog v-model="catDialog.show" max-width="460">
      <VCard v-if="catDialog.compra">
        <VCardTitle class="pa-4 pb-2 d-flex align-center gap-2">
          <VIcon color="primary">mdi-tag-outline</VIcon>
          Categorizar compra
        </VCardTitle>
        <VCardText>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ tipoLabel(catDialog.compra) }} {{ catDialog.compra.folio }} ·
            {{ catDialog.compra.nombre_emisor }}
          </div>
          <VCombobox
            v-model="catDialog.categoria"
            :items="categoriasDisponibles"
            label="Categoría"
            density="compact"
            variant="outlined"
            hide-details
            class="mb-3"
          />
          <VCheckbox
            v-model="catDialog.crearRegla"
            density="compact"
            hide-details
            color="primary"
            label="Aplicar a todas las compras de este proveedor (crear regla)"
          />
          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            Con la regla activada se categorizan también las demás facturas de
            <strong>{{ catDialog.compra.nombre_emisor }}</strong> y las que lleguen a futuro.
          </p>
        </VCardText>
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="catDialog.show = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            :loading="catDialog.loading"
            :disabled="!catDialog.categoria"
            @click="guardarCategoria"
          >
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Estado ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const compras  = ref([])
const totales  = ref({})
const snack    = ref({ show: false, color: 'success', text: '' })
const page     = ref(1)

const hoy       = new Date().toISOString().slice(0, 10)
const inicioAño = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde:              inicioAño,
  hasta:              hoy,
  buscar:             '',
  monto:              '',
  categoria:          '',
  solo_pendientes:    false,
  ocultar_historicas: false,
})

const categoriasDisponibles = ref([])

// ── Headers ───────────────────────────────────────────────────────────────────
const headers = [
  { title: 'Folio',      key: 'folio',         sortable: false },
  { title: 'Proveedor',  key: 'nombre_emisor',  sortable: true  },
  { title: 'Fecha',      key: 'fecha_emision',  sortable: true  },
  { title: 'Categoría',  key: 'categoria',      sortable: true  },
  { title: 'Total',      key: 'total',          align: 'end', sortable: true },
  { title: 'Por Pagar',  key: 'pendiente',      align: 'end', sortable: true },
  { title: '',           key: 'acciones',       sortable: false, width: '140px' },
]

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmt = v =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

const fmtFecha = f => {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function tipoLabel(item) {
  if (!item) return '—'
  const map = { 33: 'FAC', 34: 'F-EX', 61: 'NC', 56: 'ND', 46: 'LH' }
  return map[item.tipo_dte] ?? `DTE-${item.tipo_dte}`
}

function tipoColor(item) {
  if (!item) return 'secondary'
  if (item.tipo_dte === 61) return 'success'
  if (item.tipo_dte === 34) return 'info'
  return 'error'
}

function toast(text, color = 'success') {
  snack.value = { show: true, text, color }
}

// ── Carga principal ───────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/registro-compras', {
      params: {
        desde:           filtros.value.desde     || undefined,
        hasta:           filtros.value.hasta     || undefined,
        buscar:          filtros.value.buscar    || undefined,
        monto:           filtros.value.monto     || undefined,
        categoria:       filtros.value.categoria || undefined,
        solo_pendientes: filtros.value.solo_pendientes,
      },
    })
    let resultado = data.compras
    if (filtros.value.ocultar_historicas) {
      resultado = resultado.filter(c => !c.pagado_historico)
    }
    compras.value  = resultado
    totales.value  = data.totales
    page.value     = 1   // volver a la primera página al cambiar filtros (evita quedar en página vacía)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// ── Export CSV ────────────────────────────────────────────────────────────────
function exportarCSV() {
  const cols = [
    ['Folio',      c => c.folio || ''],
    ['Tipo',       c => tipoLabel(c)],
    ['Proveedor',  c => c.nombre_emisor || ''],
    ['RUT',        c => c.rut_emisor || ''],
    ['Fecha',      c => c.fecha_emision || ''],
    ['Total',      c => c.es_nc ? -Number(c.total) : Number(c.total)],
    ['Por Pagar',  c => Number(c.pendiente) || 0],
    ['Histórica',  c => c.pagado_historico ? 'Sí' : 'No'],
  ]
  const header = cols.map(([h]) => h).join(';')
  const rows = compras.value.map(c =>
    cols.map(([, fn]) => {
      const v = fn(c)
      return typeof v === 'string' && v.includes(';') ? `"${v}"` : v
    }).join(';')
  )
  const bom = '﻿'
  const blob = new Blob([bom + [header, ...rows].join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `registro-compras-${filtros.value.desde || 'inicio'}-${filtros.value.hasta || 'hoy'}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// ── Conciliar ─────────────────────────────────────────────────────────────────
const dialogConciliar   = ref(false)
const compraActiva      = ref(null)
const asignados         = ref([])
const ncsAplicadas      = ref([])
const disponibles       = ref({})
const saldoPorPagar     = ref(0)
const buscarMov         = ref('')
const buscarMontoMov    = ref('')
const movSeleccionado   = ref(null)
const montoAsignar      = ref(0)
const loadingDisponibles = ref(false)
const loadingAsignar    = ref(false)
const loadingDesasignar = ref({})

async function abrirConciliar(compra) {
  compraActiva.value  = compra
  movSeleccionado.value = null
  buscarMov.value     = ''
  buscarMontoMov.value = ''
  dialogConciliar.value = true
  await Promise.all([cargarAsignados(), cargarDisponibles()])
}

async function cargarAsignados() {
  if (!compraActiva.value) return
  const { data } = await axios.get(`/api/compras/${compraActiva.value.id}/movimientos`)
  asignados.value    = data.asignados
  ncsAplicadas.value = data.ncs_aplicadas ?? []
  saldoPorPagar.value = Math.max(0, data.saldo_por_pagar)
  const idx = compras.value.findIndex(c => c.id === compraActiva.value.id)
  if (idx !== -1) compras.value[idx].pendiente = saldoPorPagar.value
}

async function cargarDisponibles() {
  if (!compraActiva.value) return
  loadingDisponibles.value = true
  try {
    const { data } = await axios.get(`/api/compras/${compraActiva.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMov.value || undefined, monto: buscarMontoMov.value || undefined },
    })
    disponibles.value = data
  } finally {
    loadingDisponibles.value = false
  }
}

function seleccionarMov(mov) {
  movSeleccionado.value = mov
  montoAsignar.value = Math.min(
    mov.saldo_por_asignar,
    saldoPorPagar.value
  )
}

async function asignarDirecto(mov) {
  seleccionarMov(mov)
  await confirmarAsignacion()
}

async function confirmarAsignacion() {
  if (!movSeleccionado.value || !montoAsignar.value) return
  loadingAsignar.value = true
  try {
    await axios.post(`/api/compras/${compraActiva.value.id}/movimientos`, {
      movimiento_id: movSeleccionado.value.id,
      monto:         montoAsignar.value,
    })
    movSeleccionado.value = null
    toast('Pago asignado correctamente')
    await Promise.all([cargarAsignados(), cargarDisponibles()])
  } catch (e) {
    toast(e.response?.data?.error ?? 'Error al asignar', 'error')
  } finally {
    loadingAsignar.value = false
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    await axios.delete(`/api/compras/${compraActiva.value.id}/movimientos/${pivotId}`)
    toast('Pago desasignado')
    await Promise.all([cargarAsignados(), cargarDisponibles()])
  } catch {
    toast('Error al desasignar', 'error')
  } finally {
    loadingDesasignar.value[pivotId] = false
  }
}

async function cargarCategorias() {
  try {
    const { data } = await axios.get('/api/compras/categorias')
    categoriasDisponibles.value = data
  } catch { /* silencioso */ }
}

// ── Categorizar una compra (inline) ─────────────────────────────────────────
const catDialog = ref({ show: false, compra: null, categoria: '', crearRegla: true, loading: false })

function abrirCategoria(item) {
  catDialog.value = { show: true, compra: item, categoria: item.categoria || '', crearRegla: true, loading: false }
}

async function guardarCategoria() {
  const d = catDialog.value
  if (!d.categoria) return
  d.loading = true
  try {
    await axios.patch(`/api/compras/${d.compra.id}/categoria`, {
      categoria:   d.categoria,
      crear_regla: d.crearRegla,
    })
    toast(d.crearRegla ? 'Categoría asignada y regla creada' : 'Categoría asignada')
    catDialog.value.show = false
    await Promise.all([cargar(), cargarCategorias()])
  } catch (e) {
    toast(e.response?.data?.message || 'Error al asignar categoría', 'error')
  } finally {
    d.loading = false
  }
}

onMounted(() => {
  cargar()
  cargarCategorias()
})
</script>
