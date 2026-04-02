<template>
  <v-container fluid>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <div>
          <h2 class="text-h5">Proveedores</h2>
          <p class="text-subtitle-2 text-grey mt-1">Gestiona los proveedores del sistema</p>
        </div>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="abrirModalNuevo">
          Nuevo Proveedor
        </v-btn>
      </v-card-title>

      <v-divider />

      <v-card-text>
        <v-text-field
          v-model="busqueda"
          label="Buscar por nombre o contacto"
          prepend-inner-icon="mdi-magnify"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 400px;"
        />
      </v-card-text>

      <v-data-table
        :headers="headers"
        :items="proveedoresFiltrados"
        :loading="cargando"
        :items-per-page="15"
        :items-per-page-options="[10, 15, 25, 50]"
        class="elevation-1"
      >
        <template #item.actions="{ item }">
          <v-btn
            icon
            variant="text"
            size="small"
            color="primary"
            @click="abrirModalEditar(item)"
          >
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn
            icon
            variant="text"
            size="small"
            color="error"
            @click="confirmarEliminar(item)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Modal Crear / Editar -->
    <v-dialog v-model="dialog" max-width="480" persistent>
      <v-card>
        <v-card-title>{{ editando ? 'Editar Proveedor' : 'Nuevo Proveedor' }}</v-card-title>
        <v-divider />
        <v-card-text>
          <v-form ref="formRef" @submit.prevent="guardar">
            <v-text-field
              v-model="form.nombre"
              label="Nombre"
              variant="outlined"
              density="compact"
              :rules="[v => !!v || 'Campo requerido']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.contacto"
              label="Contacto (opcional)"
              variant="outlined"
              density="compact"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="justify-end pa-4">
          <v-btn variant="text" @click="cerrarModal">Cancelar</v-btn>
          <v-btn color="primary" :loading="guardando" @click="guardar">
            {{ editando ? 'Guardar Cambios' : 'Crear' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmación Eliminar -->
    <v-dialog v-model="dialogEliminar" max-width="400">
      <v-card>
        <v-card-title>Eliminar Proveedor</v-card-title>
        <v-card-text>
          ¿Estás seguro de eliminar <strong>{{ proveedorAEliminar?.nombre }}</strong>?
        </v-card-text>
        <v-card-actions class="justify-end pa-4">
          <v-btn variant="text" @click="dialogEliminar = false">Cancelar</v-btn>
          <v-btn color="error" :loading="eliminando" @click="eliminar">Eliminar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'

const proveedores = ref([])
const cargando    = ref(false)
const busqueda    = ref('')

const dialog    = ref(false)
const editando  = ref(false)
const guardando = ref(false)
const formRef   = ref(null)
const form      = ref({ nombre: '', contacto: '' })
const proveedorEditando = ref(null)

const dialogEliminar     = ref(false)
const eliminando         = ref(false)
const proveedorAEliminar = ref(null)

const snackbar = ref({ show: false, text: '', color: 'success' })

const headers = [
  { title: 'ID',       key: 'id',       width: '80px' },
  { title: 'Nombre',   key: 'nombre'   },
  { title: 'Contacto', key: 'contacto' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

const proveedoresFiltrados = computed(() => {
  const q = busqueda.value?.toLowerCase() ?? ''
  if (!q) return proveedores.value
  return proveedores.value.filter(p =>
    p.nombre?.toLowerCase().includes(q) ||
    p.contacto?.toLowerCase().includes(q)
  )
})

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/proveedores')
    proveedores.value = data
  } catch {
    mostrarSnackbar('Error al cargar proveedores', 'error')
  } finally {
    cargando.value = false
  }
}

function abrirModalNuevo() {
  editando.value = false
  form.value = { nombre: '', contacto: '' }
  proveedorEditando.value = null
  dialog.value = true
}

function abrirModalEditar(item) {
  editando.value = true
  form.value = { nombre: item.nombre, contacto: item.contacto ?? '' }
  proveedorEditando.value = item
  dialog.value = true
}

function cerrarModal() {
  dialog.value = false
  formRef.value?.reset()
}

async function guardar() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  guardando.value = true
  try {
    if (editando.value) {
      const { data } = await api.put(`/api/proveedores/${proveedorEditando.value.id}`, form.value)
      const idx = proveedores.value.findIndex(p => p.id === data.id)
      if (idx !== -1) proveedores.value[idx] = data
      mostrarSnackbar('Proveedor actualizado')
    } else {
      const { data } = await api.post('/api/proveedores', form.value)
      proveedores.value.push(data)
      mostrarSnackbar('Proveedor creado')
    }
    cerrarModal()
  } catch {
    mostrarSnackbar('Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

function confirmarEliminar(item) {
  proveedorAEliminar.value = item
  dialogEliminar.value = true
}

async function eliminar() {
  eliminando.value = true
  try {
    await api.delete(`/api/proveedores/${proveedorAEliminar.value.id}`)
    proveedores.value = proveedores.value.filter(p => p.id !== proveedorAEliminar.value.id)
    mostrarSnackbar('Proveedor eliminado')
    dialogEliminar.value = false
  } catch {
    mostrarSnackbar('Error al eliminar', 'error')
  } finally {
    eliminando.value = false
  }
}

function mostrarSnackbar(text, color = 'success') {
  snackbar.value = { show: true, text, color }
}

onMounted(cargar)
</script>
