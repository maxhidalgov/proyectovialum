<template>
  <v-container fluid>
    <v-row justify="center">
      <v-col cols="12" sm="11" md="10" lg="8">
        <v-card elevation="2" class="pa-6">

          <v-card-title class="text-h5 text-center mb-2">
            {{ modoEdicion ? 'Editar Producto' : 'Agregar Producto' }}
          </v-card-title>

          <v-card-text>
            <v-card-subtitle class="text-subtitle-1 font-weight-medium mb-2">
              Datos del producto
            </v-card-subtitle>

            <v-form @submit.prevent="handleSubmit" ref="formRef">
              <v-row class="gap-y-6 gap-x-4">
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="productoActual.nombre"
                    label="Nombre del producto"
                    prepend-inner-icon="mdi-label-outline"
                    outlined
                    color="primary"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-select
                    v-model="productoActual.tipo_producto_id"
                    :items="tiposProducto"
                    item-title="nombre"
                    item-value="id"
                    label="Tipo de producto"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-select
                    v-model="productoActual.unidad_id"
                    :items="unidades"
                    item-title="nombre"
                    item-value="id"
                    label="Unidad de medida"
                    outlined
                    color="primary"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="productoActual.largo_total"
                    label="Largo total (m)"
                    type="number"
                    step="0.01"
                    outlined
                    color="primary"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="productoActual.peso_por_metro"
                    label="Peso por metro (kg)"
                    type="number"
                    step="0.01"
                    outlined
                    color="primary"
                  />
                </v-col>
              </v-row>

              <v-divider class="my-6" />

              <v-card-subtitle class="text-subtitle-1 font-weight-medium mb-2">
                Combinaciones proveedor + color + costo
              </v-card-subtitle>

              <v-row
                v-for="(combo, index) in combinacionesProveedorColor"
                :key="index"
                
              >
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="combo.proveedor_id"
                    :items="proveedores"
                    item-title="nombre"
                    item-value="id"
                    label="Proveedor"
                    outlined
                    color="primary"
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="combo.color_id"
                    :items="colores"
                    item-title="nombre"
                    item-value="id"
                    label="Color"
                    outlined
                    color="primary"
                  />
                </v-col>


                <v-col cols="12" sm="4">
                  <v-text-field
                  v-model="combo.codigo_proveedor" label="Código proveedor" />
                </v-col>
                
                <v-col cols="10" sm="3">
                  <v-text-field
                    v-model="combo.costo"
                    label="Costo"
                    type="number"
                    step="0.01"
                    outlined
                    color="primary"
                  />
                </v-col>
                <v-col cols="12" sm="1" class="h-100 d-flex align-center justify-center">
                  <v-btn icon @click="eliminarCombinacion(index)" color="error" size="small">
                    <v-icon>mdi-delete</v-icon>
                  </v-btn>
                </v-col>
              </v-row>

              <v-btn variant="text" color="primary" @click="agregarCombinacion" class="mb-6">
                + Agregar combinación
              </v-btn>

              <v-btn block color="primary" type="submit" class="mb-2">
                {{ modoEdicion ? 'Actualizar Producto' : 'Agregar Producto' }}
              </v-btn>
              <v-btn block v-if="modoEdicion" color="secondary" @click="cancelarEdicion">
                Cancelar
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

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
 import api from '@/axiosInstance'

 
  const productoActual = reactive({
    nombre: '',
  //  tipo: '',
    unidad_id: '',
    largo_total: '',
    peso_por_metro: '',
    codigo_proveedor: ''
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
  const tiposProducto = ref([])
  const codigo_proveedor = ref('')
  
  const snackbar = reactive({
    visible: false,
    text: '',
    color: 'success'
  })
  
  //const tipos = ['perfil', 'vidrio', 'herraje', 'accesorio']
  
  const headers = [
    { title: 'Nombre', key: 'nombre' },
    { title: 'Tipo', key: 'tipo_producto.nombre' },
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
      codigo_proveedor: '',
      costo: ''
    })
  }

  
  const eliminarCombinacion = (index) => {
    combinacionesProveedorColor.value.splice(index, 1)
  }
  
  const resetFormulario = () => {
    Object.assign(productoActual, {
      nombre: '',
      //tipo: '',
      tipo_producto_id: '', // 👈 aquí lo agregas
      unidad_id: '',
      largo_total: '',
      peso_por_metro: '',
      codigo_proveedor: '',
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
      await api.post('api/productos', {
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
      await api.put(`api/productos/${productoActual.id}`, {
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
      await api.delete(`api/productos/${id}`)
      cargarProductos()
      showSnackbar('Producto eliminado')
    } catch (error) {
      console.error(error)
      showSnackbar('Error al eliminar producto', 'error')
    }
  }
  
  const editarProducto = (item) => {
  productoSeleccionado.value = item
  modoEdicion.value = true

  Object.assign(productoActual, {
    id: item.id, // ✅ Este campo es esencial para la actualización
    nombre: item.nombre,
    //tipo: item.tipo,
    tipo_producto_id: item.tipo_producto_id,
    unidad_id: item.unidad_id,
    largo_total: item.largo_total,
    peso_por_metro: item.peso_por_metro,
    codigo_proveedor: item.codigo_proveedor,
  })

  // Si también estás cargando combinaciones
  combinacionesProveedorColor.value = item.colores_por_proveedor.map(combo => ({
    proveedor_id: combo.proveedor_id,
    color_id: combo.color_id,
    costo: combo.costo,
    codigo_proveedor: combo.codigo_proveedor ?? ''
  }))
}
  
  const cancelarEdicion = () => resetFormulario()
  
  const cargarProductos = async () => {
  try {
    const { data } = await api.get('api/productos')
    console.log('🧪 Productos cargados:', data) // 👈 verifica aquí
    productos.value = data
  } catch (error) {
    console.error('Error cargando productos:', error)
  }
}
  
  const cargarColores = async () => {
    const res = await api.get('api/colores')
    colores.value = res.data
  }
  
  const cargarProveedores = async () => {
    const res = await api.get('api/proveedores')
    proveedores.value = res.data
  }
  
  const cargarUnidades = async () => {
    const res = await api.get('api/unidades')
    unidades.value = res.data
  }
  const cargarTiposProducto = async () => {
  try {
    const { data } = await api.get('api/tipos_producto')
    tiposProducto.value = data
  } catch (error) {
    console.error('Error al cargar tipos de producto:', error)
  }
}
  
  onMounted(() => {
    cargarColores()
    cargarProveedores()
    cargarProductos()
    cargarUnidades()
    cargarTiposProducto()
    
  })
  </script>
  