<template>
  <v-container fluid>
    <v-card>
      <v-card-title>Cotizador</v-card-title>
      <v-card-text>
        <v-row>
          <!-- Cliente -->
          <v-col cols="12" md="6">
            <v-autocomplete
              label="Cliente"
              :items="clientes"
              v-model="form.cliente_id"
              item-title="nombre"
              item-value="id"
            />
          </v-col>

          <!-- Observaciones -->
          <v-col cols="12" md="6">
            <v-text-field
              label="Observaciones"
              v-model="form.observaciones"
            />
          </v-col>

          <!-- Material general -->
          <v-col cols="6" md="3">
            <v-select
              label="Material"
              :items="materiales"
              item-title="nombre"
              item-value="id"
              v-model="form.material_id"
            />
          </v-col>

          <!-- Color general -->
          <v-col cols="6" md="3">
            <v-select
              label="Color"
              :items="colores"
              item-title="nombre"
              item-value="id"
              v-model="form.color_id"
            />
          </v-col>

          <!-- Tipo vidrio general -->
          <v-col cols="6" md="3">
            <v-select
              label="Tipo de vidrio"
              :items="tiposVidrio"
              item-title="nombre"
              item-value="id"
              v-model="form.tipo_vidrio_id"
            />
          </v-col>

          <!-- Producto de vidrio general -->
          <v-col cols="6" md="3">
            <v-select
              label="Producto de vidrio"
              :items="productosVidrio"
              item-title="nombre"
              item-value="id"
              v-model="form.producto_vidrio_id"
            />
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Botón para abrir modal -->
      <v-card-actions>
        <v-btn color="primary" @click="abrirModalVentana">
          Agregar Ventana
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Lista de ventanas -->
    <v-card class="mt-4">
      <v-card-title>Ventanas en esta cotización</v-card-title>
      <v-card-text>
        <v-table>
          <thead>
            <tr>
              <th>Ancho</th>
              <th>Alto</th>
              <th>Cantidad</th>
              <th>Tipo ventana</th>
              <th>Material</th>
              <th>Color</th>
              <th>Vidrio</th>
              <th>Costo</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ventana, i) in ventanas" :key="i">
              <td>{{ ventana.ancho }}</td>
              <td>{{ ventana.alto }}</td>
              <td>{{ ventana.cantidad }}</td>
              <td>{{ nombreTipoVentana(ventana.tipo_ventana_id) }}</td>
              <td>{{ nombreMaterial(ventana.material_id) }}</td>
              <td>{{ nombreColor(ventana.color_id) }}</td>
              <td>{{ nombreProductoVidrio(ventana.producto_vidrio_id) }}</td>
              <td>${{ ventana.costo?.toLocaleString() }}</td>
              <td>
                <v-btn icon color="red" @click="eliminarVentana(i)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn color="success" @click="guardarCotizacion">
          Guardar Cotización
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Modal para agregar ventana -->
    <AgregarVentanaModal
      v-model:mostrar="mostrarModalVentana"
      @guardar="agregarVentana"
      :tiposVentana="tiposVentana"
      :colores="colores"
      :materiales="materiales"
      :tiposVidrio="tiposVidrio"
      :productosVidrio="productosVidrio"
    />
  </v-container>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AgregarVentanaModal from '@/pages/AgregarVentanaModal2.vue'

// Formulario principal
const form = reactive({
  cliente_id: null,
  observaciones: '',
  material_id: null,
  color_id: null,
  tipo_vidrio_id: null,
  producto_vidrio_id: null
})

// Catálogos
const clientes = ref([])
const materiales = ref([])
const colores = ref([])
const tiposVidrio = ref([])
const tiposVentana = ref([])
const productosVidrio = ref([])

// Lista de ventanas agregadas
const ventanas = ref([])

// Modal
const mostrarModalVentana = ref(false)

// Abrir modal
const abrirModalVentana = () => {
  mostrarModalVentana.value = true
}

// Agregar ventana (con cálculo de materiales)
const agregarVentana = async (ventana) => {
  try {
    const { data } = await axios.post('/api/cotizador/calcular-materiales', ventana)
    ventanas.value.push({
      ...ventana,
      costo: data.costo_total
    })
  } catch (error) {
    console.error('Error calculando materiales:', error)
  }
}

// Eliminar ventana
const eliminarVentana = (i) => {
  ventanas.value.splice(i, 1)
}

// Guardar cotización completa
const guardarCotizacion = async () => {
  try {
    const payload = {
      ...form,
      ventanas: ventanas.value
    }
    await axios.post('/api/cotizaciones', payload)
    alert('Cotización guardada correctamente')
  } catch (error) {
    console.error('Error guardando cotización:', error)
  }
}

// Cargar catálogos iniciales
onMounted(async () => {
  try {
    const [cli, mat, col, tipVid, tipVen, prodVid] = await Promise.all([
      axios.get('/api/clientes'),
      axios.get('/api/materiales'),
      axios.get('/api/colores'),
      axios.get('/api/tipos-vidrio'),
      axios.get('/api/tipos-ventana'),
      axios.get('/api/productos-vidrio')
    ])
    clientes.value = cli.data
    materiales.value = mat.data
    colores.value = col.data
    tiposVidrio.value = tipVid.data
    tiposVentana.value = tipVen.data
    productosVidrio.value = prodVid.data
  } catch (error) {
    console.error('Error cargando catálogos:', error)
  }
})

// Helpers para mostrar nombres
const nombreTipoVentana = (id) => tiposVentana.value.find(t => t.id === id)?.nombre || ''
const nombreMaterial = (id) => materiales.value.find(m => m.id === id)?.nombre || ''
const nombreColor = (id) => colores.value.find(c => c.id === id)?.nombre || ''
const nombreProductoVidrio = (id) => productosVidrio.value.find(p => p.id === id)?.nombre || ''
</script>
