<template>
  <div>
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-icon icon="mdi-factory" size="32" color="primary" />
          <div>
            <h1 class="text-h5 font-weight-bold">Producción</h1>
            <div class="text-body-2 text-medium-emphasis">Hojas de cortes por cotización</div>
          </div>
        </div>
      </v-col>
    </v-row>

    <v-card rounded="lg">
      <v-card-text class="pa-0">
        <v-data-table
          :headers="headers"
          :items="cotizaciones"
          :loading="loading"
          :search="search"
          items-per-page="15"
        >
          <template #top>
            <div class="pa-4">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                label="Buscar cotización..."
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 360px"
              />
            </div>
          </template>

          <template #item.id="{ item }">
            <span class="font-weight-bold">#{{ item.id }}</span>
          </template>

          <template #item.cliente="{ item }">
            {{ item.cliente?.razon_social || item.cliente?.first_name || '—' }}
          </template>

          <template #item.estado="{ item }">
            <v-chip size="small" :color="estadoColor(item.estado?.nombre)" variant="tonal">
              {{ item.estado?.nombre || '—' }}
            </v-chip>
          </template>

          <template #item.total="{ item }">
            {{ formatCLP(item.total) }}
          </template>

          <template #item.acciones="{ item }">
            <div class="d-flex gap-2">
              <v-btn
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-scissors-cutting"
                :to="{ name: 'produccion-id', params: { id: item.id } }"
              >
                Hoja de Cortes
              </v-btn>
              <v-btn
                size="small"
                color="secondary"
                variant="tonal"
                prepend-icon="mdi-clipboard-list-outline"
                :to="{ name: 'produccion-materiales-id', params: { id: item.id } }"
              >
                Materiales
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/axiosInstance'

const cotizaciones = ref([])
const loading = ref(true)
const search = ref('')

const headers = [
  { title: '#',       value: 'id',       sortable: true },
  { title: 'Fecha',   value: 'fecha',    sortable: true },
  { title: 'Cliente', value: 'cliente',  sortable: false },
  { title: 'Estado',  value: 'estado',   sortable: false },
  { title: 'Total',   value: 'total',    sortable: true, align: 'end' },
  { title: '',        value: 'acciones', sortable: false, align: 'end' },
]

const estadoColor = (nombre) => {
  const mapa = {
    'Evaluación':    'grey',
    'Aprobada':      'success',
    'Rechazada':     'error',
    'Anulada':       'error',
    'Enviada':       'orange',
    'Facturada':     'teal',
    'En Producción': 'blue',
    'Entregada':     'purple',
  }
  return mapa[nombre] || 'default'
}

const formatCLP = (n) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n || 0)

onMounted(async () => {
  try {
    const res = await api.get('/api/cotizaciones')
    cotizaciones.value = res.data?.data ?? res.data ?? []
  } catch {
    cotizaciones.value = []
  } finally {
    loading.value = false
  }
})
</script>
