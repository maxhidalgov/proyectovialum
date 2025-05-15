<template>
  <v-container>
    <template v-if="cotizacion">
      <v-card class="mb-4">
        <v-card-title>
          Editar Cotización #{{ cotizacion.id }}
          <v-spacer />
          <v-btn icon @click="volver">
            <v-icon>mdi-arrow-left</v-icon>
          </v-btn>
        </v-card-title>

        <v-card-text>
          <v-form @submit.prevent="guardarCambios" ref="form">
            <v-row>
              <v-col cols="12" sm="6">
                <v-text-field v-model="cotizacion.fecha" label="Fecha" type="date" />
              </v-col>

              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.estado"
                  :items="estados"
                  label="Estado"
                />
              </v-col>

              <v-col cols="12">
                <v-textarea v-model="cotizacion.observaciones" label="Observaciones" />
              </v-col>
            </v-row>

            <v-btn type="submit" color="primary" class="mt-4">Guardar Cambios</v-btn>
          </v-form>
        </v-card-text>
      </v-card>

      <v-card>
        <v-card-title>Resumen de Ventanas</v-card-title>
        <v-data-table
          :headers="headers"
          :items="cotizacion.ventanas"
          :items-per-page="5"
          class="elevation-1"
        >
          <template #item.tipo_ventana_id="{ item }">
            {{ item.tipo_ventana?.nombre || '—' }}
          </template>
        </v-data-table>
      </v-card>
    </template>

    <template v-else>
      <v-alert type="info" variant="outlined" class="mt-4">
        Cargando cotización...
      </v-alert>
    </template>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/axiosInstance'

const route = useRoute()
const router = useRouter()
const cotizacionId = route.query.id

const cotizacion = ref({
  fecha: '',
  estado: '',
  observaciones: '',
  ventanas: [],
})

const form = ref(null)

const estados = ['Evaluación', 'Aprobada', 'Rechazada']

const headers = [
  { title: 'Tipo de ventana', value: 'tipo_ventana_id' },
  { title: 'Ancho (mm)', value: 'ancho' },
  { title: 'Alto (mm)', value: 'alto' },
  { title: 'Color ID', value: 'color_id' },
  { title: 'Vidrio', value: 'producto_vidrio_proveedor_id' },
  { title: 'Costo', value: 'costo' },
  { title: 'Precio', value: 'precio' },
]

const volver = () => {
  router.push({ path: '/cotizaciones' })
}

const guardarCambios = async () => {
  try {
    await api.put(`/api/cotizaciones/${cotizacion.value.id}`, {
      estado: cotizacion.value.estado,
      fecha: cotizacion.value.fecha,
      observaciones: cotizacion.value.observaciones,
    })
    alert('Cotización actualizada correctamente')
    volver()
  } catch (error) {
    console.error(error)
    alert('Error al guardar cambios')
  }
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
