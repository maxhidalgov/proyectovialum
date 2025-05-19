<template>
  <v-container>
    <v-card class="mb-4">
      <v-card-title>
        Cotización #{{ cotizacion?.id }}
        <v-btn
          class="ml-2"
          color="secondary"
          :href="`/cotizaciones/${cotizacion?.id}/pdf`"
          target="_blank"
        >
          Descargar PDF
        </v-btn>
        <v-spacer />
        <v-btn icon @click="volver">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <strong>Cliente:</strong> {{ cotizacion?.cliente?.nombre }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Vendedor:</strong> {{ cotizacion?.vendedor?.name }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Fecha:</strong> {{ cotizacion?.fecha }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Estado:</strong>
            <v-chip :color="getEstadoColor(cotizacion?.estado)">
              {{ cotizacion?.estado }}
            </v-chip>
          </v-col>
          <v-col cols="12">
            <strong>Observaciones:</strong><br />
            {{ cotizacion?.observaciones || '—' }}
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title>Ventanas</v-card-title>
      <v-data-table
        :headers="headers"
        :items="cotizacion?.ventanas || []"
        :items-per-page="5"
        class="elevation-1"
      >
        <template #item.tipo_ventana_id="{ item }">
          {{ item.tipo_ventana?.nombre || '—' }}
        </template>
      </v-data-table>
    </v-card>
  </v-container>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import api from '@/axiosInstance'


const route = useRoute()
const router = useRouter()
const cotizacionId = route.query.id
const apiBase = import.meta.env.VITE_API_BASE_URL
const cotizacion = ref(null)

const headers = [
  { title: 'Tipo de ventana', value: 'tipo_ventana_id' },
  { title: 'Ancho (mm)', value: 'ancho' },
  { title: 'Alto (mm)', value: 'alto' },
  { title: 'Color ID', value: 'color_id' },
  { title: 'Vidrio', value: 'producto_vidrio_proveedor_id' },
  { title: 'Costo', value: 'costo' },
  { title: 'Precio', value: 'precio' },
]

const getEstadoColor = (estado) => {
  switch (estado) {
    case 'Evaluación': return 'grey'
    case 'Aprobada': return 'green'
    case 'Rechazada': return 'red'
    default: return 'blue'
  }
}

const volver = () => {
  router.push({ name: 'cotizaciones' })
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/cotizaciones/${cotizacionId}`)
    cotizacion.value = data
  } catch (error) {
    console.error('Error al cargar cotización:', error)
  }
})
</script>
