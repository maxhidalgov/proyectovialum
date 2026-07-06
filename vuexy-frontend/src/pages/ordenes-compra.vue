<template>
  <v-container fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4">
      <v-icon icon="mdi-cart-arrow-down" size="32" color="deep-purple" />
      <div>
        <h1 class="text-h5 font-weight-bold">Órdenes de Compra</h1>
        <p class="text-caption text-grey mt-1">Historial de órdenes generadas para proveedores</p>
      </div>
      <v-spacer />
      <v-text-field
        v-model="busqueda"
        prepend-inner-icon="mdi-magnify"
        label="Buscar N° o proveedor"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:280px"
        @update:model-value="cargar"
      />
    </div>

    <v-card variant="outlined">
      <v-data-table
        :headers="headers"
        :items="ordenes"
        :loading="loading"
        density="compact"
        items-per-page="25"
      >
        <template #item.numero="{ item }">
          <span class="font-weight-bold font-monospace">{{ item.numero }}</span>
        </template>
        <template #item.fecha="{ item }">
          {{ fmtFecha(item.fecha) }}
        </template>
        <template #item.proveedor="{ item }">
          {{ item.proveedor || '—' }}
        </template>
        <template #item.cliente="{ item }">
          {{ item.cliente || '—' }}
        </template>
        <template #item.items_count="{ item }">
          <v-chip size="x-small" color="deep-purple" variant="tonal">{{ item.items_count }} ítems</v-chip>
        </template>
        <template #item.acciones="{ item }">
          <div class="d-flex gap-1 justify-end">
            <v-btn size="x-small" color="red" variant="tonal" icon="mdi-file-pdf-box" @click="descargar(item, 'pdf')" />
            <v-btn size="x-small" color="green" variant="tonal" icon="mdi-file-excel" @click="descargar(item, 'excel')" />
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <v-icon size="40" class="mb-2">mdi-cart-off</v-icon>
            <p>No hay órdenes de compra. Genéralas desde el Taller (Materiales WP).</p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">
      {{ snack.msg }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'
const api = axios

const ordenes  = ref([])
const loading  = ref(false)
const busqueda = ref('')
const snack    = ref({ show: false, color: 'success', msg: '' })

const headers = [
  { title: 'N°',        key: 'numero' },
  { title: 'Fecha',     key: 'fecha' },
  { title: 'Proveedor', key: 'proveedor' },
  { title: 'Obra/Cliente', key: 'cliente' },
  { title: 'Ítems',     key: 'items_count' },
  { title: 'Creada por', key: 'creador' },
  { title: '',          key: 'acciones', sortable: false, align: 'end' },
]

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/ordenes-compra', { params: { buscar: busqueda.value || undefined } })
    ordenes.value = Array.isArray(data) ? data : []
  } catch {
    ordenes.value = []
  } finally {
    loading.value = false
  }
}

async function descargar(orden, formato) {
  try {
    const res = await api.get(`/api/ordenes-compra/${orden.id}/${formato}`, { responseType: 'blob' })
    const ext = formato === 'pdf' ? 'pdf' : 'csv'
    const mime = formato === 'pdf' ? 'application/pdf' : 'text/csv'
    const url = window.URL.createObjectURL(new Blob([res.data], { type: mime }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${orden.numero}.${ext}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch {
    snack.value = { show: true, color: 'error', msg: 'Error al descargar' }
  }
}

function fmtFecha(f) {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(cargar)
</script>
