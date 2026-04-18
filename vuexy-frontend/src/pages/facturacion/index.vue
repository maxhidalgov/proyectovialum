<template>
  <div>
    <!-- Stats cards -->
    <v-row class="mb-4">
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="warning">mdi-clock-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Por facturar</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsPorFacturar.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsPorFacturar.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="info">mdi-receipt-text</v-icon>
            <span class="text-caption text-medium-emphasis">Facturadas</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsFacturadas.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsFacturadas.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="success">mdi-check-circle-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Pagadas</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsPagadas.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsPagadas.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="primary">mdi-briefcase-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Total en cartera</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ cotizacionesFiltradas.length }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(totalCartera) }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtros -->
    <v-card class="mb-4">
      <v-card-text class="pb-2">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filtros.busqueda"
              label="Buscar por cliente, ID..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.estado"
              :items="estadosFacturacion"
              label="Estado"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.cliente"
              :items="clientesUnicos"
              :item-title="(c) => c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Sin nombre'"
              item-value="id"
              label="Cliente"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center">
            <v-btn size="small" variant="text" @click="limpiarFiltros">Limpiar</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Tabla -->
    <v-card>
      <v-data-table
        v-model:items-per-page="itemsPorPagina"
        :headers="headers"
        :items="cotizacionesFiltradas"
        :loading="loading"
        item-value="id"
        show-expand
      >
        <!-- Número -->
        <template #item.id="{ item }">
          <v-chip color="primary" variant="outlined" size="small">#{{ item.id }}</v-chip>
        </template>

        <!-- Cliente -->
        <template #item.cliente="{ item }">
          <div v-if="item.cliente">
            <div class="font-weight-medium text-body-2">
              {{ item.cliente.razon_social || `${item.cliente.first_name || ''} ${item.cliente.last_name || ''}`.trim() || 'Sin nombre' }}
            </div>
            <div class="text-caption text-medium-emphasis">{{ item.cliente.identification || item.cliente.email }}</div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">Sin cliente</span>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span class="font-weight-bold text-success">{{ clp(item.total) }}</span>
        </template>

        <!-- Estado -->
        <template #item.estado_facturacion="{ item }">
          <v-chip :color="colorEstado(item.estado_facturacion)" size="small" variant="tonal">
            {{ textoEstado(item.estado_facturacion) }}
          </v-chip>
        </template>

        <!-- Fecha -->
        <template #item.fecha="{ item }">
          <span class="text-caption">{{ fmtFecha(item.fecha) }}</span>
        </template>

        <!-- Cobrado -->
        <template #item.cobrado="{ item }">
          <div v-if="item.documentos_facturacion?.length" style="min-width: 90px">
            <div class="d-flex justify-space-between text-caption mb-1">
              <span class="text-success">{{ clp(totalEmitido(item)) }}</span>
              <span class="text-medium-emphasis">{{ pctEmitido(item) }}%</span>
            </div>
            <v-progress-linear
              :model-value="pctEmitido(item)"
              color="success"
              bg-color="grey-lighten-3"
              rounded height="4"
            />
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <!-- Acciones -->
        <template #item.acciones="{ item }">
          <div class="d-flex align-center gap-1" @click.stop>
            <v-btn
              v-if="item.estado_facturacion !== 'pagada'"
              color="success" variant="tonal" size="small"
              @click="abrirModalBsale(item)"
            >
              <v-icon size="16" start>mdi-receipt</v-icon>
              Emitir
            </v-btn>
            <v-menu>
              <template #activator="{ props }">
                <v-btn v-bind="props" icon="mdi-dots-vertical" variant="text" size="x-small" />
              </template>
              <v-list density="compact">
                <v-list-item @click="verDetalleCompleto(item)">
                  <v-list-item-title>Ver detalle</v-list-item-title>
                </v-list-item>
                <v-divider />
                <v-list-item class="text-error" @click="eliminarCotizacion(item)">
                  <v-list-item-title>Eliminar</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </div>
        </template>

        <!-- Expanded row: historial de documentos emitidos -->
        <template #expanded-row="{ columns, item }">
          <tr>
            <td :colspan="columns.length" class="pa-0">
              <div class="pa-4 expanded-row-content">
                <v-row>
                  <!-- Izquierda: items cotización -->
                  <v-col cols="12" md="7">
                    <div v-if="item.cliente_facturacion_id && item.cliente_facturacion_id !== item.cliente_id" class="mb-3 d-flex align-center gap-2">
                      <v-icon size="14" color="warning">mdi-alert-circle-outline</v-icon>
                      <span class="text-caption text-medium-emphasis">
                        Facturado a: <strong>{{ item.cliente_facturacion?.razon_social || `${item.cliente_facturacion?.first_name || ''} ${item.cliente_facturacion?.last_name || ''}`.trim() }}</strong>
                        — {{ item.cliente_facturacion?.identification }}
                      </span>
                    </div>
                    <p class="text-caption font-weight-medium mb-2">Items ({{ item.ventanas?.length || 0 }})</p>
                    <v-table density="compact">
                      <thead>
                        <tr>
                          <th>Descripción</th>
                          <th>Cant.</th>
                          <th class="text-right">P. Unit.</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="v in item.ventanas" :key="v.id">
                          <td>
                            <span class="text-body-2">{{ v.tipo_ventana?.nombre || 'Ventana' }}</span>
                            <span class="text-caption text-medium-emphasis ml-1">{{ v.ancho }}×{{ v.alto }}mm</span>
                          </td>
                          <td>{{ v.cantidad }}</td>
                          <td class="text-right">{{ clp(v.precio_unitario) }}</td>
                          <td class="text-right font-weight-medium">{{ clp(v.precio_unitario * v.cantidad) }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                    <div class="d-flex justify-end mt-2">
                      <div style="min-width:180px">
                        <div class="d-flex justify-space-between font-weight-bold py-1">
                          <span>Total</span><span class="text-success">{{ clp(item.total) }}</span>
                        </div>
                      </div>
                    </div>
                  </v-col>

                  <!-- Derecha: historial de emisiones -->
                  <v-col cols="12" md="5">
                    <p class="text-caption font-weight-medium mb-2">Historial de facturación</p>

                    <div v-if="item.documentos_facturacion?.length">
                      <div
                        v-for="doc in item.documentos_facturacion"
                        :key="doc.id"
                        class="d-flex align-center justify-space-between mb-2 pa-2 rounded"
                        style="border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
                      >
                        <div>
                          <div class="d-flex align-center gap-1">
                            <v-icon size="14" color="success">mdi-check-circle</v-icon>
                            <span class="text-body-2 font-weight-medium text-capitalize">{{ doc.tipo }}</span>
                            <span class="text-caption text-medium-emphasis">({{ doc.porcentaje }}%)</span>
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ clp(doc.monto) }}
                            <span v-if="doc.numero_documento_bsale"> · Doc #{{ doc.numero_documento_bsale }}</span>
                            <span v-if="doc.fecha_emision"> · {{ doc.fecha_emision }}</span>
                          </div>
                        </div>
                        <v-btn
                          v-if="doc.url_pdf_bsale"
                          icon size="x-small" variant="text" color="info"
                          :href="doc.url_pdf_bsale" target="_blank"
                        >
                          <v-icon size="14">mdi-file-pdf-box</v-icon>
                        </v-btn>
                      </div>

                      <!-- Barra progreso -->
                      <div class="mt-2">
                        <div class="d-flex justify-space-between text-caption mb-1">
                          <span>Emitido: {{ clp(totalEmitido(item)) }}</span>
                          <span class="text-medium-emphasis">Pendiente: {{ clp(item.total - totalEmitido(item)) }}</span>
                        </div>
                        <v-progress-linear
                          :model-value="pctEmitido(item)"
                          color="success"
                          bg-color="grey-lighten-3"
                          rounded height="6"
                        />
                      </div>
                    </div>

                    <div v-else class="text-center pa-4">
                      <v-icon size="32" color="grey" class="mb-1">mdi-receipt-text-outline</v-icon>
                      <p class="text-caption text-medium-emphasis">Sin documentos emitidos aún</p>
                      <v-btn size="small" color="success" variant="tonal" class="mt-2" @click.stop="abrirModalBsale(item)">
                        Emitir primer documento
                      </v-btn>
                    </div>
                  </v-col>
                </v-row>
              </div>
            </td>
          </tr>
        </template>

        <!-- Footer -->
        <template #bottom>
          <div class="d-flex justify-space-between align-center px-4 py-2 text-caption text-medium-emphasis">
            <span>{{ cotizacionesFiltradas.length }} de {{ cotizacionesAprobadas.length }} cotizaciones</span>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Modal Bsale -->
    <ModalBsale
      v-model="mostrarModalBsale"
      :cotizacion="cotizacionSeleccionada"
      @documento-generado="onDocumentoGenerado"
    />

    <!-- Modal detalle -->
    <v-dialog v-model="mostrarModalDetalle" max-width="1200px">
      <DetalleCotizacion
        v-if="mostrarModalDetalle"
        :cotizacion="cotizacionSeleccionada"
        @cerrar="mostrarModalDetalle = false"
      />
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snack.show" :color="snack.color" location="top right" :timeout="4000">
      {{ snack.text }}
      <template #actions>
        <v-btn variant="text" @click="snack.show = false">Cerrar</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'
import ModalBsale from '@/components/facturacion/ModalBsale.vue'
import DetalleCotizacion from '@/components/DetalleCotizacion.vue'

// ── Estado ───────────────────────────────────────────────────────────
const loading               = ref(false)
const cotizacionesAprobadas = ref([])
const mostrarModalBsale     = ref(false)
const mostrarModalDetalle   = ref(false)
const cotizacionSeleccionada = ref(null)
const itemsPorPagina        = ref(15)
const snack = ref({ show: false, text: '', color: 'success' })

const filtros = ref({ busqueda: '', estado: null, cliente: null })

// ── Headers ──────────────────────────────────────────────────────────
const headers = [
  { title: '#',        key: 'id',                 sortable: true,  width: '80px' },
  { title: 'Cliente',  key: 'cliente',            sortable: false },
  { title: 'Total',    key: 'total',              sortable: true },
  { title: 'Estado',   key: 'estado_facturacion', sortable: true },
  { title: 'Fecha',    key: 'fecha',              sortable: true },
  { title: 'Cobrado',  key: 'cobrado',            sortable: false, width: '120px' },
  { title: 'Acciones', key: 'acciones',           sortable: false, width: '130px' },
]

const estadosFacturacion = [
  { title: 'Por facturar', value: 'aprobada' },
  { title: 'Facturada',    value: 'facturada' },
  { title: 'Pagada',       value: 'pagada' },
]

// ── Computed ─────────────────────────────────────────────────────────
const clientesUnicos = computed(() => {
  const seen = new Set()
  return cotizacionesAprobadas.value
    .map(c => c.cliente)
    .filter(c => c && !seen.has(c.id) && seen.add(c.id))
})

const cotizacionesFiltradas = computed(() => {
  let list = cotizacionesAprobadas.value
  if (filtros.value.busqueda) {
    const q = filtros.value.busqueda.toLowerCase()
    list = list.filter(c =>
      String(c.id).includes(q) ||
      c.cliente?.razon_social?.toLowerCase().includes(q) ||
      c.cliente?.first_name?.toLowerCase().includes(q) ||
      c.cliente?.last_name?.toLowerCase().includes(q) ||
      c.cliente?.identification?.toLowerCase().includes(q)
    )
  }
  if (filtros.value.estado)   list = list.filter(c => c.estado_facturacion === filtros.value.estado)
  if (filtros.value.cliente)  list = list.filter(c => c.cliente?.id === filtros.value.cliente)
  return list
})

const statsPorFacturar = computed(() => stats('aprobada'))
const statsFacturadas  = computed(() => stats('facturada'))
const statsPagadas     = computed(() => stats('pagada'))
const totalCartera     = computed(() => cotizacionesFiltradas.value.reduce((s, c) => s + Number(c.total), 0))

function stats(estado) {
  const list = cotizacionesAprobadas.value.filter(c => c.estado_facturacion === estado)
  return { count: list.length, monto: list.reduce((s, c) => s + Number(c.total), 0) }
}

function totalEmitido(item) {
  return item.documentos_facturacion?.filter(d => d.estado === 'emitido').reduce((s, d) => s + Number(d.monto), 0) || 0
}
function pctEmitido(item) {
  return item.total > 0 ? Math.round((totalEmitido(item) / item.total) * 100) : 0
}

// ── Carga ────────────────────────────────────────────────────────────
async function cargarCotizaciones() {
  loading.value = true
  try {
    const { data } = await api.get('/api/cotizaciones/aprobadas')
    cotizacionesAprobadas.value = data.cotizaciones || []
  } catch (e) {
    mostrarSnack('Error al cargar cotizaciones', 'error')
  } finally {
    loading.value = false
  }
}


// ── Acciones ─────────────────────────────────────────────────────────
function abrirModalBsale(item) {
  cotizacionSeleccionada.value = item
  mostrarModalBsale.value = true
}

function verDetalleCompleto(item) {
  cotizacionSeleccionada.value = item
  mostrarModalDetalle.value = true
}

async function eliminarCotizacion(item) {
  if (!confirm(`¿Eliminar cotización #${item.id}?`)) return
  try {
    await api.delete(`/api/cotizaciones/${item.id}`)
    cotizacionesAprobadas.value = cotizacionesAprobadas.value.filter(c => c.id !== item.id)
    mostrarSnack('Cotización eliminada')
  } catch {
    mostrarSnack('Error al eliminar', 'error')
  }
}

async function onDocumentoGenerado(data) {
  mostrarSnack('Documento generado correctamente')
  // Actualizar solo la fila afectada con los nuevos documentos
  if (data?.cotizacion) {
    const idx = cotizacionesAprobadas.value.findIndex(c => c.id === data.cotizacion.id)
    if (idx !== -1) {
      cotizacionesAprobadas.value[idx].documentos_facturacion = data.cotizacion.documentos_facturacion
      cotizacionesAprobadas.value[idx].estado_cotizacion_id   = data.cotizacion.estado_cotizacion_id
      // Recalcular estado_facturacion localmente
      const estadoId = data.cotizacion.estado_cotizacion_id
      if (estadoId === 6) cotizacionesAprobadas.value[idx].estado_facturacion = 'facturada'
    }
  } else {
    await cargarCotizaciones()
  }
}

function limpiarFiltros() {
  filtros.value = { busqueda: '', estado: null, cliente: null }
}

// ── Helpers ──────────────────────────────────────────────────────────
const clp = (n) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)

const fmtFecha = (f) => f ? new Date(f).toLocaleDateString('es-CL') : '-'

const calcularSubtotal = (item) =>
  item.ventanas?.reduce((s, v) => s + Number(v.precio_unitario) * Number(v.cantidad), 0) || 0

const colorEstado = (e) => ({ aprobada: 'warning', facturada: 'info', pagada: 'success' }[e] || 'grey')
const textoEstado = (e) => ({ aprobada: 'Por facturar', facturada: 'Facturada', pagada: 'Pagada' }[e] || e)

function mostrarSnack(text, color = 'success') {
  snack.value = { show: true, text, color }
}

onMounted(cargarCotizaciones)
</script>

<style scoped>
.expanded-row-content {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
