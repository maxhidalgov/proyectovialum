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
        <v-card-title>Ventanas</v-card-title>

        <div v-if="cotizacion.estado === 'Evaluación'">
          <VentanaForm
            v-for="(ventana, index) in cotizacion.ventanas"
            :key="index"
            :ventana="ventana"
            :tiposVentana="tiposVentanaTodos"
            :colores="colores"
            :tiposVidrio="tiposVidrio"
            :productosVidrio="productosVidrioFiltrados"
          />
        </div>

        <v-data-table
          v-else
          :headers="headers"
          :items="cotizacion.ventanas"
          :items-per-page="5"
          class="elevation-1"
        >
          <template #item.tipo_ventana_id="{ item }">
            {{ item.tipo_ventana?.nombre || '—' }}
          </template>
          <template #item.color_id="{ item }">
            <v-chip color="primary" variant="tonal" size="small">
              {{ item.color?.nombre || '—' }}
            </v-chip>
          </template>
          <template #item.producto_vidrio_proveedor_id="{ item }">
            <v-chip color="green" variant="tonal" size="small">
              {{ item.producto_vidrio_proveedor?.producto?.nombre || '—' }}
              <span v-if="item.producto_vidrio_proveedor?.proveedor">
                ({{ item.producto_vidrio_proveedor.proveedor.nombre }})
              </span>
            </v-chip>
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
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/axiosInstance'
import VentanaForm from './VentanaForm.vue'

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
  { title: 'Color', value: 'color_id' },
  { title: 'Vidrio', value: 'producto_vidrio_proveedor_id' },
  { title: 'Costo', value: 'costo' },
  { title: 'Precio', value: 'precio' },
]

const tiposVentanaTodos = ref([])
const colores = ref([])
const tiposVidrio = ref([])
const productosVidrio = ref([])

const productosVidrioFiltrados = computed(() => {
  return productosVidrio.value.flatMap(p =>
    p.colores_por_proveedor.map(cpp => ({
      id: cpp.id,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id,
      nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Desconocido'})`
    }))
  )
})

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
    const [cotizacionRes, tiposVentanaRes, coloresRes, tiposVidrioRes, productosRes] = await Promise.all([
      api.get(`/api/cotizaciones/${cotizacionId}`),
      api.get('/api/tipos_ventana'),
      api.get('/api/colores'),
      api.get('/api/tipos_producto'),
      api.get('/api/productos'),
    ])

    cotizacion.value = cotizacionRes.data
    // Asegura que cada ventana tenga el campo `tipo_vidrio_id` para el select
    cotizacion.value.ventanas = cotizacion.value.ventanas.map(v => ({
      ...v,
      tipo_vidrio_id: v.producto_vidrio_proveedor?.producto?.tipo_producto_id ?? null,
    }))
    tiposVentanaTodos.value = tiposVentanaRes.data
    colores.value = coloresRes.data
    tiposVidrio.value = tiposVidrioRes.data.filter(t => [1, 2].includes(t.id))
    productosVidrio.value = productosRes.data.filter(p => [1, 2].includes(p.tipo_producto_id))
  } catch (error) {
    console.error('Error al cargar cotización:', error)
  }
})
</script>
