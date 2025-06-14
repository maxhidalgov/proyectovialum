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
            <v-chip :color="getEstadoColor(cotizacion?.estado?.nombre)">
              {{ cotizacion?.estado?.nombre || '—' }}
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

          <!-- Template para mostrar producto + proveedor en la columna de vidrio -->
        <template #item.producto_vidrio_proveedor_id="{ item }">
          {{ item.producto_vidrio_proveedor?.producto?.nombre || '—' }}
          <span v-if="item.producto_vidrio_proveedor?.proveedor">
            ({{ item.producto_vidrio_proveedor.proveedor.nombre }})
          </span>
        </template>

          <template #item.costo="{ item }">
            ${{ Number(item.costo)?.toLocaleString('es-CL', { minimumFractionDigits: 0 }) || 0 }}
          </template>

          <template #item.precio="{ item }">
            ${{ Number(item.precio)?.toLocaleString('es-CL', { minimumFractionDigits: 0 }) || 0 }}
          </template>

        <template #item.precio_total="{ item }">
          ${{ Number(item.precio_total)?.toLocaleString('es-CL', { minimumFractionDigits: 0 }) || 0 }}  
        </template>
      </v-data-table>
      <v-row justify="end" class="px-6 pb-4">
        <v-col cols="12" sm="4" class="text-right">
          <v-alert type="success" variant="tonal" border="start" border-color="green">
            <strong>Total general:</strong>
            ${{ cotizacion?.total_general?.toLocaleString('es-CL', { minimumFractionDigits: 0 }) || 0 }}
          </v-alert>
        </v-col>
      </v-row>
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
  { title: 'Cantidad', value: 'cantidad' },
  { title: 'Color', value: 'color.nombre' },
  { title: 'Vidrio', value: 'producto_vidrio_proveedor_id' },
  // { title: 'Vidrio', value: 'producto_vidrio_proveedor.producto.nombre' },
  // { title: 'Vidrio Proveedor', value: 'producto_vidrio_proveedor.proveedor.nombre' },
  { title: 'Costo', value: 'costo' },
  { title: 'Precio Unitario', value: 'precio' },
  { title: 'Precio Total', value: 'precio_total' },

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
