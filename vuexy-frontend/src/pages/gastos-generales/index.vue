<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Gastos Generales</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Arriendos, servicios, comisiones y otros gastos sin factura de proveedor</p>
      </VCol>
      <VCol cols="auto">
        <VBtn color="primary" prepend-icon="mdi-plus" @click="abrirNuevo">Nuevo Gasto</VBtn>
      </VCol>
    </VRow>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total gastos</p>
            <p class="text-h5 font-weight-bold text-error mb-0">{{ formatMonto(totales.total_monto || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Conciliados</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ formatMonto(totales.total_conciliado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Pendientes</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ formatMonto(totales.total_pendiente || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">N° de gastos</p>
            <p class="text-h5 font-weight-bold mb-0">{{ totales.total_gastos || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <!-- Filtros -->
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="6" md="2">
            <VTextField v-model="filtros.desde" label="Desde" type="date" density="compact" variant="outlined" hide-details @change="cargar" />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VTextField v-model="filtros.hasta" label="Hasta" type="date" density="compact" variant="outlined" hide-details @change="cargar" />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="filtros.categoria"
              label="Categoría"
              density="compact"
              variant="outlined"
              hide-details
              :items="[{ title: 'Todas', value: '' }, ...categorias.map(c => ({ title: c, value: c }))]"
              item-title="title"
              item-value="value"
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" md="5">
            <VTextField
              v-model="filtros.buscar"
              label="Buscar descripción o proveedor"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-magnify"
              clearable
              @update:modelValue="debounce"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Tabla -->
      <VDataTable
        :headers="headers"
        :items="gastos"
        :loading="loading"
        item-value="id"
        density="compact"
      >
        <template #item.fecha="{ item }">{{ item.fecha }}</template>

        <template #item.descripcion="{ item }">
          <div>
            <span>{{ item.descripcion }}</span>
            <div v-if="item.proveedor" class="text-caption text-medium-emphasis">{{ item.proveedor }}</div>
          </div>
        </template>

        <template #item.categoria="{ item }">
          <VChip v-if="item.categoria" size="x-small" color="secondary" variant="tonal">{{ item.categoria }}</VChip>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <template #item.monto="{ item }">
          <span class="text-error font-weight-medium">{{ formatMonto(item.monto) }}</span>
        </template>

        <template #item.saldo_por_conciliar="{ item }">
          <div style="cursor: pointer" @click="abrirConciliar(item)">
            <span v-if="item.saldo_por_conciliar > 0" class="text-caption text-warning font-weight-bold">
              {{ formatMonto(item.saldo_por_conciliar) }} pendiente
            </span>
            <VChip v-else size="x-small" color="success" variant="tonal">
              <VIcon start size="11">mdi-check</VIcon> Conciliado
            </VChip>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VBtn size="x-small" variant="tonal" color="primary" @click="abrirConciliar(item)">
              <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>Conciliar
            </VBtn>
            <VBtn size="x-small" variant="tonal" color="secondary" icon @click="abrirEditar(item)">
              <VIcon size="14">mdi-pencil</VIcon>
            </VBtn>
            <VBtn size="x-small" variant="tonal" color="error" icon @click="eliminar(item.id)">
              <VIcon size="14">mdi-delete</VIcon>
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Dialog Crear / Editar ─────────────────────────────────────────── -->
    <VDialog v-model="dialogForm" max-width="560">
      <VCard :title="editando?.id ? 'Editar Gasto' : 'Nuevo Gasto'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.fecha" label="Fecha" type="date" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.monto" label="Monto ($)" type="number" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.descripcion" label="Descripción" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="7">
              <VSelect
                v-model="form.categoria"
                label="Categoría"
                density="compact"
                variant="outlined"
                hide-details
                :items="categorias"
              />
            </VCol>
            <VCol cols="12" sm="5">
              <VTextField v-model="form.numero_documento" label="N° Documento (opcional)" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.proveedor" label="Proveedor / Quién cobró (opcional)" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.notas" label="Notas (opcional)" density="compact" variant="outlined" hide-details />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogForm = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="saving" @click="guardar">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Conciliar Gasto ↔ Movimientos ───────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1200" scrollable>
      <VCard>
        <VCardTitle class="pa-4 d-flex align-center justify-space-between" style="background: rgb(var(--v-theme-primary)); color: white">
          <span>Conciliar Gasto</span>
          <VBtn icon variant="text" color="white" @click="dialogConciliar = false; cargar()">
            <VIcon>mdi-close</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-4">
          <VRow>
            <!-- Panel izq: gasto + movimientos asignados -->
            <VCol cols="12" md="4">
              <p class="text-overline text-medium-emphasis mb-2">Gasto</p>
              <VCard variant="outlined" class="mb-4">
                <VCardText class="pa-3">
                  <div class="text-body-2 font-weight-bold mb-1">{{ concGasto?.descripcion }}</div>
                  <div class="text-caption text-medium-emphasis">{{ concGasto?.fecha }}</div>
                  <div v-if="concGasto?.proveedor" class="text-caption text-medium-emphasis">{{ concGasto.proveedor }}</div>
                  <VChip v-if="concGasto?.categoria" size="x-small" color="secondary" variant="tonal" class="mt-1">{{ concGasto.categoria }}</VChip>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-caption">Monto</span>
                    <span class="font-weight-bold text-error">{{ formatMonto(concGasto?.monto) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-caption">Pendiente</span>
                    <span :class="concSaldoPendiente > 0 ? 'text-warning font-weight-bold' : 'text-success font-weight-bold'">
                      {{ formatMonto(concSaldoPendiente) }}
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="concGasto?.monto ? ((concGasto.monto - concSaldoPendiente) / concGasto.monto) * 100 : 0"
                    color="success" rounded height="6" class="mt-2"
                  />
                </VCardText>
              </VCard>

              <p class="text-overline text-medium-emphasis mb-2">Movimientos asignados</p>
              <p v-if="!concAsignados.length" class="text-caption text-medium-emphasis">Ninguno asignado aún</p>
              <VCard v-for="a in concAsignados" :key="a.pivot_id" variant="tonal" color="success" class="mb-2">
                <VCardText class="pa-2 d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption font-weight-bold">{{ a.descripcion }}</div>
                    <div class="text-caption text-medium-emphasis">{{ a.fecha_contable }}</div>
                    <div class="text-caption">Asignado: <strong>{{ formatMonto(a.monto_asignado) }}</strong></div>
                  </div>
                  <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === a.pivot_id" @click="desasignar(a.pivot_id)">
                    <VIcon size="16">mdi-close-circle</VIcon>
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Panel der: movimientos disponibles -->
            <VCol cols="12" md="8">
              <div class="d-flex align-center justify-space-between mb-2">
                <p class="text-overline text-medium-emphasis mb-0">Egresos bancarios disponibles</p>
                <span class="text-caption text-medium-emphasis">Ordenados por monto más cercano</span>
              </div>
              <VTextField
                v-model="buscarMov"
                placeholder="Buscar descripción o glosa..."
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="mdi-magnify"
                clearable
                class="mb-3"
                @update:modelValue="debounceMov"
              />

              <div v-if="loadingConc" class="text-center py-6">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else-if="!concDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
                No hay egresos con saldo disponible
              </div>
              <div v-else style="overflow-x: auto">
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th>Saldo por asignar</th>
                      <th>Monto</th>
                      <th>Fecha</th>
                      <th>Descripción</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="m in concDisponibles" :key="m.id">
                      <td class="font-weight-bold text-primary">{{ formatMonto(m.saldo_por_asignar) }}</td>
                      <td>{{ formatMonto(m.monto) }}</td>
                      <td class="text-caption">{{ m.fecha_contable }}</td>
                      <td class="text-caption">
                        {{ m.descripcion }}
                        <div v-if="m.glosa" class="text-medium-emphasis">
                          <VIcon size="10">mdi-comment-text-outline</VIcon> {{ m.glosa }}
                        </div>
                      </td>
                      <td>
                        <VBtn
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          :loading="loadingAsignar === m.id"
                          :disabled="concSaldoPendiente <= 0"
                          @click="asignar(m)"
                        >Seleccionar</VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="primary" @click="dialogConciliar = false; cargar()">Listo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Categorías ───────────────────────────────────────────────────────────────
const categorias = [
  'Arriendo',
  'Servicios básicos',
  'Comisión bancaria',
  'Publicidad',
  'Mantención',
  'Transporte y combustible',
  'Seguros',
  'Honorarios',
  'Gastos de oficina',
  'Otro',
]

// ── Estado ───────────────────────────────────────────────────────────────────
const gastos  = ref([])
const totales = ref({})
const loading = ref(false)
const saving  = ref(false)

const hoy          = new Date().toISOString().slice(0, 10)
const primerDiAnio = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)

const filtros = ref({ desde: primerDiAnio, hasta: hoy, categoria: '', buscar: '' })

const dialogForm = ref(false)
const editando   = ref(null)
const formVacio  = () => ({
  fecha: hoy, descripcion: '', categoria: '', monto: null,
  proveedor: '', numero_documento: '', notas: '',
})
const form = ref(formVacio())

// ── Headers ──────────────────────────────────────────────────────────────────
const headers = [
  { title: 'Fecha',       key: 'fecha',             sortable: true  },
  { title: 'Descripción', key: 'descripcion',        sortable: false },
  { title: 'Categoría',   key: 'categoria',          sortable: false },
  { title: 'Monto',       key: 'monto',   align: 'end', sortable: true },
  { title: 'Estado',      key: 'saldo_por_conciliar', align: 'end', sortable: false },
  { title: '',            key: 'actions', sortable: false, width: '160px' },
]

// ── API ──────────────────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const params = {}
    if (filtros.value.desde)    params.desde    = filtros.value.desde
    if (filtros.value.hasta)    params.hasta    = filtros.value.hasta
    if (filtros.value.categoria) params.categoria = filtros.value.categoria
    if (filtros.value.buscar)   params.buscar   = filtros.value.buscar
    const { data } = await axios.get('/api/gastos', { params })
    gastos.value  = data.gastos?.data || []
    totales.value = data.totales || {}
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function abrirNuevo() {
  editando.value  = null
  form.value      = formVacio()
  dialogForm.value = true
}

function abrirEditar(gasto) {
  editando.value  = gasto
  form.value      = { ...gasto }
  dialogForm.value = true
}

async function guardar() {
  saving.value = true
  try {
    if (editando.value?.id) {
      await axios.put(`/api/gastos/${editando.value.id}`, form.value)
    } else {
      await axios.post('/api/gastos', form.value)
    }
    dialogForm.value = false
    await cargar()
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este gasto?')) return
  await axios.delete(`/api/gastos/${id}`)
  await cargar()
}

let buscarTimer = null
function debounce() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargar, 350)
}

// ── Conciliación ─────────────────────────────────────────────────────────────
const dialogConciliar   = ref(false)
const concGasto         = ref(null)
const concAsignados     = ref([])
const concDisponibles   = ref([])
const concSaldoPendiente = ref(0)
const loadingConc       = ref(false)
const loadingAsignar    = ref(null)
const loadingDesasignar = ref(null)
const buscarMov         = ref('')

function abrirConciliar(gasto) {
  concGasto.value     = gasto
  buscarMov.value     = ''
  concAsignados.value = []
  concDisponibles.value = []
  dialogConciliar.value = true
  cargarEstado()
}

async function cargarEstado() {
  if (!concGasto.value) return
  loadingConc.value = true
  try {
    const { data } = await axios.get(`/api/gastos/${concGasto.value.id}/movimientos`)
    concAsignados.value    = data.asignados
    concSaldoPendiente.value = data.saldo_por_conciliar
    await cargarDisponibles()
  } catch (e) {
    console.error(e)
  } finally {
    loadingConc.value = false
  }
}

async function cargarDisponibles() {
  if (!concGasto.value) return
  try {
    const params = buscarMov.value ? { buscar: buscarMov.value } : {}
    const { data } = await axios.get(`/api/gastos/${concGasto.value.id}/movimientos-disponibles`, { params })
    concDisponibles.value = data.data ?? data
  } catch (e) {
    console.error(e)
  }
}

async function asignar(mov) {
  loadingAsignar.value = mov.id
  try {
    await axios.post(`/api/gastos/${concGasto.value.id}/movimientos`, {
      movimiento_id: mov.id,
      monto: Math.min(concSaldoPendiente.value, mov.saldo_por_asignar),
    })
    await cargarEstado()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignar.value = null
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value = pivotId
  try {
    await axios.delete(`/api/gastos/${concGasto.value.id}/movimientos/${pivotId}`)
    await cargarEstado()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

let movTimer = null
function debounceMov() {
  clearTimeout(movTimer)
  movTimer = setTimeout(cargarDisponibles, 350)
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatMonto(v) {
  return '$' + parseFloat(v || 0).toLocaleString('es-CL', { minimumFractionDigits: 0 })
}

onMounted(cargar)
</script>
