<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Empleados</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Gestión de personal y remuneraciones</p>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2">
        <VBtn v-if="tabActiva === 'nomina'" color="primary" prepend-icon="mdi-plus" @click="abrirNuevo">
          Nuevo empleado
        </VBtn>
        <template v-if="tabActiva === 'remuneraciones'">
          <VBtn variant="tonal" color="warning" prepend-icon="mdi-calendar-check" @click="dialogGenerar = true">
            Generar sueldos
          </VBtn>
          <VBtn color="primary" prepend-icon="mdi-plus" @click="abrirNuevoPagoGlobal">
            Agregar pago
          </VBtn>
        </template>
      </VCol>
    </VRow>

    <!-- Tabs -->
    <VTabs v-model="tabActiva" class="mb-4">
      <VTab value="nomina">
        <VIcon start size="16">mdi-account-group</VIcon>
        Nómina
      </VTab>
      <VTab value="remuneraciones">
        <VIcon start size="16">mdi-cash-multiple</VIcon>
        Remuneraciones
      </VTab>
    </VTabs>

    <!-- TAB: NÓMINA -->
    <div v-if="tabActiva === 'nomina'">
      <!-- Cards resumen -->
      <VRow class="mb-4">
        <VCol cols="12" sm="4">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Empleados activos</p>
              <p class="text-h5 font-weight-bold text-primary mb-0">{{ empleadosActivos }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" sm="4">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Masa salarial mensual</p>
              <p class="text-h5 font-weight-bold text-success mb-0">{{ formatMonto(masaSalarial) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" sm="4">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Total empleados</p>
              <p class="text-h5 font-weight-bold mb-0">{{ empleados.length }}</p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla empleados -->
      <VCard>
        <VCardText class="pb-0">
          <VTextField
            v-model="buscar"
            placeholder="Buscar empleado..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-magnify"
            hide-details
            style="max-width: 300px"
          />
        </VCardText>

        <VDataTable
          :headers="headers"
          :items="empleadosFiltrados"
          :loading="loading"
          item-value="id"
          density="comfortable"
        >
          <template #item.activo="{ item }">
            <VChip size="x-small" :color="item.activo ? 'success' : 'default'" variant="tonal">
              {{ item.activo ? 'Activo' : 'Inactivo' }}
            </VChip>
          </template>

          <template #item.sueldo_base="{ item }">
            <span class="font-weight-medium">{{ formatMonto(item.sueldo_base) }}</span>
          </template>

          <template #item.fecha_ingreso="{ item }">
            {{ item.fecha_ingreso?.slice(0, 10) }}
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn size="x-small" variant="tonal" color="info" @click="verPagos(item)">
                <VIcon size="14">mdi-history</VIcon>
              </VBtn>
              <VBtn size="x-small" variant="tonal" color="primary" icon @click="editar(item)">
                <VIcon size="14">mdi-pencil</VIcon>
              </VBtn>
              <VBtn size="x-small" variant="tonal" color="error" icon @click="eliminar(item.id)">
                <VIcon size="14">mdi-delete</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- TAB: REMUNERACIONES -->
    <div v-if="tabActiva === 'remuneraciones'">
      <!-- Selector de período -->
      <VCard class="mb-4">
        <VCardText>
          <VRow align="center" dense>
            <VCol cols="12" sm="3">
              <VTextField
                v-model="periodoRem"
                label="Período"
                type="month"
                density="compact"
                variant="outlined"
                hide-details
                @update:modelValue="cargarRemuneraciones"
              />
            </VCol>
            <VCol cols="auto">
              <VBtn variant="tonal" size="small" @click="cargarRemuneraciones">
                <VIcon start size="16">mdi-refresh</VIcon>
                Actualizar
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Cards resumen remuneraciones -->
      <VRow class="mb-4">
        <VCol cols="6" sm="3">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Total del mes</p>
              <p class="text-h6 font-weight-bold mb-0">{{ formatMonto(totalesRem.total) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Conciliado</p>
              <p class="text-h6 font-weight-bold text-success mb-0">{{ formatMonto(totalesRem.pagado) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">Sin conciliar</p>
              <p class="text-h6 font-weight-bold text-warning mb-0">{{ formatMonto(totalesRem.pendiente) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-1">N° documentos</p>
              <p class="text-h6 font-weight-bold mb-0">{{ totalesRem.cantidad }}</p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla remuneraciones -->
      <VCard>
        <VDataTable
          :headers="headersRem"
          :items="pagosRem"
          :loading="loadingRem"
          item-value="id"
          density="comfortable"
        >
          <template #item.tipo="{ item }">
            <VChip size="x-small" variant="tonal" :color="tipoColor(item.tipo)">{{ item.tipo }}</VChip>
          </template>

          <template #item.monto="{ item }">
            <span class="font-weight-medium">{{ formatMonto(item.monto) }}</span>
          </template>

          <template #item.conciliacion="{ item }">
            <div v-if="item.movimiento_id" class="d-flex align-center gap-1">
              <VIcon size="14" color="success">mdi-check-circle</VIcon>
              <span class="text-caption text-success">
                {{ item.mov_fecha?.slice(0, 10) }}
              </span>
            </div>
            <VChip v-else size="x-small" color="warning" variant="tonal">
              Sin conciliar
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn size="x-small" variant="tonal" color="primary" icon @click="editarPagoRem(item)">
                <VIcon size="14">mdi-pencil</VIcon>
              </VBtn>
              <VBtn size="x-small" variant="tonal" color="error" icon @click="eliminarPagoRem(item.id)">
                <VIcon size="14">mdi-delete</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>

        <VCardText v-if="!loadingRem && pagosRem.length === 0" class="text-center text-medium-emphasis py-8">
          No hay registros para este período.
          <br />
          <VBtn class="mt-3" variant="tonal" color="warning" prepend-icon="mdi-calendar-check" @click="dialogGenerar = true">
            Generar sueldos del mes
          </VBtn>
        </VCardText>
      </VCard>
    </div>

    <!-- ──────────── DIALOGS ──────────── -->

    <!-- Dialog Nuevo/Editar Empleado -->
    <VDialog v-model="dialogForm" max-width="600" persistent>
      <VCard :title="editando?.id ? 'Editar Empleado' : 'Nuevo Empleado'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="8">
              <VTextField v-model="form.nombre" label="Nombre completo" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.rut" label="RUT" density="compact" variant="outlined" hide-details placeholder="12345678-9" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.cargo" label="Cargo" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.sueldo_base" label="Sueldo base" type="number" density="compact" variant="outlined" hide-details prefix="$" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.fecha_ingreso" label="Fecha ingreso" type="date" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.fecha_egreso" label="Fecha egreso" type="date" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="5">
              <VTextField v-model="form.banco" label="Banco" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.cuenta_bancaria" label="N° Cuenta" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="3">
              <VSelect
                v-model="form.tipo_cuenta"
                label="Tipo"
                density="compact"
                variant="outlined"
                hide-details
                :items="['corriente', 'vista', 'ahorro']"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.notas" label="Notas" density="compact" variant="outlined" hide-details rows="2" />
            </VCol>
            <VCol cols="auto">
              <VSwitch v-model="form.activo" label="Activo" color="success" hide-details density="compact" />
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

    <!-- Dialog Historial de Pagos (por empleado) -->
    <VDialog v-model="dialogPagos" max-width="700">
      <VCard :title="`Historial — ${empleadoSeleccionado?.nombre}`">
        <VCardText>
          <VRow class="mb-3" align="center">
            <VCol class="text-body-2 text-medium-emphasis">
              Sueldo base: <strong>{{ formatMonto(empleadoSeleccionado?.sueldo_base) }}</strong>
            </VCol>
            <VCol cols="auto">
              <VBtn size="small" color="primary" prepend-icon="mdi-plus" @click="abrirNuevoPago">
                Registrar pago
              </VBtn>
            </VCol>
          </VRow>

          <VDataTable
            :headers="headersPagos"
            :items="pagosEmpleado"
            density="compact"
            item-value="id"
          >
            <template #item.periodo="{ item }">
              {{ item.periodo?.slice(0, 7) }}
            </template>
            <template #item.monto="{ item }">
              <span class="font-weight-medium">{{ formatMonto(item.monto) }}</span>
            </template>
            <template #item.pagado="{ item }">
              <VSwitch
                :model-value="item.pagado"
                density="compact"
                hide-details
                color="success"
                @update:modelValue="(v) => togglePagado(item, v)"
              />
            </template>
            <template #item.tipo="{ item }">
              <VChip size="x-small" variant="tonal" :color="tipoColor(item.tipo)">{{ item.tipo }}</VChip>
            </template>
            <template #item.actions="{ item }">
              <VBtn size="x-small" variant="tonal" color="error" icon @click="eliminarPago(item.id)">
                <VIcon size="14">mdi-delete</VIcon>
              </VBtn>
            </template>
          </VDataTable>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogPagos = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Nuevo/Editar Pago (historial y remuneraciones) -->
    <VDialog v-model="dialogNuevoPago" max-width="480" persistent>
      <VCard :title="editandoPago?.id ? 'Editar Pago' : 'Registrar Pago'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" v-if="!empleadoSeleccionado">
              <VSelect
                v-model="pagoForm.empleado_id"
                label="Empleado"
                density="compact"
                variant="outlined"
                hide-details
                :items="empleados.filter(e => e.activo)"
                item-title="nombre"
                item-value="id"
              />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="pagoForm.periodo" label="Período" type="month" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="6">
              <VSelect
                v-model="pagoForm.tipo"
                label="Tipo"
                density="compact"
                variant="outlined"
                hide-details
                :items="['sueldo', 'bono', 'finiquito']"
              />
            </VCol>
            <VCol cols="12">
              <VTextField v-model.number="pagoForm.monto" label="Monto" type="number" density="compact" variant="outlined" hide-details prefix="$" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="pagoForm.fecha_pago" label="Fecha de pago" type="date" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="6" class="d-flex align-center">
              <VSwitch v-model="pagoForm.pagado" label="Pagado" color="success" hide-details density="compact" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="pagoForm.notas" label="Notas" density="compact" variant="outlined" hide-details />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogNuevoPago = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="savingPago" @click="guardarPago">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Generar Sueldos -->
    <VDialog v-model="dialogGenerar" max-width="380">
      <VCard title="Generar Sueldos del Mes">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-3">
            Crea registros de sueldo para todos los empleados activos del período seleccionado.
            Si ya existe un registro para un empleado en ese período, se omite.
          </p>
          <VTextField
            v-model="periodoGenerar"
            label="Período"
            type="month"
            density="compact"
            variant="outlined"
            hide-details
          />
          <VAlert v-if="resultadoGenerar" class="mt-3" color="success" variant="tonal">
            {{ resultadoGenerar.creados }} sueldos generados para {{ resultadoGenerar.periodo?.slice(0, 7) }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogGenerar = false; resultadoGenerar = null">Cerrar</VBtn>
          <VBtn color="primary" :loading="generando" @click="generarSueldos">Generar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from '@/axiosInstance'

// ── Estado general ────────────────────────────────────────────────────────────

const tabActiva   = ref('nomina')
const empleados   = ref([])
const loading     = ref(false)
const saving      = ref(false)
const buscar      = ref('')
const dialogForm  = ref(false)
const editando    = ref(null)

const formVacio = () => ({
  nombre: '', rut: '', cargo: '', sueldo_base: 0,
  fecha_ingreso: '', fecha_egreso: '', activo: true,
  banco: '', cuenta_bancaria: '', tipo_cuenta: 'corriente', notas: '',
})
const form = ref(formVacio())

// ── Estado historial de pagos ─────────────────────────────────────────────────

const dialogPagos         = ref(false)
const dialogNuevoPago     = ref(false)
const savingPago          = ref(false)
const empleadoSeleccionado = ref(null)
const pagosEmpleado       = ref([])
const editandoPago        = ref(null)

const pagoFormVacio = () => ({
  empleado_id: null,
  periodo:    new Date().toISOString().slice(0, 7),
  tipo:       'sueldo',
  monto:      0,
  fecha_pago: '',
  pagado:     false,
  notas:      '',
})
const pagoForm = ref(pagoFormVacio())

// ── Estado remuneraciones ─────────────────────────────────────────────────────

const periodoRem   = ref(new Date().toISOString().slice(0, 7))
const pagosRem     = ref([])
const loadingRem   = ref(false)
const totalesRem   = ref({ total: 0, pagado: 0, pendiente: 0, cantidad: 0 })

// ── Estado generar sueldos ────────────────────────────────────────────────────

const dialogGenerar    = ref(false)
const generando        = ref(false)
const periodoGenerar   = ref(new Date().toISOString().slice(0, 7))
const resultadoGenerar = ref(null)

// ── Headers ───────────────────────────────────────────────────────────────────

const headers = [
  { title: 'Nombre', key: 'nombre' },
  { title: 'RUT', key: 'rut' },
  { title: 'Cargo', key: 'cargo' },
  { title: 'Sueldo base', key: 'sueldo_base', align: 'end' },
  { title: 'Ingreso', key: 'fecha_ingreso' },
  { title: 'Estado', key: 'activo', align: 'center' },
  { title: '', key: 'actions', sortable: false, width: '100px' },
]

const headersPagos = [
  { title: 'Período', key: 'periodo' },
  { title: 'Tipo', key: 'tipo' },
  { title: 'Monto', key: 'monto', align: 'end' },
  { title: 'Fecha pago', key: 'fecha_pago' },
  { title: 'Pagado', key: 'pagado', align: 'center' },
  { title: '', key: 'actions', sortable: false, width: '50px' },
]

const headersRem = [
  { title: 'Empleado', key: 'nombre' },
  { title: 'Cargo', key: 'cargo' },
  { title: 'Tipo', key: 'tipo', align: 'center' },
  { title: 'Monto', key: 'monto', align: 'end' },
  { title: 'Conciliación', key: 'conciliacion', align: 'center' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
]

// ── Computed ──────────────────────────────────────────────────────────────────

const empleadosFiltrados = computed(() => {
  if (!buscar.value) return empleados.value
  const q = buscar.value.toLowerCase()
  return empleados.value.filter(e =>
    e.nombre.toLowerCase().includes(q) || e.rut.includes(q) || (e.cargo || '').toLowerCase().includes(q)
  )
})

const empleadosActivos = computed(() => empleados.value.filter(e => e.activo).length)
const masaSalarial     = computed(() =>
  empleados.value.filter(e => e.activo).reduce((s, e) => s + parseFloat(e.sueldo_base || 0), 0)
)

// ── API Nómina ────────────────────────────────────────────────────────────────

async function cargar() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/empleados')
    empleados.value = data
  } finally {
    loading.value = false
  }
}

function abrirNuevo() {
  editando.value = null
  form.value = formVacio()
  dialogForm.value = true
}

function editar(emp) {
  editando.value = emp
  form.value = {
    ...emp,
    fecha_ingreso: emp.fecha_ingreso?.slice(0, 10) || '',
    fecha_egreso:  emp.fecha_egreso?.slice(0, 10) || '',
  }
  dialogForm.value = true
}

async function guardar() {
  saving.value = true
  try {
    if (editando.value?.id) {
      const { data } = await axios.put(`/api/empleados/${editando.value.id}`, form.value)
      const idx = empleados.value.findIndex(e => e.id === data.id)
      if (idx >= 0) empleados.value[idx] = data
    } else {
      const { data } = await axios.post('/api/empleados', form.value)
      empleados.value.push(data)
    }
    dialogForm.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este empleado?')) return
  await axios.delete(`/api/empleados/${id}`)
  empleados.value = empleados.value.filter(e => e.id !== id)
}

// ── Historial de pagos por empleado ──────────────────────────────────────────

async function verPagos(emp) {
  empleadoSeleccionado.value = emp
  const { data } = await axios.get(`/api/empleados/${emp.id}/pagos`)
  pagosEmpleado.value = data
  dialogPagos.value = true
}

function abrirNuevoPago() {
  editandoPago.value = null
  pagoForm.value = { ...pagoFormVacio(), monto: empleadoSeleccionado.value?.sueldo_base || 0 }
  dialogNuevoPago.value = true
}

async function guardarPago() {
  savingPago.value = true
  try {
    const empId = empleadoSeleccionado.value?.id ?? pagoForm.value.empleado_id

    const payload = {
      ...pagoForm.value,
      periodo: pagoForm.value.periodo + '-01',
    }

    if (editandoPago.value?.id) {
      const { data } = await axios.put(`/api/empleados/pagos/${editandoPago.value.id}`, payload)
      // Actualizar lista de historial si está abierta
      if (dialogPagos.value) {
        const idx = pagosEmpleado.value.findIndex(p => p.id === data.id)
        if (idx >= 0) pagosEmpleado.value[idx] = { ...pagosEmpleado.value[idx], ...data }
      }
      await cargarRemuneraciones()
    } else {
      await axios.post(`/api/empleados/${empId}/pagos`, payload)
      if (dialogPagos.value) {
        const { data: hist } = await axios.get(`/api/empleados/${empId}/pagos`)
        pagosEmpleado.value = hist
      }
      await cargarRemuneraciones()
    }

    dialogNuevoPago.value = false
    editandoPago.value = null
    if (!dialogPagos.value) empleadoSeleccionado.value = null
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar pago')
  } finally {
    savingPago.value = false
  }
}

async function togglePagado(pago, valor) {
  const { data } = await axios.put(`/api/empleados/pagos/${pago.id}`, { pagado: valor })
  Object.assign(pago, data)
}

async function eliminarPago(id) {
  if (!confirm('¿Eliminar este pago?')) return
  await axios.delete(`/api/empleados/pagos/${id}`)
  pagosEmpleado.value = pagosEmpleado.value.filter(p => p.id !== id)
}

// ── API Remuneraciones ────────────────────────────────────────────────────────

async function cargarRemuneraciones() {
  loadingRem.value = true
  try {
    const { data } = await axios.get('/api/empleados/pagos-por-periodo', {
      params: { periodo: periodoRem.value },
    })
    pagosRem.value   = data.pagos
    totalesRem.value = data.totales
  } finally {
    loadingRem.value = false
  }
}

function abrirNuevoPagoGlobal() {
  empleadoSeleccionado.value = null
  editandoPago.value = null
  pagoForm.value = { ...pagoFormVacio(), periodo: periodoRem.value }
  dialogNuevoPago.value = true
}

function editarPagoRem(item) {
  // Reconstituir el empleado seleccionado para que el PUT use el endpoint correcto
  empleadoSeleccionado.value = { id: item.empleado_id, nombre: item.nombre, sueldo_base: item.sueldo_base }
  editandoPago.value = item
  pagoForm.value = {
    empleado_id: item.empleado_id,
    periodo:     item.periodo?.slice(0, 7),
    tipo:        item.tipo,
    monto:       item.monto,
    fecha_pago:  item.fecha_pago?.slice(0, 10) || '',
    pagado:      !!item.pagado,
    notas:       item.notas || '',
  }
  dialogNuevoPago.value = true
}

async function eliminarPagoRem(id) {
  if (!confirm('¿Eliminar este registro de pago?')) return
  await axios.delete(`/api/empleados/pagos/${id}`)
  pagosRem.value = pagosRem.value.filter(p => p.id !== id)
  totalesRem.value.cantidad--
}

// ── Generar sueldos ───────────────────────────────────────────────────────────

async function generarSueldos() {
  generando.value = true
  resultadoGenerar.value = null
  try {
    const { data } = await axios.post('/api/empleados/generar-sueldos', { periodo: periodoGenerar.value })
    resultadoGenerar.value = data
    await cargarRemuneraciones()
    await cargar()
  } finally {
    generando.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatMonto(v) {
  return '$' + parseFloat(v || 0).toLocaleString('es-CL', { minimumFractionDigits: 0 })
}

function tipoColor(tipo) {
  return { sueldo: 'primary', bono: 'info', finiquito: 'error' }[tipo] ?? 'default'
}

// ── Sincronizar período generar con período rem ───────────────────────────────

watch(periodoRem, v => { periodoGenerar.value = v })

// ── Cargar al cambiar tab ─────────────────────────────────────────────────────

watch(tabActiva, tab => {
  if (tab === 'remuneraciones') cargarRemuneraciones()
})

onMounted(cargar)
</script>
