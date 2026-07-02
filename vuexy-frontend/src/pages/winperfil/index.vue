<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <div class="d-flex align-center gap-3">
          <VAvatar color="deep-purple" variant="tonal" size="44" rounded>
            <VIcon size="22">mdi-window-maximize</VIcon>
          </VAvatar>
          <div>
            <h4 class="text-h5 font-weight-bold">Winperfil</h4>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Sincronización de presupuestos y pedidos desde Winperfil ERP
            </p>
          </div>
        </div>
      </VCol>
      <VCol cols="auto">
        <div class="d-flex gap-2">
          <VBtn
            variant="tonal"
            :color="conexion.ok === true ? 'success' : conexion.ok === false ? 'error' : 'default'"
            :loading="testandoConexion"
            prepend-icon="mdi-connection"
            size="small"
            @click="testConexion"
          >
            {{ conexion.ok === true ? 'Conectado' : conexion.ok === false ? 'Sin conexión' : 'Probar conexión' }}
          </VBtn>
          <VBtn
            color="deep-purple"
            prepend-icon="mdi-sync"
            :loading="syncTodoLoading"
            :disabled="conexion.ok === false"
            @click="syncTodo"
          >
            Sincronizar Todo
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Alerta de estado de conexión -->
    <VAlert
      v-if="conexion.mensaje"
      :color="conexion.ok ? 'success' : 'warning'"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="conexion.mensaje = ''"
    >
      <VIcon size="16" class="mr-1">{{ conexion.ok ? 'mdi-check-circle' : 'mdi-alert' }}</VIcon>
      {{ conexion.mensaje }}
      <span v-if="!conexion.ok" class="text-caption d-block mt-1">
        Asegúrate de que Winperfil esté corriendo en <code>localhost:2024</code> y de estar usando la app localmente.
      </span>
    </VAlert>

    <!-- Resultado del último sync -->
    <VAlert
      v-if="syncResult"
      color="info"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="syncResult = null"
    >
      <div class="font-weight-medium mb-1">Resultado de la sincronización</div>
      <div v-if="syncResult.clientes" class="text-caption">
        <strong>Clientes:</strong>
        creados {{ syncResult.clientes.creados ?? 0 }},
        actualizados {{ syncResult.clientes.actualizados ?? 0 }},
        omitidos {{ syncResult.clientes.omitidos ?? 0 }}
        <span v-if="syncResult.clientes.error" class="text-error"> — {{ syncResult.clientes.error }}</span>
      </div>
      <div v-if="syncResult.presupuestos" class="text-caption">
        <strong>Presupuestos:</strong>
        total {{ syncResult.presupuestos.total ?? 0 }},
        creados {{ syncResult.presupuestos.creados ?? 0 }},
        actualizados {{ syncResult.presupuestos.actualizados ?? 0 }}
        <span v-if="syncResult.presupuestos.errores?.length" class="text-warning">
          — {{ syncResult.presupuestos.errores.length }} con error
        </span>
        <span v-if="syncResult.presupuestos.error" class="text-error"> — {{ syncResult.presupuestos.error }}</span>
      </div>
      <div v-if="syncResult.pedidos" class="text-caption">
        <strong>Pedidos:</strong>
        total {{ syncResult.pedidos.total ?? 0 }},
        creados {{ syncResult.pedidos.creados ?? 0 }},
        actualizados {{ syncResult.pedidos.actualizados ?? 0 }}
      </div>
    </VAlert>

    <!-- Configuración de sincronización -->
    <VCard class="mb-4">
      <VCardText>
        <VRow dense align="center">
          <VCol cols="12" sm="3" md="2">
            <VTextField
              v-model="filtros.desde"
              label="Desde"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="3" md="2">
            <VTextField
              v-model="filtros.hasta"
              label="Hasta"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="2" md="1">
            <VSelect
              v-model="filtros.serie"
              :items="['A', 'B', 'C']"
              label="Serie"
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="3" md="2">
            <VTextField
              v-model="filtroCliente"
              label="Cliente"
              placeholder="Nombre o RUT"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              prepend-inner-icon="mdi-magnify"
            />
          </VCol>
          <VCol cols="12" sm="3" md="2">
            <VSelect
              v-model="filtroEstado"
              :items="estadosDisponibles"
              label="Estado"
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>
          <VCol cols="auto">
            <VBtn variant="tonal" size="small" prepend-icon="mdi-eye" @click="cargarPresupuestos">
              Previsualizar
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="deep-purple"
              variant="tonal"
              size="small"
              prepend-icon="mdi-download"
              :loading="syncPresupLoading"
              @click="syncPresupuestos"
            >
              Sincronizar Presupuestos
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="indigo"
              variant="tonal"
              size="small"
              prepend-icon="mdi-factory"
              :loading="syncPedidosLoading"
              @click="syncPedidos"
            >
              Sincronizar Pedidos
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="teal"
              variant="tonal"
              size="small"
              prepend-icon="mdi-account-sync"
              :loading="syncClientesLoading"
              @click="syncClientes"
            >
              Sync Clientes
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabs: Presupuestos Winperfil | Cotizaciones Sincronizadas -->
    <VTabs v-model="tab" class="mb-4">
      <VTab value="preview">
        <VIcon size="16" class="mr-1">mdi-magnify</VIcon>
        Winperfil Live ({{ presupuestosWin.length }})
      </VTab>
      <VTab value="sync">
        <VIcon size="16" class="mr-1">mdi-database-check</VIcon>
        Sincronizados ({{ cotizSyncCount }})
      </VTab>
    </VTabs>

    <VWindow v-model="tab">
      <!-- TAB 1: Presupuestos raw de Winperfil -->
      <VWindowItem value="preview">
        <VCard>
          <VDataTable
            :headers="headersWin"
            :items="presupuestosFiltrados"
            :loading="loadingWin"
            density="compact"
            class="text-no-wrap"
            :items-per-page="50"
          >
            <!-- Número -->
            <template #item.PRESUPUESTO_NUMERO="{ item }">
              <span class="font-weight-bold font-monospace">
                {{ item.PRESUPUESTO_SERIE }}-{{ item.PRESUPUESTO_NUMERO }}
              </span>
            </template>

            <!-- Fecha -->
            <template #item.FECHAFACTURA="{ item }">
              {{ formatFecha(item.FECHAFACTURA) }}
            </template>

            <!-- Cliente -->
            <template #item.NOMBRECLIENTE="{ item }">
              <div>
                <span>{{ item.NOMBRECLIENTE }}</span>
                <span v-if="item.CIFCLIENTE" class="text-caption text-medium-emphasis d-block">
                  {{ item.CIFCLIENTE }}
                </span>
              </div>
            </template>

            <!-- Base / Total -->
            <template #item.BASE="{ item }">
              <span class="font-weight-bold">{{ clp(calcTotal(item)) }}</span>
              <span class="text-caption text-medium-emphasis d-block">
                Base: {{ clp(item.BASE) }}
              </span>
            </template>

            <!-- Estado ACEPTADO -->
            <template #item.ACEPTADO="{ item }">
              <VChip :color="colorAceptado(item.ACEPTADO)" size="x-small" variant="tonal">
                {{ labelAceptado(item.ACEPTADO) }}
              </VChip>
            </template>

            <!-- Detalles -->
            <template #item.detalles="{ item }">
              <VChip
                v-if="item.DETALLES?.length"
                size="x-small"
                color="default"
                variant="tonal"
              >
                {{ item.DETALLES.length }} líneas
              </VChip>
              <span v-else class="text-caption text-disabled">—</span>
            </template>

            <!-- Sync status -->
            <template #item._synced="{ item }">
              <VChip v-if="item._synced" size="x-small" color="success" variant="tonal">
                <VIcon start size="10">mdi-check</VIcon>Sync
              </VChip>
              <VChip v-else size="x-small" color="warning" variant="tonal">
                Pendiente
              </VChip>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <VBtn
                size="x-small"
                :color="item._synced ? 'success' : 'deep-purple'"
                variant="tonal"
                :loading="syncingId === item.PRESUPUESTO_NUMERO"
                @click="syncUno(item)"
              >
                {{ item._synced ? 'Re-sync' : 'Importar' }}
              </VBtn>
            </template>

            <template #no-data>
              <div class="text-center py-8 text-medium-emphasis">
                <VIcon size="40" class="mb-2">mdi-window-maximize</VIcon>
                <p>Haz clic en "Previsualizar" para cargar los presupuestos de Winperfil</p>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VWindowItem>

      <!-- TAB 2: Cotizaciones ya sincronizadas -->
      <VWindowItem value="sync">
        <VCard>
          <VCardText class="pb-0">
            <VRow dense align="center">
              <VCol cols="auto">
                <VBtn variant="tonal" size="small" prepend-icon="mdi-refresh" @click="cargarSync">
                  Actualizar
                </VBtn>
              </VCol>
              <VCol cols="auto">
                <VBtn
                  color="deep-purple"
                  variant="tonal"
                  size="small"
                  prepend-icon="mdi-update"
                  :loading="resyncLoading"
                  :disabled="cotizSyncCount === 0 || resyncLoading"
                  @click="resyncSincronizados"
                >
                  {{ resyncLoading ? `Re-sync... ${resyncProgreso}` : `Re-sync sin imagen (${cotizSyncCount})` }}
                </VBtn>
              </VCol>
              <!-- Barra de progreso del re-sync -->
              <VCol v-if="resyncLoading && resyncTotal > 0" cols="12" class="pt-1 pb-0">
                <VProgressLinear
                  :model-value="resyncPorcentaje"
                  color="deep-purple"
                  height="6"
                  rounded
                  bg-color="surface-variant"
                />
                <p class="text-caption text-medium-emphasis text-center mt-1 mb-0">
                  Procesando {{ resyncProcesados }}/{{ resyncTotal }} presupuestos...
                </p>
              </VCol>
            </VRow>
          </VCardText>

          <VDataTable
            :headers="headersSync"
            :items="cotizacionesFiltradas"
            :loading="loadingSync"
            density="compact"
            class="text-no-wrap"
            :items-per-page="50"
          >
            <!-- Número Winperfil -->
            <template #item.winperfil_numero="{ item }">
              <span class="font-weight-bold font-monospace">
                {{ item.winperfil_serie }}-{{ item.winperfil_numero }}
              </span>
            </template>

            <!-- Fecha -->
            <template #item.fecha="{ item }">
              {{ item.fecha?.slice(0, 10) }}
            </template>

            <!-- Cliente -->
            <template #item.cliente_nombre="{ item }">
              <div>
                <span>{{ item.cliente_nombre }}</span>
                <span v-if="item.cliente_rut" class="text-caption text-medium-emphasis d-block">
                  {{ item.cliente_rut }}
                </span>
              </div>
            </template>

            <!-- Total -->
            <template #item.total="{ item }">
              <span class="font-weight-bold">{{ clp(item.total) }}</span>
            </template>

            <!-- Estado -->
            <template #item.estado="{ item }">
              <VChip
                size="x-small"
                color="default"
                variant="tonal"
              >
                {{ item.estado ?? '—' }}
              </VChip>
            </template>

            <!-- Pedido -->
            <template #item.pedido_id="{ item }">
              <VChip v-if="item.pedido_id" size="x-small" color="indigo" variant="tonal">
                <VIcon start size="10">mdi-factory</VIcon>{{ item.pedido_estado || 'Pedido' }}
              </VChip>
              <span v-else class="text-caption text-disabled">—</span>
            </template>

            <!-- Sincronizado -->
            <template #item.winperfil_synced_at="{ item }">
              <span class="text-caption text-medium-emphasis">
                {{ item.winperfil_synced_at ? formatSyncDate(item.winperfil_synced_at) : '—' }}
              </span>
            </template>

            <!-- Acciones -->
            <template #item.id="{ item }">
              <VBtn
                size="x-small"
                variant="tonal"
                color="primary"
                :to="{ name: 'cotizaciones', query: { id: item.id } }"
                icon
              >
                <VIcon size="14">mdi-open-in-new</VIcon>
              </VBtn>
            </template>

            <template #no-data>
              <div class="text-center py-8 text-medium-emphasis">
                <VIcon size="40" class="mb-2">mdi-database-off</VIcon>
                <p>No hay cotizaciones sincronizadas desde Winperfil</p>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VWindowItem>
    </VWindow>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from '@/axiosInstance'

// ── Estado de conexión ─────────────────────────────────────────────────────────
const conexion = ref({ ok: null, mensaje: '' })
const testandoConexion = ref(false)

async function testConexion() {
  testandoConexion.value = true
  try {
    const { data } = await axios.get('/api/winperfil/test')
    conexion.value = { ok: true, mensaje: data.mensaje }
  } catch (e) {
    const msg = e.response?.data?.mensaje || e.message || 'Sin respuesta'
    conexion.value = { ok: false, mensaje: msg }
  } finally {
    testandoConexion.value = false
  }
}

// ── Filtros ────────────────────────────────────────────────────────────────────
const hoy = new Date()
const primerDiaMes = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-01`
const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().slice(0, 10)

const filtros = ref({
  desde: primerDiaMes,
  hasta: ultimoDiaMes,
  serie: 'A',
})

// Filtros client-side (aplican sobre los datos ya cargados, en ambos tabs)
const filtroCliente = ref('')
const filtroEstado  = ref(null)

// ── Tabs ──────────────────────────────────────────────────────────────────────
const tab = ref('preview')

// ── Tab 1: Presupuestos Winperfil (live) ──────────────────────────────────────
const loadingWin       = ref(false)
const presupuestosWin  = ref([])
const syncingId        = ref(null)

const headersWin = [
  { title: 'Nº',         key: 'PRESUPUESTO_NUMERO', sortable: true  },
  { title: 'Fecha',      key: 'FECHAFACTURA',        sortable: true  },
  { title: 'Cliente',    key: 'NOMBRECLIENTE',       sortable: false },
  { title: 'Total',      key: 'BASE',                align: 'end', sortable: true },
  { title: 'Estado',     key: 'ACEPTADO',            align: 'center', sortable: true },
  { title: 'Detalles',   key: 'detalles',            align: 'center', sortable: false },
  { title: 'Sync',       key: '_synced',             align: 'center', sortable: false },
  { title: '',           key: 'actions',             sortable: false, width: '100px' },
]

async function cargarPresupuestos() {
  loadingWin.value = true
  try {
    const { data } = await axios.get('/api/winperfil/presupuestos', {
      params: {
        serie:   filtros.value.serie,
        desde:   fechaParaWinperfil(filtros.value.desde),
        hasta:   fechaParaWinperfil(filtros.value.hasta),
        detalle: true,
      },
    })
    presupuestosWin.value = Array.isArray(data) ? data : []
    if (!conexion.value.ok) conexion.value = { ok: true, mensaje: 'Conectado' }
  } catch (e) {
    const msg = e.response?.data?.error || e.message
    conexion.value = { ok: false, mensaje: msg }
    presupuestosWin.value = []
  } finally {
    loadingWin.value = false
  }
}

async function syncUno(item) {
  syncingId.value = item.PRESUPUESTO_NUMERO
  try {
    await axios.post('/api/winperfil/sync/presupuestos', {
      serie: filtros.value.serie,
      desde: fechaParaWinperfil(filtros.value.desde),
      hasta: fechaParaWinperfil(filtros.value.hasta),
    })
    // Refrescar estado sync
    await cargarPresupuestos()
    await cargarSync()
  } catch (e) {
    console.error(e)
  } finally {
    syncingId.value = null
  }
}

// ── Tab 2: Cotizaciones sincronizadas ─────────────────────────────────────────
const loadingSync      = ref(false)
const cotizacionesSync = ref([])
const cotizSyncCount   = computed(() => cotizacionesSync.value.length)

// Estados disponibles para el select (según el tab activo)
const estadosDisponibles = computed(() => {
  if (tab.value === 'preview') {
    return [...new Set(presupuestosWin.value.map(p => labelAceptado(p.ACEPTADO)))].filter(Boolean)
  }
  return [...new Set(cotizacionesSync.value.map(c => c.estado).filter(Boolean))]
})

// Tablas filtradas client-side por cliente + estado
const presupuestosFiltrados = computed(() =>
  presupuestosWin.value.filter(p => {
    if (filtroCliente.value) {
      const hay = `${p.NOMBRECLIENTE ?? ''} ${p.CIFCLIENTE ?? ''}`.toLowerCase()
      if (!hay.includes(filtroCliente.value.toLowerCase())) return false
    }
    if (filtroEstado.value && labelAceptado(p.ACEPTADO) !== filtroEstado.value) return false
    return true
  }),
)

const cotizacionesFiltradas = computed(() =>
  cotizacionesSync.value.filter(c => {
    if (filtroCliente.value) {
      const hay = `${c.cliente_nombre ?? ''} ${c.cliente_rut ?? ''} ${c.observaciones ?? ''}`.toLowerCase()
      if (!hay.includes(filtroCliente.value.toLowerCase())) return false
    }
    if (filtroEstado.value && (c.estado ?? '') !== filtroEstado.value) return false
    return true
  }),
)

const headersSync = [
  { title: 'Nº Winperfil',  key: 'winperfil_numero',   sortable: true  },
  { title: 'Fecha',         key: 'fecha',               sortable: true  },
  { title: 'Cliente',       key: 'cliente_nombre',      sortable: false },
  { title: 'Total',         key: 'total',               align: 'end', sortable: true },
  { title: 'Estado',        key: 'estado',              align: 'center', sortable: false },
  { title: 'Pedido',        key: 'pedido_id',           align: 'center', sortable: false },
  { title: 'Últ. sync',     key: 'winperfil_synced_at', sortable: true  },
  { title: '',              key: 'id',                  sortable: false, width: '48px' },
]

async function cargarSync() {
  loadingSync.value = true
  try {
    const { data } = await axios.get('/api/winperfil/cotizaciones', {
      params: {
        serie:  filtros.value.serie,
        desde:  filtros.value.desde,
        hasta:  filtros.value.hasta,
      },
    })
    cotizacionesSync.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.error(e)
  } finally {
    loadingSync.value = false
  }
}

// ── Acciones de sync ──────────────────────────────────────────────────────────
const syncPresupLoading   = ref(false)
const syncPedidosLoading  = ref(false)
const syncClientesLoading = ref(false)
const syncTodoLoading     = ref(false)
const resyncLoading   = ref(false)
const resyncTotal     = ref(0)
const resyncProcesados = ref(0)
const resyncProgreso  = computed(() =>
  resyncTotal.value > 0 ? `${resyncProcesados.value}/${resyncTotal.value}` : ''
)
const resyncPorcentaje = computed(() =>
  resyncTotal.value > 0 ? Math.round((resyncProcesados.value / resyncTotal.value) * 100) : 0
)
const syncResult      = ref(null)

async function syncPresupuestos() {
  syncPresupLoading.value = true
  syncResult.value = null
  try {
    const { data } = await axios.post('/api/winperfil/sync/presupuestos', {
      serie: filtros.value.serie,
      desde: fechaParaWinperfil(filtros.value.desde),
      hasta: fechaParaWinperfil(filtros.value.hasta),
    })
    syncResult.value = { presupuestos: data }
    await cargarPresupuestos()
    await cargarSync()
  } catch (e) {
    syncResult.value = { presupuestos: { error: e.response?.data?.error || e.message } }
  } finally {
    syncPresupLoading.value = false
  }
}

async function syncPedidos() {
  syncPedidosLoading.value = true
  try {
    const { data } = await axios.post('/api/winperfil/sync/pedidos', {
      serie: filtros.value.serie,
      desde: fechaParaWinperfil(filtros.value.desde),
      hasta: fechaParaWinperfil(filtros.value.hasta),
    })
    syncResult.value = { pedidos: data }
    await cargarSync()
  } catch (e) {
    console.error(e)
  } finally {
    syncPedidosLoading.value = false
  }
}

async function syncClientes() {
  syncClientesLoading.value = true
  try {
    const { data } = await axios.post('/api/winperfil/sync/clientes')
    syncResult.value = { clientes: data }
  } catch (e) {
    console.error(e)
  } finally {
    syncClientesLoading.value = false
  }
}

async function resyncSincronizados() {
  resyncLoading.value   = true
  resyncTotal.value     = 0
  resyncProcesados.value = 0
  syncResult.value      = null

  let totalActualizados = 0
  let todosErrores      = []
  let offset            = 0
  const LOTE            = 50

  try {
    // Primera llamada para saber el total pendiente
    let continuar = true
    while (continuar) {
      const { data } = await axios.post('/api/winperfil/sync/resync', {
        serie:       filtros.value.serie,
        solo_sin_svg: true,
        limite:      LOTE,
        offset,
      })

      // Actualizar totales
      if (resyncTotal.value === 0 && data.total > 0) {
        resyncTotal.value = data.total
      }
      resyncProcesados.value += (data.procesados ?? 0)
      totalActualizados      += (data.actualizados ?? 0)
      if (data.errores?.length) todosErrores.push(...data.errores)

      // ¿Hay más?
      if (data.next_offset != null && data.pendientes > 0) {
        offset = data.next_offset
      } else {
        continuar = false
      }
    }

    syncResult.value = {
      presupuestos: {
        ok:          true,
        total:       resyncTotal.value,
        actualizados: totalActualizados,
        errores:     todosErrores,
      },
    }
    await cargarSync()
  } catch (e) {
    syncResult.value = { presupuestos: { error: e.response?.data?.error || e.message } }
  } finally {
    resyncLoading.value = false
  }
}

async function syncTodo() {
  syncTodoLoading.value = true
  syncResult.value = null
  try {
    const { data } = await axios.post('/api/winperfil/sync/todo', {
      serie: filtros.value.serie,
      desde: fechaParaWinperfil(filtros.value.desde),
      hasta: fechaParaWinperfil(filtros.value.hasta),
    })
    syncResult.value = data.resultados
    await cargarPresupuestos()
    await cargarSync()
  } catch (e) {
    const msg = e.response?.data?.error || e.message
    conexion.value = { ok: false, mensaje: 'Error en sync: ' + msg }
  } finally {
    syncTodoLoading.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
// Las fechas del input type="date" ya vienen en yyyy-mm-dd que es lo que espera la API
function fechaParaWinperfil(iso) {
  return iso ?? ''
}

function formatFecha(f) {
  if (!f) return '—'
  // DD/MM/YYYY o DD-MM-YYYY
  if (/^\d{2}[\/\-]\d{2}[\/\-]\d{4}/.test(f)) return f.slice(0, 10).replace(/-/g, '/')
  // YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}/.test(f)) {
    const [y, m, d] = f.slice(0, 10).split('-')
    return `${d}/${m}/${y}`
  }
  return f
}

function formatSyncDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function calcTotal(item) {
  const base = parseFloat(item.BASE || 0)
  const iva  = parseFloat(item.IVA  || 19)
  return base * (1 + iva / 100)
}

function colorAceptado(v) {
  if (!v) return 'default'
  switch (v?.toUpperCase()) {
    case 'T': return 'success'
    case 'C': return 'info'
    case 'F': return 'error'
    default:  return 'warning'
  }
}

function labelAceptado(v) {
  switch (v?.toUpperCase()) {
    case 'T': return 'Aceptado'
    case 'C': return 'Facturado'
    case 'F': return 'Rechazado'
    default:  return 'En evaluación'
  }
}

const clp = n => new Intl.NumberFormat('es-CL', {
  style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
}).format(Number(n) || 0)

// Recargar automáticamente al cambiar filtros de fecha o serie (con debounce
// para no disparar dos llamadas cuando cambian desde+hasta seguidos).
let filtroTimeout = null
watch(
  () => [filtros.value.desde, filtros.value.hasta, filtros.value.serie],
  () => {
    clearTimeout(filtroTimeout)
    filtroTimeout = setTimeout(() => {
      if (tab.value === 'preview') {
        // El tab Live consulta Winperfil directamente; solo si no está caída la conexión
        if (conexion.value.ok !== false) cargarPresupuestos()
      } else {
        cargarSync()
      }
    }, 300)
  },
)

onMounted(async () => {
  await testConexion()
  if (conexion.value.ok) {
    await Promise.all([cargarPresupuestos(), cargarSync()])
  } else {
    await cargarSync() // Solo carga los que ya están en BD aunque Winperfil no esté disponible
  }
})
</script>
