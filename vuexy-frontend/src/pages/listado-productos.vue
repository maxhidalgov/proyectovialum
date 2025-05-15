<template>
    <v-container fluid>
    <!-- Tabla -->
    <v-row>
      <v-col cols="12" md="10" lg="8" class="mx-auto mt-6">
        <v-data-table
          :items="productos"
          :headers="headers"
          :search="filtro"
          class="elevation-1"
        >
  <!-- Buscador -->
  <template #top>
    <v-text-field
      v-model="filtro"
      label="Buscar producto"
      class="mx-4"
      prepend-inner-icon="mdi-magnify"
      clearable
      dense
    />
  </template>

  <!-- Colores 
  <template #item.colores="{ item }">
    <div v-if="item.colores_por_proveedor?.length">
      <v-chip
        v-for="combo in item.colores_por_proveedor"
        :key="combo.color_id + '-' + combo.proveedor_id"
        class="ma-1"
        size="small"
        color="primary"
        variant="tonal"
      >
        {{ combo.color?.nombre }}
      </v-chip>
    </div>
    <span v-else class="text-disabled">Sin colores</span>
  </template>-->

<!-- Proveedor -->
<template #item.proveedor="{ item }">
  <div v-if="item.colores_por_proveedor?.length">
    <v-chip
      v-for="proveedor in obtenerProveedoresUnicos(item.colores_por_proveedor)"
      :key="proveedor"
      class="ma-1"
      size="small"
      color="secondary"
      variant="tonal"
    >
      {{ proveedor }}
    </v-chip>
  </div>
  <span v-else class="text-disabled">Sin proveedor</span>
</template>
  <!-- Costo 
  <template #item.costo="{ item }">
    <div v-if="item.colores_por_proveedor?.length">
      <div
        v-for="combo in item.colores_por_proveedor"
        :key="combo.color_id + '-' + combo.proveedor_id"
      >
        <strong>{{ combo.color?.nombre }}</strong> – {{ combo.proveedor?.nombre }}:<br />
        ${{ Number(combo.costo).toLocaleString() }}
      </div>
    </div>
    <span v-else class="text-disabled">Sin datos</span>
  </template>-->

  <!-- Acciones -->
  <template #item.acciones="{ item }">
    <v-btn icon color="warning" @click="editarProducto(item)">
      <v-icon>mdi-pencil</v-icon>
    </v-btn>
    <v-btn icon color="error" @click="eliminarProducto(item.id)">
      <v-icon>mdi-delete</v-icon>
    </v-btn>
  </template>
</v-data-table>
      </v-col>
    </v-row>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" :timeout="3000" top right>
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
  </template>
  
  <script setup>
  import { ref, reactive, onMounted } from 'vue'
  import axios from 'axios'

  axios.defaults.baseURL = 'http://localhost:8000'
  
  const productoActual = reactive({
    nombre: '',
    tipo: 'perfil',
    unidad_id: '',
    largo_total: '',
    peso_por_metro: ''
  })

  const obtenerProveedoresUnicos = (combinaciones) => {
  const nombres = combinaciones
    .map(combo => combo.proveedor?.nombre)
    .filter(Boolean) // elimina nulls o undefined
  return [...new Set(nombres)] // elimina duplicados
}
  
  const productos = ref([])
  const proveedores = ref([])
  const colores = ref([])
  const unidades = ref([])
  const combinacionesProveedorColor = ref([])
  const modoEdicion = ref(false)
  const productoSeleccionado = ref(null)
  const filtro = ref('')
  
  const snackbar = reactive({
    visible: false,
    text: '',
    color: 'success'
  })
  
  const tipos = ['perfil', 'vidrio', 'herraje', 'accesorio']
  
  const headers = [
    { title: 'Nombre', key: 'nombre' },
    { title: 'Tipo', key: 'tipo' },
    { title: 'Unidad', key: 'unidad.nombre' },
    //{ title: 'Colores', value: 'colores' },
    { title: 'Proveedor', value: 'proveedor' },
    //{ title: 'Costo', value: 'costo' },
    { title: 'Acciones', key: 'acciones', sortable: false }
  ]
  
  const showSnackbar = (message, color = 'success') => {
    snackbar.text = message
    snackbar.color = color
    snackbar.visible = true
  }
  
  const agregarCombinacion = () => {
    combinacionesProveedorColor.value.push({
      proveedor_id: '',
      color_id: '',
      costo: ''
    })
  }
  
  const eliminarCombinacion = (index) => {
    combinacionesProveedorColor.value.splice(index, 1)
  }
  
  const resetFormulario = () => {
    Object.assign(productoActual, {
      nombre: '',
      tipo: 'perfil',
      unidad_id: '',
      largo_total: '',
      peso_por_metro: ''
    })
    productoSeleccionado.value = null
    modoEdicion.value = false
    combinacionesProveedorColor.value = []
  }
  
  const handleSubmit = async () => {
    modoEdicion.value ? await actualizarProducto() : await crearProducto()
  }
  
  const crearProducto = async () => {
    try {
      await axios.post('/api/productos', {
        ...productoActual,
        producto_color_proveedor: combinacionesProveedorColor.value
      })
      showSnackbar('Producto agregado con éxito')
      cargarProductos()
      resetFormulario()
    } catch (error) {
      console.error(error)
      showSnackbar('Error al crear producto', 'error')
    }
  }
  
  const actualizarProducto = async () => {
    try {
      await axios.put(`/api/productos/${productoSeleccionado.value.id}`, {
        ...productoActual,
        producto_color_proveedor: combinacionesProveedorColor.value
      })
      showSnackbar('Producto actualizado')
      cargarProductos()
      resetFormulario()
    } catch (error) {
      console.error(error)
      showSnackbar('Error al actualizar producto', 'error')
    }
  }
  
  const eliminarProducto = async (id) => {
    try {
      await axios.delete(`/api/productos/${id}`)
      cargarProductos()
      showSnackbar('Producto eliminado')
    } catch (error) {
      console.error(error)
      showSnackbar('Error al eliminar producto', 'error')
    }
  }
  
  const editarProducto = (producto) => {
    modoEdicion.value = true
    productoSeleccionado.value = producto
  
    Object.assign(productoActual, {
      id: producto.id,
      nombre: producto.nombre,
      tipo: producto.tipo,
      unidad_id: producto.unidad_id || '',
      largo_total: producto.largo_total || '',
      peso_por_metro: producto.peso_por_metro || ''
    })
  
    combinacionesProveedorColor.value = (producto.colores_por_proveedor || []).map(c => ({
      proveedor_id: c.proveedor_id,
      color_id: c.color_id,
      costo: c.costo
    }))
  }
  
  const cancelarEdicion = () => resetFormulario()
  
  const cargarProductos = async () => {
  try {
    const { data } = await axios.get('/api/productos')
    console.log('🧪 Productos cargados:', data) // 👈 verifica aquí
    productos.value = data
  } catch (error) {
    console.error('Error cargando productos:', error)
  }
}
  
  const cargarColores = async () => {
    const res = await axios.get('/api/colores')
    colores.value = res.data
  }
  
  const cargarProveedores = async () => {
    const res = await axios.get('/api/proveedores')
    proveedores.value = res.data
  }
  
  const cargarUnidades = async () => {
    const res = await axios.get('/api/unidades')
    unidades.value = res.data
  }
  
  onMounted(() => {
    cargarColores()
    cargarProveedores()
    cargarProductos()
    cargarUnidades()
  })
  </script>
  
  