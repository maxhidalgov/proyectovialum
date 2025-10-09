<template>
  <v-dialog v-model="modalProductos" max-width="1000px" persistent>
    <v-card>
      <v-card-title class="text-h6 d-flex justify-space-between align-center">
        <span>Agregar Productos</span>
        <v-btn icon="mdi-close" variant="text" @click="cerrarModal"></v-btn>
      </v-card-title>
      
      <v-card-text>
        <v-row dense>
          <!-- Buscador de productos -->
          <v-col cols="12">
            <v-text-field
              v-model="busqueda"
              label="Buscar producto por nombre o código"
              prepend-inner-icon="mdi-magnify"
              clearable
              outlined
              dense
              hide-details="auto"
            />
          </v-col>
        </v-row>

        <!-- Tabla de productos -->
        <v-data-table
          :headers="headers"
          :items="productosFiltrados"
          :loading="cargando"
          item-value="id"
          class="elevation-1 mt-4"
          density="compact"
          :items-per-page="10"
          :items-per-page-options="[5, 10, 25, 50]"
        >
          <template #item.tipo_producto="{ item }">
            {{ item.tipoProducto?.nombre || '-' }}
          </template>

          <template #item.unidad="{ item }">
            {{ item.unidad?.abreviacion || '-' }}
          </template>

          <template #item.acciones="{ item }">
            <v-btn
              icon="mdi-plus"
              size="small"
              color="success"
              variant="tonal"
              @click="seleccionarProducto(item)"
            ></v-btn>
          </template>
        </v-data-table>

        <v-divider class="my-4"></v-divider>

        <!-- Productos seleccionados -->
        <div v-if="productosSeleccionados.length > 0">
          <h3 class="text-subtitle-1 mb-3">Productos seleccionados ({{ productosSeleccionados.length }})</h3>
          
          <v-card
            v-for="(item, index) in productosSeleccionados"
            :key="index"
            class="mb-3 pa-3"
            outlined
          >
            <v-row dense align="center">
              <v-col cols="12" md="4">
                <div class="text-subtitle-2">{{ item.producto.nombre }}</div>
                <div class="text-caption text-grey">{{ item.producto.codigo_proveedor }}</div>
              </v-col>

              <v-col cols="6" md="2">
                <v-text-field
                  v-model.number="item.cantidad"
                  label="Cantidad"
                  type="number"
                  min="1"
                  outlined
                  dense
                  hide-details
                  @input="calcularPrecio(item)"
                />
              </v-col>

              <v-col cols="6" md="2">
                <div class="text-caption text-grey">Precio Costo</div>
                <div class="text-subtitle-2">${{ formatearNumero(item.precio_costo) }}</div>
              </v-col>

              <v-col cols="6" md="2">
                <div class="text-caption text-grey">Margen</div>
                <div class="text-subtitle-2">{{ item.margen }}%</div>
              </v-col>

              <v-col cols="4" md="1">
                <div class="text-caption text-grey">Precio Venta</div>
                <div class="text-subtitle-2 text-success">${{ formatearNumero(item.precio_venta) }}</div>
              </v-col>

              <v-col cols="2" md="1" class="text-right">
                <v-btn
                  icon="mdi-delete"
                  size="small"
                  color="error"
                  variant="text"
                  @click="eliminarProductoSeleccionado(index)"
                ></v-btn>
              </v-col>
            </v-row>
          </v-card>

          <!-- Total -->
          <v-card class="pa-3 bg-primary-lighten-5" outlined>
            <v-row>
              <v-col cols="12" class="text-right">
                <div class="text-subtitle-2 text-grey">Total productos seleccionados:</div>
                <div class="text-h6 text-primary">${{ formatearNumero(totalSeleccionados) }}</div>
              </v-col>
            </v-row>
          </v-card>
        </div>

        <v-alert v-else type="info" variant="tonal" class="mt-4">
          Busca y selecciona productos de la tabla para agregarlos a la cotización
        </v-alert>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn color="grey" variant="text" @click="cerrarModal">
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          :disabled="productosSeleccionados.length === 0"
          @click="agregarProductos"
        >
          Agregar {{ productosSeleccionados.length }} producto(s)
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/axiosInstance'

// Props
const props = defineProps({
  mostrar: {
    type: Boolean,
    default: false
  }
})

// Emits
const emit = defineEmits(['update:mostrar', 'agregar-productos'])

// State
const modalProductos = ref(false)
const productos = ref([])
const productosSeleccionados = ref([])
const busqueda = ref('')
const cargando = ref(false)

// Headers de la tabla
const headers = [
  { title: 'Código', key: 'codigo_proveedor', sortable: true },
  { title: 'Nombre', key: 'nombre', sortable: true },
  { title: 'Tipo', key: 'tipo_producto', sortable: true },
  { title: 'Unidad', key: 'unidad', sortable: true },
  { title: 'Acciones', key: 'acciones', sortable: false, align: 'center' }
]

// Computed
const productosFiltrados = computed(() => {
  if (!Array.isArray(productos.value)) return []
  if (!busqueda.value) return productos.value
  
  const termino = busqueda.value.toLowerCase()
  return productos.value.filter(p => {
    if (!p) return false
    const nombre = p.nombre || ''
    const codigo = p.codigo_proveedor || ''
    return nombre.toLowerCase().includes(termino) || codigo.toLowerCase().includes(termino)
  })
})

const totalSeleccionados = computed(() => {
  return productosSeleccionados.value.reduce((sum, item) => sum + item.precio_venta, 0)
})

// Watch
watch(() => props.mostrar, (valor) => {
  modalProductos.value = valor
  if (valor) {
    cargarProductos()
  }
})

watch(modalProductos, (valor) => {
  emit('update:mostrar', valor)
  if (!valor) {
    limpiarSeleccion()
  }
})

// Methods
const cargarProductos = async () => {
  cargando.value = true
  try {
    console.log('🔍 Cargando productos desde /api/productos...')
    console.log('🌐 Using API instance with baseURL')
    const response = await api.get('/api/productos')
    console.log('✅ Respuesta recibida:', response)
    console.log('📦 Data type:', typeof response.data)
    console.log('📦 Data content:', response.data)
    
    // Si la respuesta es un string, intentar parsearlo
    let data = response.data
    if (typeof data === 'string') {
      console.log('⚠️ Respuesta es string, intentando parsear...')
      console.log('📝 Primeros 500 caracteres:', data.substring(0, 500))
      try {
        data = JSON.parse(data)
      } catch (parseError) {
        console.error('❌ No se pudo parsear como JSON:', parseError)
        throw new Error('La respuesta no es JSON válido')
      }
    }
    
    if (Array.isArray(data)) {
      productos.value = data
      console.log(`✅ ${productos.value.length} productos cargados`)
    } else {
      console.warn('⚠️ La respuesta no es un array:', typeof data)
      console.log('Contenido:', data)
      productos.value = []
    }
  } catch (error) {
    console.error('❌ Error al cargar productos:', error)
    console.error('❌ Error completo:', error.response)
    productos.value = []
    
    // Mostrar alerta al usuario
    alert('Error al cargar productos. Por favor, verifica que el servidor esté corriendo.')
  } finally {
    cargando.value = false
  }
}

const seleccionarProducto = (producto) => {
  // Verificar si ya está seleccionado
  const yaSeleccionado = productosSeleccionados.value.find(
    item => item.producto.id === producto.id
  )
  
  if (yaSeleccionado) {
    alert('Este producto ya fue seleccionado')
    return
  }

  // Buscar lista de precios activa del producto
  console.log('🔍 Producto seleccionado:', producto)
  console.log('📋 Lista precios disponibles:', producto.listaPrecios || producto.lista_precios)
  
  const listaPrecioActiva = (producto.listaPrecios || producto.lista_precios)?.find(lp => lp.activo === 1 || lp.activo === true) || null
  
  console.log('✅ Lista precio activa encontrada:', listaPrecioActiva)
  
  const precioCosto = listaPrecioActiva ? parseFloat(listaPrecioActiva.precio_costo) : 0
  const margenDefault = listaPrecioActiva ? parseFloat(listaPrecioActiva.margen) : 30
  const precioVenta = precioCosto * (1 + margenDefault / 100)
  
  console.log('💰 Precio costo:', precioCosto)
  console.log('📊 Margen:', margenDefault)
  console.log('💵 Precio venta:', precioVenta)

  productosSeleccionados.value.push({
    producto: { ...producto },
    producto_lista_id: producto.id, // ✅ ID del producto
    lista_precio_id: listaPrecioActiva?.id || null, // ✅ ID de la lista de precios
    cantidad: 1,
    precio_costo: precioCosto,
    margen: margenDefault,
    precio_venta: precioVenta,
    // Información adicional para mostrar
    codigo: producto.codigo,
    nombre: producto.nombre,
    tipo: producto.tipo_producto?.nombre || '',
    unidad: producto.unidad?.nombre || producto.unidad?.simbolo || ''
  })
}

const eliminarProductoSeleccionado = (index) => {
  productosSeleccionados.value.splice(index, 1)
}

const calcularPrecio = (item) => {
  const costo = parseFloat(item.precio_costo) || 0
  const cantidad = parseFloat(item.cantidad) || 1
  const margen = parseFloat(item.margen) || 0
  
  const precioUnitario = costo * (1 + margen / 100)
  item.precio_venta = precioUnitario * cantidad
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL').format(numero || 0)
}

const limpiarSeleccion = () => {
  productosSeleccionados.value = []
  busqueda.value = ''
}

const cerrarModal = () => {
  modalProductos.value = false
}

const agregarProductos = () => {
  emit('agregar-productos', [...productosSeleccionados.value])
  modalProductos.value = false
}
</script>

<style scoped>
.text-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
