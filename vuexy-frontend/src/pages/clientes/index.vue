<template>
  <div>
    <!-- Header -->
    <v-row class="mb-4" align="center">
      <v-col>
        <h1 class="text-h4 mb-1">👥 Clientes</h1>
        <div class="d-flex align-center gap-2">
          <v-chip
            v-if="ultimaSync"
            size="small"
            variant="tonal"
            color="success"
            prepend-icon="mdi-check-circle-outline"
          >
            Última sync: {{ ultimaSync }}
          </v-chip>
          <span v-else class="text-medium-emphasis text-body-2">Nunca sincronizado</span>
        </div>
      </v-col>
      <v-col cols="auto" class="d-flex gap-2">
        <v-btn
          color="success"
          prepend-icon="mdi-plus"
          variant="tonal"
          @click="dialogCrear = true"
        >
          Nuevo cliente
        </v-btn>
        <v-btn
          @click="sincronizarClientes"
          color="primary"
          prepend-icon="mdi-sync"
          :loading="sincronizando"
        >
          Sincronizar desde Bsale
        </v-btn>
      </v-col>
    </v-row>

    <!-- Stats rápidas post-sync -->
    <v-row v-if="statsSync" class="mb-4">
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="primary" rounded="lg">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ statsSync.total }}</div>
            <div class="text-caption text-medium-emphasis">Procesados</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="success" rounded="lg">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ statsSync.nuevos }}</div>
            <div class="text-caption text-medium-emphasis">Nuevos</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="info" rounded="lg">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ statsSync.actualizados }}</div>
            <div class="text-caption text-medium-emphasis">Actualizados</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" :color="statsSync.errores > 0 ? 'error' : 'default'" rounded="lg">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ statsSync.errores }}</div>
            <div class="text-caption text-medium-emphasis">Errores</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtros -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="busqueda"
              label="Buscar cliente"
              placeholder="Buscar por nombre, RUT, email..."
              prepend-inner-icon="mdi-magnify"
              clearable
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtroTipo"
              :items="[
                { title: 'Todos', value: null },
                { title: 'Empresas', value: 'empresa' },
                { title: 'Personas', value: 'persona' }
              ]"
              label="Tipo de cliente"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3" class="d-flex align-center">
            <span class="text-medium-emphasis text-body-2">
              {{ clientesFiltrados.length }} cliente{{ clientesFiltrados.length !== 1 ? 's' : '' }}
            </span>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <ClienteTable :clientes="clientesFiltrados" :loading="loading" />

    <!-- Modal crear cliente -->
    <v-dialog v-model="dialogCrear" max-width="600" persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between pa-4">
          <span>Nuevo cliente</span>
          <v-btn icon="mdi-close" variant="text" @click="cerrarDialogCrear" />
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-form ref="formCrear" v-model="formValido">
            <v-row>
              <v-col cols="12">
                <v-select
                  v-model="nuevoCliente.tipo_cliente"
                  :items="[{ title: 'Empresa', value: 'empresa' }, { title: 'Persona natural', value: 'persona' }]"
                  label="Tipo de cliente *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Requerido']"
                />
              </v-col>
              <template v-if="nuevoCliente.tipo_cliente === 'empresa'">
                <v-col cols="12">
                  <v-text-field v-model="nuevoCliente.razon_social" label="Razón social *" variant="outlined" density="compact" :rules="[v => !!v || 'Requerido']" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="nuevoCliente.giro" label="Giro" variant="outlined" density="compact" />
                </v-col>
              </template>
              <template v-else>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="nuevoCliente.first_name" label="Nombre *" variant="outlined" density="compact" :rules="[v => !!v || 'Requerido']" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="nuevoCliente.last_name" label="Apellido" variant="outlined" density="compact" />
                </v-col>
              </template>
              <v-col cols="12" sm="6">
                <v-text-field v-model="nuevoCliente.identification" label="RUT" variant="outlined" density="compact" placeholder="12.345.678-9" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="nuevoCliente.phone" label="Teléfono" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="nuevoCliente.email" label="Email" variant="outlined" density="compact" type="email" />
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="nuevoCliente.address" label="Dirección" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="nuevoCliente.ciudad" label="Ciudad" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="nuevoCliente.comuna" label="Comuna" variant="outlined" density="compact" />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="cerrarDialogCrear">Cancelar</v-btn>
          <v-btn color="success" variant="flat" :loading="guardando" @click="guardarCliente">
            Guardar cliente
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar de notificaciones -->
    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="bottom right"
      multi-line
    >
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.show = false">Cerrar</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { fetchClientesLocales } from '@/api/clientes'
import ClienteTable from '../ClienteTable.vue'
import api from '@/axiosInstance'

const LS_KEY = 'clientes_ultima_sync'

const clientes = ref([])
const loading = ref(false)
const sincronizando = ref(false)
const busqueda = ref('')
const filtroTipo = ref(null)
const statsSync = ref(null)
const ultimaSync = ref(localStorage.getItem(LS_KEY) || null)

const snackbar = ref({ show: false, text: '', color: 'success', timeout: 5000 })

// Crear cliente
const dialogCrear = ref(false)
const formCrear = ref(null)
const formValido = ref(false)
const guardando = ref(false)
const nuevoClienteVacio = () => ({
  tipo_cliente: null, razon_social: '', giro: '', first_name: '', last_name: '',
  identification: '', phone: '', email: '', address: '', ciudad: '', comuna: '',
})
const nuevoCliente = ref(nuevoClienteVacio())

const mostrarNotificacion = (text, color = 'success', timeout = 5000) => {
  snackbar.value = { show: true, text, color, timeout }
}

const clientesFiltrados = computed(() => {
  let resultado = clientes.value

  if (busqueda.value) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(c =>
      c.razon_social?.toLowerCase().includes(termino) ||
      c.first_name?.toLowerCase().includes(termino) ||
      c.last_name?.toLowerCase().includes(termino) ||
      c.identification?.toLowerCase().includes(termino) ||
      c.email?.toLowerCase().includes(termino)
    )
  }

  if (filtroTipo.value) {
    resultado = resultado.filter(c => c.tipo_cliente === filtroTipo.value)
  }

  return resultado
})

const cerrarDialogCrear = () => {
  dialogCrear.value = false
  nuevoCliente.value = nuevoClienteVacio()
  formCrear.value?.reset()
}

const guardarCliente = async () => {
  const { valid } = await formCrear.value.validate()
  if (!valid) return
  try {
    guardando.value = true
    await api.post('/api/clientes', nuevoCliente.value)
    mostrarNotificacion('Cliente creado correctamente.', 'success')
    cerrarDialogCrear()
    await loadClientes()
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al guardar el cliente.'
    mostrarNotificacion(msg, 'error', 8000)
  } finally {
    guardando.value = false
  }
}

const sincronizarClientes = async () => {
  try {
    sincronizando.value = true
    statsSync.value = null

    const res = await api.post('/api/clientes/sincronizar-bsale')
    const { stats } = res.data

    statsSync.value = stats

    const ahora = new Date().toLocaleString('es-CL', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
    ultimaSync.value = ahora
    localStorage.setItem(LS_KEY, ahora)

    const msg = `Sincronización completada: ${stats.total} procesados, ${stats.nuevos} nuevos, ${stats.actualizados} actualizados${stats.errores > 0 ? `, ${stats.errores} errores` : ''}.`
    mostrarNotificacion(msg, stats.errores > 0 ? 'warning' : 'success')

    await loadClientes()
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al sincronizar clientes.'
    mostrarNotificacion(msg, 'error', 8000)
  } finally {
    sincronizando.value = false
  }
}

const loadClientes = async () => {
  loading.value = true
  try {
    const data = await fetchClientesLocales()
    clientes.value = Array.isArray(data) ? data : []
  } catch {
    clientes.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadClientes()
})
</script>
  