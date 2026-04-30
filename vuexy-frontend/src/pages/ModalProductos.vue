<template>
  <v-dialog v-model="modalProductos" max-width="1000px" persistent scrollable>
    <v-card>
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pa-4 bg-surface">
        <div class="d-flex align-center gap-2">
          <v-icon color="primary" class="mr-2">mdi-package-variant-plus</v-icon>
          <span class="text-h6 font-weight-bold">Agregar Productos</span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="cerrarModal" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-4">
        <!-- Buscador -->
        <v-text-field
          v-model="busqueda"
          label="Buscar producto por nombre o código"
          prepend-inner-icon="mdi-magnify"
          clearable
          variant="outlined"
          density="compact"
          hide-details
          class="mb-4"
          bg-color="surface"
        />

        <!-- Tabla de productos -->
        <v-data-table
          :headers="headers"
          :items="productosFiltrados"
          :loading="cargando"
          item-value="id"
          density="compact"
          :items-per-page="10"
          :items-per-page-options="[5, 10, 25, 50]"
          class="rounded-lg border"
          hover
        >
          <template #item.nombre="{ item }">
            <div class="py-1">
              <div class="text-body-2 font-weight-medium">{{ item.nombre }}</div>
              <div v-if="item.codigo" class="text-caption text-medium-emphasis">{{ item.codigo }}</div>
            </div>
          </template>

          <template #item.color="{ item }">
            <v-chip v-if="item.color && item.color !== '-'" size="x-small" variant="tonal" color="secondary">
              {{ item.color }}
            </v-chip>
            <span v-else class="text-caption text-disabled">—</span>
          </template>

          <template #item.precio="{ item }">
            <span class="text-body-2 font-weight-medium text-success">{{ item.precio }}</span>
          </template>

          <template #item.acciones="{ item }">
            <v-btn
              icon="mdi-plus"
              size="small"
              color="primary"
              variant="tonal"
              @click="seleccionarProducto(item)"
            />
          </template>

          <template #no-data>
            <div class="text-center py-6 text-medium-emphasis">
              <v-icon size="40" class="mb-2">mdi-package-variant-remove</v-icon>
              <div>No hay productos con precios configurados</div>
            </div>
          </template>
        </v-data-table>

        <!-- Productos seleccionados -->
        <template v-if="productosSeleccionados.length > 0">
          <div class="d-flex align-center mt-5 mb-3">
            <v-icon color="primary" size="small" class="mr-2">mdi-cart-check</v-icon>
            <span class="text-subtitle-2 font-weight-bold">
              Productos seleccionados
            </span>
            <v-chip size="x-small" color="primary" variant="tonal" class="ml-2">
              {{ productosSeleccionados.length }}
            </v-chip>
          </div>

          <v-card
            v-for="(item, index) in productosSeleccionados"
            :key="index"
            class="mb-3"
            variant="outlined"
          >
            <!-- Cabecera del producto -->
            <div class="d-flex align-center justify-space-between px-4 pt-3 pb-2">
              <div class="d-flex align-center gap-3 flex-wrap">
                <div>
                  <div class="text-body-1 font-weight-semibold">{{ item.nombre }}</div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <v-chip v-if="item.codigo" size="x-small" variant="tonal" color="secondary">
                      {{ item.codigo }}
                    </v-chip>
                    <v-chip v-if="item.color && item.color !== '-'" size="x-small" variant="tonal" color="info">
                      {{ item.color }}
                    </v-chip>
                    <v-chip v-if="item.esVidrio" size="x-small" color="primary" variant="tonal">
                      <v-icon start size="10">mdi-texture-box</v-icon>
                      Venta por m²
                    </v-chip>
                  </div>
                </div>
              </div>
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                color="error"
                variant="text"
                @click="eliminarProductoSeleccionado(index)"
              />
            </div>

            <v-divider />

            <div class="px-4 py-3">
              <!-- VIDRIO: dimensiones en 2 filas -->
              <template v-if="item.esVidrio">
                <!-- Fila 1: dimensiones -->
                <v-row dense class="mb-2">
                  <v-col cols="6" sm="3">
                    <v-text-field
                      v-model.number="item.ancho_mm"
                      label="Ancho (mm)"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      @update:modelValue="recalcularPrecioVidrio(item)"
                    />
                  </v-col>
                  <v-col cols="6" sm="3">
                    <v-text-field
                      v-model.number="item.alto_mm"
                      label="Alto (mm)"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      @update:modelValue="recalcularPrecioVidrio(item)"
                    />
                  </v-col>
                  <v-col cols="6" sm="2">
                    <v-text-field
                      :key="`m2-${item.ancho_mm}-${item.alto_mm}`"
                      :model-value="calcularM2Display(item)"
                      label="m²"
                      readonly
                      variant="outlined"
                      density="compact"
                      hide-details
                      bg-color="primary-darken-4"
                    >
                      <template #append-inner>
                        <v-icon size="small" color="primary">mdi-texture-box</v-icon>
                      </template>
                    </v-text-field>
                  </v-col>
                  <v-col cols="6" sm="2">
                    <v-text-field
                      v-model.number="item.cantidad"
                      label="Cant."
                      type="number"
                      min="1"
                      variant="outlined"
                      density="compact"
                      hide-details
                      @update:modelValue="recalcularPrecioVidrio(item)"
                    />
                  </v-col>
                  <v-col cols="12" sm="2" class="d-flex align-center">
                    <v-checkbox
                      v-model="item.pulido"
                      label="Pulido (+20%)"
                      color="primary"
                      density="compact"
                      hide-details
                      @update:modelValue="(val) => { item.pulido = val; recalcularPrecioVidrio(item) }"
                    />
                  </v-col>
                </v-row>

                <!-- Fila 2: precios resultantes -->
                <v-row dense>
                  <v-col cols="6" sm="3">
                    <div class="rounded-lg pa-2 text-center" style="background: rgba(var(--v-theme-info), 0.1)">
                      <div class="text-caption text-medium-emphasis mb-1">P. Costo/m²</div>
                      <div class="text-body-2 font-weight-bold text-info">${{ formatearNumero(item.precio_costo_calculado || item.precio_costo) }}</div>
                    </div>
                  </v-col>
                  <v-col cols="6" sm="3">
                    <div class="rounded-lg pa-2 text-center" style="background: rgba(var(--v-theme-success), 0.1)">
                      <div class="text-caption text-medium-emphasis mb-1">P. Venta Total</div>
                      <div class="text-body-1 font-weight-bold text-success">${{ formatearNumero(item.precio_venta_total) }}</div>
                    </div>
                  </v-col>
                </v-row>
              </template>

              <!-- NO VIDRIO: cantidad y precio en una fila -->
              <template v-else>
                <v-row dense align="center">
                  <v-col cols="6" sm="2">
                    <v-text-field
                      v-model.number="item.cantidad"
                      label="Cant."
                      type="number"
                      min="1"
                      variant="outlined"
                      density="compact"
                      hide-details
                      @update:modelValue="calcularPrecio(item)"
                    />
                  </v-col>
                  <v-col cols="6" sm="2">
                    <div class="rounded-lg pa-2 text-center" style="background: rgba(var(--v-theme-info), 0.1)">
                      <div class="text-caption text-medium-emphasis mb-1">Margen</div>
                      <div class="text-body-2 font-weight-bold text-info">{{ item.margen }}%</div>
                    </div>
                  </v-col>
                  <v-col cols="6" sm="3">
                    <div class="rounded-lg pa-2 text-center" style="background: rgba(var(--v-theme-success), 0.1)">
                      <div class="text-caption text-medium-emphasis mb-1">P. Venta</div>
                      <div class="text-body-1 font-weight-bold text-success">${{ formatearNumero(item.precio_venta * item.cantidad) }}</div>
                    </div>
                  </v-col>
                </v-row>
              </template>
            </div>
          </v-card>

          <!-- Total -->
          <div class="d-flex justify-end align-center mt-2 px-1">
            <span class="text-body-2 text-medium-emphasis mr-3">Total productos seleccionados:</span>
            <span class="text-h6 font-weight-bold text-primary">${{ formatearNumero(totalSeleccionados) }}</span>
          </div>
        </template>

        <v-alert v-else type="info" variant="tonal" class="mt-4" density="compact">
          Busca y selecciona productos de la tabla para agregarlos a la cotización
        </v-alert>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-3">
        <v-spacer />
        <v-btn color="grey" variant="text" @click="cerrarModal">Cancelar</v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          :disabled="productosSeleccionados.length === 0"
          prepend-icon="mdi-check"
          @click="agregarProductos"
        >
          Agregar {{ productosSeleccionados.length > 0 ? productosSeleccionados.length : '' }} producto{{ productosSeleccionados.length !== 1 ? 's' : '' }}
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
  { title: 'Nombre', key: 'nombre', sortable: true },
  { title: 'Color', key: 'color', sortable: true },
  { title: 'Precio', key: 'precio', sortable: true },
  { title: '', key: 'acciones', sortable: false, align: 'end', width: '60px' }
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
    
    if (listasActivas.length > 0) {
      listasActivas.forEach(lp => {
        // Obtener color directo o desde productoColorProveedor (compatibilidad)
        const color = lp.color || lp.producto_color_proveedor?.color || lp.productoColorProveedor?.color
        
        productosExpandidos.push({
          ...producto,
          _productoOriginal: producto,
          _listaPrecio: lp,
          id: `${producto.id}_${lp.id}`,
          color: color?.nombre || '-',
          precio: `$${new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(lp.precio_venta || 0))}`
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
    const precio = item.esVidrio
      ? (item.precio_venta_total || 0)
      : (item.precio_venta || 0) * (item.cantidad || 1)
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
  
  const pcp = listaPrecioActiva.producto_color_proveedor || listaPrecioActiva.productoColorProveedor
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
  const margen = parseFloat(item.margen) || 0
  if (margen >= 100) { item.precio_venta = 0; return }
  item.precio_venta = costo / (1 - margen / 100)
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
const calcularPrecioTotal = (costo, m2, margen, cantidad) => {
  // PrecioVenta = Costo / (1 - Margen/100)
  const precioBase = costo * m2
  const precioConMargen = precioBase / (1 - margen / 100)
  const precioConIVA = precioConMargen * 1.19
  const precioRedondeado = redondearA500HaciaArriba(precioConIVA)
  const precioSinIVA = precioRedondeado / 1.19
  return precioSinIVA * cantidad
}

const recalcularPrecioVidrio = (item) => {
  if (!item.esVidrio) return
  
  const m2 = calcularM2(item)
  
  if (m2 <= 0) {
    item.precio_venta_total = 0
    item.precio_costo_calculado = item.precio_costo
    return
  }
  
  const margen = parseFloat(item.margen) || 0
  const cantidad = parseFloat(item.cantidad) || 1
  
  if (margen >= 100) {
    item.precio_venta_total = 0
    item.precio_venta = 0
    return
  }
  
  // Precio sin pulido
  const totalSinPulido = calcularPrecioTotal(item.precio_costo, m2, margen, cantidad)
  
  if (!item.pulido) {
    item.precio_costo_calculado = item.precio_costo
    item.precio_venta_total = totalSinPulido
    item.precio_venta = totalSinPulido
    return
  }
  
  // Precio con pulido (+20% en costo)
  const costoConPulido = item.precio_costo * 1.20
  item.precio_costo_calculado = costoConPulido
  let totalConPulido = calcularPrecioTotal(costoConPulido, m2, margen, cantidad)
  
  // Mínimo $1.000 de surcharge por pulido
  if (totalConPulido - totalSinPulido < 1000) {
    totalConPulido = totalSinPulido + 1000
  }
  
  item.precio_venta_total = totalConPulido
  item.precio_venta = totalConPulido
}

// Función para redondear hacia arriba al múltiplo de 500 más cercano
const redondearA500HaciaArriba = (precio) => {
  return Math.ceil(precio / 500) * 500
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(numero) || 0)
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
