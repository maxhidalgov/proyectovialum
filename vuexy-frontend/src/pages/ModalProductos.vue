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
              <v-col cols="12" md="3">
                <div class="text-subtitle-2">{{ item.nombre }}</div>
                <div class="text-caption text-grey">{{ item.codigo }}</div>
                <v-chip v-if="item.esVidrio" size="x-small" color="info" class="mt-1">
                  Venta por m²
                </v-chip>
              </v-col>

              <v-col cols="6" md="2">
                <div class="text-caption text-grey">Color</div>
                <div class="text-subtitle-2">{{ item.color || '-' }}</div>
              </v-col>

              <!-- Si es vidrio (tipo 1 o 2), mostrar campos de dimensiones -->
              <template v-if="item.esVidrio">
                <v-col cols="12" md="12">
                  <v-divider class="my-2"></v-divider>
                  <v-row dense>
                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model.number="item.ancho_mm"
                        label="Ancho (mm)"
                        type="number"
                        outlined
                        dense
                        hide-details
                        @input="recalcularPrecioVidrio(item)"
                      />
                    </v-col>

                    <v-col cols="6" md="2">
                      <v-text-field
                        v-model.number="item.alto_mm"
                        label="Alto (mm)"
                        type="number"
                        outlined
                        dense
                        hide-details
                        @input="recalcularPrecioVidrio(item)"
                      />
                    </v-col>

                    <v-col cols="6" md="2">
                      <v-text-field
                        :key="`m2-${item.ancho_mm}-${item.alto_mm}`"
                        :model-value="calcularM2Display(item)"
                        label="m²"
                        readonly
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="text-primary font-weight-bold"
                      >
                        <template #append-inner>
                          <v-icon size="small" color="info">mdi-texture-box</v-icon>
                        </template>
                      </v-text-field>
                    </v-col>

                    <v-col cols="6" md="1">
                      <v-text-field
                        v-model.number="item.cantidad"
                        label="Cant."
                        type="number"
                        min="1"
                        outlined
                        dense
                        hide-details
                        @input="recalcularPrecioVidrio(item)"
                      />
                    </v-col>

                    <v-col cols="6" md="2">
                      <v-checkbox
                        v-model="item.pulido"
                        label="Pulido (+20%)"
                        color="primary"
                        hide-details
                        @change="recalcularPrecioVidrio(item)"
                      />
                    </v-col>

                    <v-col cols="6" md="1">
                      <div class="text-caption text-grey">P. Costo/m²</div>
                      <div class="text-subtitle-2">${{ formatearNumero(item.precio_costo_calculado || item.precio_costo) }}</div>
                    </v-col>

                    <v-col cols="6" md="2">
                      <div class="text-caption text-grey">P. Venta Total</div>
                      <div class="text-subtitle-2 text-success">${{ formatearNumero(item.precio_venta_total) }}</div>
                    </v-col>
                  </v-row>
                </v-col>
              </template>

              <!-- Si NO es vidrio, mostrar campos normales -->
              <template v-else>
                <v-col cols="6" md="1">
                  <v-text-field
                    v-model.number="item.cantidad"
                    label="Cant."
                    type="number"
                    min="1"
                    outlined
                    dense
                    hide-details
                    @input="calcularPrecio(item)"
                  />
                </v-col>

                <v-col cols="6" md="1">
                  <div class="text-caption text-grey">P. Costo</div>
                  <div class="text-subtitle-2">${{ formatearNumero(item.precio_costo) }}</div>
                </v-col>

                <v-col cols="4" md="1">
                  <div class="text-caption text-grey">Margen</div>
                  <div class="text-subtitle-2">{{ item.margen }}%</div>
                </v-col>

                <v-col cols="4" md="1">
                  <div class="text-caption text-grey">P. Venta</div>
                  <div class="text-subtitle-2 text-success">${{ formatearNumero(item.precio_venta) }}</div>
                </v-col>
              </template>

              <v-col :cols="item.esVidrio ? 12 : 4" :md="item.esVidrio ? 12 : 1" :class="item.esVidrio ? '' : 'text-right'">
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
  { title: 'Color', key: 'color', sortable: true },
  { title: 'Precio', key: 'precio', sortable: true },
  { title: 'Acciones', key: 'acciones', sortable: false, align: 'center' }
]

// Computed - Expandir productos por sus variantes de color (sin mostrar proveedor al cliente)
const productosFiltrados = computed(() => {
  if (!Array.isArray(productos.value)) return []
  
  const productosExpandidos = []
  
  productos.value.forEach(producto => {
    const listasPrecios = producto.listaPrecios || producto.lista_precios || []
    const listasActivas = listasPrecios.filter(lp => {
      return Number(lp.activo) === 1 || lp.activo === true || lp.activo === '1'
    })
    
    if (listasActivas.length === 0) {
      productosExpandidos.push({
        ...producto,
        _productoOriginal: producto,
        _listaPrecio: null,
        color: '-',
        precio: '-'
      })
    } else {
      listasActivas.forEach(lp => {
        // Obtener color directo o desde productoColorProveedor (compatibilidad)
        const color = lp.color || lp.productoColorProveedor?.color
        
        productosExpandidos.push({
          ...producto,
          _productoOriginal: producto,
          _listaPrecio: lp,
          id: `${producto.id}_${lp.id}`,
          color: color?.nombre || '-',
          precio: `$${Number(lp.precio_venta || 0).toLocaleString('es-CL')}`
        })
      })
    }
  })
  
  if (!busqueda.value) return productosExpandidos
  
  const busquedaLower = busqueda.value.toLowerCase()
  return productosExpandidos.filter(p => 
    p.nombre?.toLowerCase().includes(busquedaLower) ||
    p.codigo?.toLowerCase().includes(busquedaLower) ||
    p.color?.toLowerCase().includes(busquedaLower)
  )
})

const totalSeleccionados = computed(() => {
  return productosSeleccionados.value.reduce((sum, item) => {
    const precio = item.esVidrio ? (item.precio_venta_total || 0) : (item.precio_venta || 0)
    return sum + precio
  }, 0)
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
    const response = await api.get('/api/productos')
    let data = response.data
    
    if (typeof data === 'string') {
      try {
        data = JSON.parse(data)
      } catch (parseError) {
        console.error('Error al parsear JSON:', parseError)
        data = []
      }
    }
    
    productos.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error al cargar productos:', error)
    productos.value = []
  } finally {
    cargando.value = false
  }
}

const seleccionarProducto = (productoFila) => {
  const producto = productoFila._productoOriginal
  const listaPrecioActiva = productoFila._listaPrecio
  
  // Detectar si es vidrio (tipo_producto_id 1 o 2)
  const esVidrio = producto.tipo_producto_id === 1 || producto.tipo_producto_id === 2
  
  // Solo validar duplicados si NO es vidrio (los vidrios pueden repetirse con distintas medidas)
  if (!esVidrio) {
    const yaSeleccionado = productosSeleccionados.value.find(
      item => item.producto_lista_id === producto.id && item.lista_precio_id === listaPrecioActiva?.id
    )
    
    if (yaSeleccionado) {
      alert('Esta variante del producto ya fue seleccionada')
      return
    }
  }

  if (!listaPrecioActiva) {
    alert('Este producto no tiene precio configurado')
    return
  }
  
  const pcp = listaPrecioActiva.productoColorProveedor || listaPrecioActiva.producto_color_proveedor
  const color = listaPrecioActiva.color || pcp?.color
  const precioCosto = parseFloat(listaPrecioActiva.precio_costo) || 0
  const margenDefault = parseFloat(listaPrecioActiva.margen) || 30
  
  // Fórmula: Margen = (PrecioVenta - Costo) / PrecioVenta
  // Despejando: PrecioVenta = Costo / (1 - Margen/100)
  const precioVenta = margenDefault >= 100 ? 0 : precioCosto / (1 - margenDefault / 100)

  productosSeleccionados.value.push({
    producto: { ...producto },
    producto_lista_id: producto.id,
    lista_precio_id: listaPrecioActiva.id,
    cantidad: 1,
    precio_costo: precioCosto,
    margen: margenDefault,
    precio_venta: precioVenta,
    precio_venta_total: precioVenta, // Para vidrios, se recalculará
    descripcion: `${producto.nombre} - ${color?.nombre || 'Sin color'}`,
    codigo: producto.codigo,
    nombre: producto.nombre,
    color: color?.nombre || '-',
    proveedor: '-', // Oculto al cliente
    tipo: producto.tipo_producto?.nombre || '',
    unidad: producto.unidad?.nombre || producto.unidad?.simbolo || '',
    // Campos específicos para vidrios
    esVidrio: esVidrio,
    ancho_mm: esVidrio ? null : undefined,
    alto_mm: esVidrio ? null : undefined,
    m2: esVidrio ? 0 : undefined,
    pulido: esVidrio ? false : undefined,
    precio_costo_calculado: esVidrio ? precioCosto : undefined
  })
}

const eliminarProductoSeleccionado = (index) => {
  productosSeleccionados.value.splice(index, 1)
}

const calcularPrecio = (item) => {
  const costo = parseFloat(item.precio_costo) || 0
  const cantidad = parseFloat(item.cantidad) || 1
  const margen = parseFloat(item.margen) || 0
  
  // Fórmula: Margen = (PrecioVenta - Costo) / PrecioVenta
  // Despejando: PrecioVenta = Costo / (1 - Margen/100)
  if (margen >= 100) {
    item.precio_venta = 0
    return
  }
  
  const precioUnitario = costo / (1 - margen / 100)
  item.precio_venta = precioUnitario * cantidad
}

// Función para calcular m² desde dimensiones en mm
const calcularM2 = (item) => {
  if (!item.esVidrio || !item.ancho_mm || !item.alto_mm) return 0
  
  const m2 = (item.ancho_mm * item.alto_mm) / 1000000
  item.m2 = m2
  return m2
}

// Función para mostrar m² formateado
const calcularM2Display = (item) => {
  const m2 = calcularM2(item)
  return m2 > 0 ? m2.toFixed(4) : '0.0000'
}

// Función para recalcular precio cuando cambian las dimensiones del vidrio
const recalcularPrecioVidrio = (item) => {
  if (!item.esVidrio) return
  
  const m2 = calcularM2(item)
  
  if (m2 <= 0) {
    item.precio_venta_total = 0
    item.precio_costo_calculado = item.precio_costo
    return
  }
  
  // Aplicar costo de pulido si está activado (+20%)
  let costoFinal = item.precio_costo
  if (item.pulido) {
    costoFinal = item.precio_costo * 1.20
  }
  item.precio_costo_calculado = costoFinal
  
  const margen = parseFloat(item.margen) || 0
  
  // Evitar división por cero si margen >= 100
  if (margen >= 100) {
    item.precio_venta_total = 0
    item.precio_venta = 0
    return
  }
  
  // Fórmula: Margen = (PrecioVenta - Costo) / PrecioVenta
  // Despejando: PrecioVenta = Costo / (1 - Margen/100)
  const precioBase = costoFinal * m2
  const precioConMargen = precioBase / (1 - margen / 100)
  const cantidad = parseFloat(item.cantidad) || 1
  
  // 1. Aplicar IVA (19%) al precio con margen
  const precioConIVA = precioConMargen * 1.19
  
  // 2. Redondear hacia arriba al múltiplo de 500
  const precioRedondeadoConIVA = redondearA500HaciaArriba(precioConIVA)
  
  // 3. Reversar el IVA para mostrar el precio sin IVA en el modal
  const precioSinIVA = precioRedondeadoConIVA / 1.19
  
  // 4. Guardar el precio sin IVA (en el PDF se aplicará el 19% de nuevo)
  item.precio_venta_total = precioSinIVA * cantidad
  item.precio_venta = item.precio_venta_total // Para compatibilidad
}

// Función para redondear hacia arriba al múltiplo de 500 más cercano
const redondearA500HaciaArriba = (precio) => {
  return Math.ceil(precio / 500) * 500
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
  // Validar que los productos tipo vidrio tengan dimensiones
  const vidriosSinDimensiones = productosSeleccionados.value.filter(
    item => item.esVidrio && (!item.ancho_mm || !item.alto_mm || item.ancho_mm <= 0 || item.alto_mm <= 0)
  )
  
  if (vidriosSinDimensiones.length > 0) {
    alert('Por favor, ingresa las dimensiones (ancho y alto) para todos los productos de vidrio')
    return
  }
  
  // Preparar productos para enviar
  const productosParaEnviar = productosSeleccionados.value.map(item => {
    if (item.esVidrio) {
      const pulidoTexto = item.pulido ? ' [PULIDO]' : ''
      return {
        ...item,
        descripcion: `${item.nombre} - ${item.ancho_mm}mm x ${item.alto_mm}mm (${calcularM2Display(item)} m²)${pulidoTexto} - ${item.color}`,
        precio_venta: item.precio_venta_total,
        m2: calcularM2(item)
      }
    }
    return item
  })
  
  emit('agregar-productos', productosParaEnviar)
  modalProductos.value = false
}
</script>

<style scoped>
.text-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
