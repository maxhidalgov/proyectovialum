<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Ingresos sin Documento SII</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Ingresos recibidos que no cuentan con boleta ni factura del SII. Se incluyen en el Estado de Resultados.
        </p>
      </VCol>
      <VCol cols="auto">
        <VBtn color="teal" prepend-icon="mdi-plus" @click="abrirNuevo">Nuevo Ingreso</VBtn>
      </VCol>
    </VRow>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total ingresos</p>
            <p class="text-h5 font-weight-bold text-teal mb-0">{{ clp(totales.total_monto || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">N° de registros</p>
            <p class="text-h5 font-weight-bold mb-0">{{ totales.total_cantidad || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Con movimiento bancario</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ totales.con_movimiento || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Sin movimiento bancario</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ totales.sin_movimiento || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <VCard>
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="4" md="2">
            <VTextField v-model="filtros.desde" label="Desde" type="date" density="compact"
              variant="outlined" hide-details @change="cargar" />
          </VCol>
          <VCol cols="12" sm="4" md="2">
            <VTextField v-model="filtros.hasta" label="Hasta" type="date" density="compact"
              variant="outlined" hide-details @change="cargar" />
          </VCol>
          <VCol cols="12" sm="4" md="2">
            <VSelect v-model="filtros.categoria" :items="['Todas', ...categoriasIngreso]"
              label="Categoría" density="compact" variant="outlined" hide-details @update:modelValue="cargar" />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VTextField v-model="filtros.buscar" label="Buscar descripción..."
              density="compact" variant="outlined" hide-details clearable
              prepend-inner-icon="mdi-magnify" @update:modelValue="debounce" />
          </VCol>
          <VCol cols="auto">
            <VBtn variant="tonal" prepend-icon="mdi-refresh" size="small" @click="cargar">
              Actualizar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Tabla -->
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="text-no-wrap"
      >
        <!-- Fecha -->
        <template #item.fecha="{ item }">
          {{ item.fecha?.slice(0,10) }}
        </template>

        <!-- Monto -->
        <template #item.monto="{ item }">
          <span class="font-weight-bold text-teal">{{ clp(item.monto) }}</span>
        </template>

        <!-- Categoría -->
        <template #item.categoria="{ item }">
          <VChip size="x-small" color="teal" variant="tonal">{{ item.categoria }}</VChip>
        </template>

        <!-- Movimiento bancario vinculado -->
        <template #item.movimientos_count="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VChip v-if="item.movimientos_count > 0" size="x-small" color="success" variant="tonal"
              class="cursor-pointer" @click="abrirConciliar(item)">
              <VIcon start size="11">mdi-bank-check</VIcon>{{ item.movimientos_count }}
            </VChip>
            <span v-else class="text-caption text-medium-emphasis">—</span>
            <VBtn v-if="Number(item.pendiente) > 0.5" size="x-small" variant="tonal" color="teal"
              prepend-icon="mdi-bank-plus" @click="abrirConciliar(item)">
              Conciliar
            </VBtn>
          </div>
        </template>

        <!-- Notas -->
        <template #item.notas="{ item }">
          <span v-if="item.notas" class="text-caption text-medium-emphasis">{{ item.notas }}</span>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VBtn size="x-small" variant="tonal" color="primary" icon @click="abrirEditar(item)">
              <VIcon size="14">mdi-pencil</VIcon>
            </VBtn>
            <VBtn size="x-small" variant="tonal" color="error" icon :loading="deletingId === item.id" @click="eliminar(item)">
              <VIcon size="14">mdi-delete</VIcon>
            </VBtn>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <VIcon size="40" class="mb-2">mdi-receipt-text-outline</VIcon>
            <p>No hay ingresos registrados en este período</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Dialog Crear / Editar ─────────────────────────────────────── -->
    <VDialog v-model="dialog" max-width="540">
      <VCard :title="editando ? 'Editar Ingreso' : 'Nuevo Ingreso sin doc SII'">
        <VCardText>
          <VAlert color="teal" variant="tonal" density="compact" class="mb-4 text-caption">
            <VIcon size="14" class="mr-1">mdi-information-outline</VIcon>
            Este registro se incluirá en los ingresos del <strong>Estado de Resultados</strong>.
          </VAlert>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.fecha" label="Fecha" type="date"
                density="compact" variant="outlined" hide-details="auto" class="mb-3" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.monto" label="Monto ($)"
                type="number" min="0" step="1"
                density="compact" variant="outlined" hide-details="auto" class="mb-3" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.descripcion" label="Descripción"
                density="compact" variant="outlined" hide-details="auto" class="mb-3" />
            </VCol>
            <VCol cols="12">
              <VSelect v-model="form.categoria" :items="categoriasIngreso"
                label="Categoría" density="compact" variant="outlined"
                hide-details="auto" class="mb-3" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.notas" label="Notas (opcional)"
                density="compact" variant="outlined" hide-details rows="2" />
            </VCol>
          </VRow>
          <VAlert v-if="error" color="error" variant="tonal" density="compact" class="mt-3 text-caption">
            {{ error }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialog = false">Cancelar</VBtn>
          <VBtn color="teal" :loading="saving" :disabled="!form.fecha || !form.monto" @click="guardar">
            {{ editando ? 'Guardar cambios' : 'Crear ingreso' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog Conciliar: elegir movimiento bancario ──────────────── -->
    <VDialog v-model="dialogConc.show" max-width="640">
      <VCard v-if="dialogConc.item">
        <VCardTitle class="d-flex align-center gap-2 pa-4">
          <VIcon color="teal">mdi-bank-plus</VIcon>
          Conciliar con movimiento bancario
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VAlert color="teal" variant="tonal" density="compact" class="mb-3 text-caption">
            <strong>{{ dialogConc.item.descripcion || 'Ingreso' }}</strong> · Total {{ clp(dialogConc.item.monto) }} ·
            Conciliado {{ clp(dialogConc.item.asignado) }} · Pendiente <strong>{{ clp(dialogConc.item.pendiente) }}</strong>
          </VAlert>

          <!-- Conciliaciones ya realizadas -->
          <template v-if="dialogConc.asignados.length">
            <p class="text-overline text-medium-emphasis mb-2">Conciliaciones realizadas</p>
            <div style="overflow-x:auto" class="mb-4">
              <VTable density="compact">
                <tbody>
                  <tr v-for="a in dialogConc.asignados" :key="a.pivot_id">
                    <td class="text-caption text-no-wrap">{{ (a.fecha_contable || '').slice(0,10) }}</td>
                    <td class="text-caption">{{ a.descripcion }}</td>
                    <td class="text-right font-weight-bold text-success">{{ clp(a.monto_asignado) }}</td>
                    <td class="text-right" style="width:44px">
                      <VBtn icon size="x-small" variant="text" color="error"
                        :loading="dialogConc.unlinking === a.pivot_id" @click="desvincularMovimiento(a)">
                        <VIcon size="15">mdi-close</VIcon>
                      </VBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </template>

          <!-- Conciliar el saldo pendiente -->
          <template v-if="dialogConc.item.pendiente > 0.5">
            <VDivider v-if="dialogConc.asignados.length" class="mb-3" />
            <p class="text-overline text-medium-emphasis mb-1">Conciliar saldo pendiente</p>
            <p class="text-caption text-medium-emphasis mb-2">
              Se asigna el menor entre el saldo del ingreso y el del movimiento. Ordenados por cercanía a {{ clp(dialogConc.item.pendiente) }}.
            </p>
            <VTextField v-model="dialogConc.buscar" placeholder="Buscar por descripción del movimiento..."
              density="compact" variant="outlined" hide-details clearable
              prepend-inner-icon="mdi-magnify" class="mb-3" @update:modelValue="debounceMov" />

            <div v-if="dialogConc.loading" class="text-center py-4"><VProgressCircular indeterminate size="24" /></div>
            <template v-else>
              <p v-if="!dialogConc.movimientos.length" class="text-caption text-medium-emphasis text-center py-4">
                No hay movimientos crédito con saldo por asignar.
              </p>
              <div v-else style="overflow-x:auto">
                <VTable density="compact">
                  <thead>
                    <tr><th>Fecha</th><th>Descripción</th><th class="text-right">Saldo</th><th class="text-right">Monto</th><th></th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="m in dialogConc.movimientos" :key="m.id"
                      :class="{ 'bg-teal-lighten-5': Math.abs(Number(m.saldo) - Number(dialogConc.item.pendiente)) < 1 }">
                      <td class="text-caption text-no-wrap">{{ (m.fecha_contable || '').slice(0,10) }}</td>
                      <td class="text-caption">{{ m.descripcion }}</td>
                      <td class="text-right font-weight-bold text-success">{{ clp(m.saldo) }}</td>
                      <td class="text-right text-caption">{{ clp(m.monto) }}</td>
                      <td class="text-right" style="width:100px">
                        <VBtn size="x-small" color="teal" variant="flat"
                          :loading="dialogConc.saving === m.id" @click="vincularMovimiento(m)">Vincular</VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </template>
          </template>
          <VAlert v-else color="success" variant="tonal" density="compact" class="text-caption">
            <VIcon size="15" class="mr-1">mdi-check-circle-outline</VIcon>
            Este ingreso está completamente conciliado.
          </VAlert>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-3">
          <VSpacer />
          <VBtn variant="text" @click="dialogConc.show = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Constantes ────────────────────────────────────────────────────────────────
const categoriasIngreso = [
  'Ingreso por ventas', 'Servicios prestados', 'Honorarios recibidos',
  'Arriendo cobrado', 'Comisión cobrada', 'Transferencia recibida', 'Otro',
]

const headers = [
  { title: 'Fecha',       key: 'fecha',             sortable: true  },
  { title: 'Descripción', key: 'descripcion',        sortable: false },
  { title: 'Monto',       key: 'monto',              align: 'end', sortable: true },
  { title: 'Categoría',   key: 'categoria',          sortable: true  },
  { title: 'Movimiento',  key: 'movimientos_count',  align: 'center', sortable: false },
  { title: 'Notas',       key: 'notas',              sortable: false },
  { title: '',            key: 'actions',            sortable: false, width: '80px' },
]

// ── Estado ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const items   = ref([])
const totales = ref({})

const hoy   = new Date()
const primerDiaMes = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-01`
const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().slice(0, 10)

const filtros = ref({
  desde:     primerDiaMes,
  hasta:     ultimoDiaMes,
  categoria: 'Todas',
  buscar:    '',
})

// ── Dialog / Form ─────────────────────────────────────────────────────────────
const dialog   = ref(false)
const editando = ref(null)   // null = nuevo, number = id del ingreso
const saving   = ref(false)
const error    = ref(null)
const deletingId = ref(null)
const form     = ref({})

function formVacio() {
  return {
    fecha:       hoy.toISOString().slice(0, 10),
    monto:       0,
    descripcion: '',
    categoria:   'Ingreso por ventas',
    notas:       '',
  }
}

function abrirNuevo() {
  editando.value = null
  error.value    = null
  form.value     = formVacio()
  dialog.value   = true
}

function abrirEditar(item) {
  editando.value = item.id
  error.value    = null
  form.value     = {
    fecha:       item.fecha?.slice(0, 10),
    monto:       parseFloat(item.monto),
    descripcion: item.descripcion ?? '',
    categoria:   item.categoria ?? 'Ingreso por ventas',
    notas:       item.notas ?? '',
  }
  dialog.value   = true
}

async function guardar() {
  saving.value = true
  error.value  = null
  try {
    if (editando.value) {
      await axios.put(`/api/ingresos-manuales/${editando.value}`, form.value)
    } else {
      await axios.post('/api/ingresos-manuales', form.value)
    }
    dialog.value = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    saving.value = false
  }
}

async function eliminar(item) {
  if (!confirm(`¿Eliminar el ingreso "${item.descripcion || clp(item.monto)}"?`)) return
  deletingId.value = item.id
  try {
    await axios.delete(`/api/ingresos-manuales/${item.id}`)
    await cargar()
  } catch (e) {
    console.error(e)
  } finally {
    deletingId.value = null
  }
}

// ── Carga de datos ────────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const params = {
      desde:  filtros.value.desde,
      hasta:  filtros.value.hasta,
      buscar: filtros.value.buscar || undefined,
      categoria: filtros.value.categoria !== 'Todas' ? filtros.value.categoria : undefined,
    }
    const { data } = await axios.get('/api/ingresos-manuales-detalle', { params })
    items.value   = data.items   ?? []
    totales.value = data.totales ?? {}
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

let buscarTimer = null
function debounce() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargar, 350)
}

// ── Conciliar ingreso ↔ movimiento bancario ─────────────────────────────────────
const dialogConc = ref({ show: false, loading: false, saving: null, unlinking: null, item: null, buscar: '', movimientos: [], asignados: [] })

function abrirConciliar(item) {
  dialogConc.value = {
    show: true, loading: true, saving: null, unlinking: null,
    item: { ...item, asignado: Number(item.asignado || 0), pendiente: Number(item.pendiente ?? item.monto) },
    buscar: '', movimientos: [], asignados: [],
  }
  refrescarConciliaciones().then(cargarMovimientos)
}
// Trae las conciliaciones ya hechas + saldos actualizados del ingreso
async function refrescarConciliaciones() {
  try {
    const { data } = await axios.get(`/api/ingresos-manuales/${dialogConc.value.item.id}/conciliaciones`)
    dialogConc.value.asignados = data.asignados ?? []
    dialogConc.value.item = { ...dialogConc.value.item, ...data.ingreso }
  } catch (e) {
    console.error(e)
  }
}
async function cargarMovimientos() {
  if (dialogConc.value.item.pendiente <= 0.5) { dialogConc.value.movimientos = []; dialogConc.value.loading = false; return }
  dialogConc.value.loading = true
  try {
    const { data } = await axios.get('/api/conciliacion/movimientos-credito-disponibles', {
      params: { buscar: dialogConc.value.buscar || undefined, cerca_de: dialogConc.value.item.pendiente },
    })
    dialogConc.value.movimientos = data.movimientos ?? []
  } catch (e) {
    console.error(e)
  } finally {
    dialogConc.value.loading = false
  }
}
let movTimer = null
function debounceMov() {
  clearTimeout(movTimer)
  movTimer = setTimeout(cargarMovimientos, 350)
}
async function vincularMovimiento(m) {
  dialogConc.value.saving = m.id
  try {
    await axios.post(`/api/conciliacion/movimientos/${m.id}/vincular-nota-venta`, {
      ingreso_id: dialogConc.value.item.id,
    })
    await refrescarConciliaciones()
    await cargarMovimientos()
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'No se pudo vincular')
  } finally {
    dialogConc.value.saving = null
  }
}
async function desvincularMovimiento(a) {
  dialogConc.value.unlinking = a.pivot_id
  try {
    await axios.delete(`/api/conciliacion/movimientos/${a.movimiento_id}/ingresos/${a.pivot_id}`)
    await refrescarConciliaciones()
    await cargarMovimientos()
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'No se pudo desvincular')
  } finally {
    dialogConc.value.unlinking = null
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const clp = n => new Intl.NumberFormat('es-CL', {
  style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
}).format(Number(n) || 0)

onMounted(cargar)
</script>
