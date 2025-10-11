<template>
  <v-dialog v-model="dialogVisible" max-width="700px" persistent @click:outside="cerrar">
    <v-card>
      <v-card-title class="text-h6 bg-primary">
        {{ modoEdicion ? 'Editar Precio' : 'Nuevo Precio' }}
      </v-card-title>

      <v-card-text class="pt-4">
        <v-form ref="form">
          <v-row>
            <!-- Selector de Producto -->
            <v-col cols="12">
              <v-autocomplete
                v-model="formulario.producto_id"
                :items="productos"
                item-title="nombre"
                item-value="id"
                label="Producto *"
                prepend-inner-icon="mdi-package-variant"
                variant="outlined"
                density="compact"
                clearable
                :rules="[v => !!v || 'El producto es requerido']"
                @update:model-value="onProductoChange"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #title>
                      {{ item.raw.nombre }}
                    </template>
                    <template #subtitle>
                      {{ item.raw.tipoProducto?.nombre }}
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Selector de Color -->
            <v-col cols="12">
              <v-autocomplete
                v-model="formulario.color_id"
                :items="coloresDisponibles"
                item-title="nombre"
                item-value="id"
                label="Color *"
                prepend-inner-icon="mdi-palette"
                variant="outlined"
                density="compact"
                :disabled="!formulario.producto_id"
                clearable
                :rules="[v => !!v || 'Debes seleccionar un color']"
                @update:model-value="onColorChange"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #title>
                      {{ item.raw.nombre }}
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Precio Costo (calculado automáticamente) -->
            <v-col cols="12" md="4">
              <v-text-field
                :model-value="formulario.precio_costo"
                label="Precio Costo (Máximo)"
                type="number"
                prefix="$"
                variant="outlined"
                density="compact"
                readonly
                bg-color="grey-lighten-4"
                :hint="proveedorHint"
                persistent-hint
              />
            </v-col>

            <!-- Margen -->
            <v-col cols="12" md="4">
              <v-text-field
                v-model.number="formulario.margen"
                label="Margen % *"
                type="number"
                suffix="%"
                variant="outlined"
                density="compact"
                :rules="[
                  v => v !== null && v !== undefined && v !== '' || 'El margen es requerido',
                  v => v >= 0 || 'Debe ser mayor o igual a 0',
                  v => v <= 100 || 'No puede ser mayor a 100'
                ]"
              />
            </v-col>

            <!-- Precio Venta (calculado) -->
            <v-col cols="12" md="4">
              <v-text-field
                :model-value="precioVentaCalculado"
                label="Precio Venta"
                prefix="$"
                variant="outlined"
                density="compact"
                readonly
                bg-color="grey-lighten-4"
              />
            </v-col>

            <!-- Fechas de Vigencia -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.vigencia_desde"
                label="Vigencia Desde"
                type="date"
                variant="outlined"
                density="compact"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.vigencia_hasta"
                label="Vigencia Hasta"
                type="date"
                variant="outlined"
                density="compact"
              />
            </v-col>

            <!-- Estado Activo -->
            <v-col cols="12">
              <v-switch
                v-model="formulario.activo"
                label="Precio Activo"
                color="success"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="cerrar"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          :loading="guardando"
          @click="guardar"
        >
          {{ modoEdicion ? 'Actualizar' : 'Guardar' }}
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
  modelValue: {
    type: Boolean,
    default: false
  },
  precio: {
    type: Object,
    default: null
  },
  modoEdicion: {
    type: Boolean,
    default: false
  }
})

// Emits
const emit = defineEmits(['update:modelValue', 'guardado'])

// State
const productos = ref([])
const coloresDisponibles = ref([])
const guardando = ref(false)
const form = ref(null)
const proveedorHint = ref('')

const formulario = ref({
  id: null,
  producto_id: null,
  color_id: null,
  precio_costo: 0,
  margen: 45,
  vigencia_desde: new Date().toISOString().split('T')[0],
  vigencia_hasta: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  activo: true
})

// Computed
const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const precioVentaCalculado = computed(() => {
  const costo = parseFloat(formulario.value.precio_costo) || 0
  const margen = parseFloat(formulario.value.margen) || 0
  
  // Fórmula: Margen = (PrecioVenta - Costo) / PrecioVenta
  // Despejando: PrecioVenta = Costo / (1 - Margen/100)
  if (margen >= 100) {
    return formatearNumero(0) // Evitar división por cero
  }
  
  const venta = costo / (1 - margen / 100)
  return formatearNumero(venta)
})

// Watchers
watch(() => props.modelValue, async (newVal) => {
  if (newVal) {
    await cargarProductos()
    
    if (props.modoEdicion && props.precio) {
      // Modo edición
      formulario.value.producto_id = props.precio.producto_id
      await cargarColoresDisponibles()
      
      // Establecer todos los valores
      formulario.value = {
        id: props.precio.id,
        producto_id: props.precio.producto_id,
        color_id: props.precio.color_id,
        precio_costo: parseFloat(props.precio.precio_costo),
        margen: parseFloat(props.precio.margen),
        vigencia_desde: props.precio.vigencia_desde ? props.precio.vigencia_desde.split(' ')[0] : '',
        vigencia_hasta: props.precio.vigencia_hasta ? props.precio.vigencia_hasta.split(' ')[0] : '',
        activo: props.precio.activo
      }
      
      // Cargar hint del proveedor
      if (props.precio.proveedor_sugerido) {
        proveedorHint.value = `Proveedor: ${props.precio.proveedor_sugerido.nombre}`
      }
    } else {
      // Modo nuevo
      resetFormulario()
    }
  }
})

// Methods
const cargarProductos = async () => {
  try {
    const response = await api.get('/api/productos')
    productos.value = response.data
  } catch (error) {
    console.error('Error al cargar productos:', error)
  }
}

const cargarColoresDisponibles = async () => {
  if (!formulario.value.producto_id) {
    coloresDisponibles.value = []
    return
  }

  try {
    const response = await api.get('/api/productos')
    const producto = response.data.find(p => p.id === formulario.value.producto_id)
    
    const coloresProveedores = producto?.coloresPorProveedor || producto?.colores_por_proveedor || []
    
    // Extraer colores únicos
    const coloresUnicos = new Map()
    coloresProveedores.forEach(cp => {
      if (cp.color && !coloresUnicos.has(cp.color.id)) {
        coloresUnicos.set(cp.color.id, cp.color)
      }
    })
    
    coloresDisponibles.value = Array.from(coloresUnicos.values())
    
    if (coloresDisponibles.value.length === 0) {
      console.warn('⚠️ Producto sin colores definidos')
    }
  } catch (error) {
    console.error('Error al cargar colores:', error)
    coloresDisponibles.value = []
  }
}

const onProductoChange = async (newProductoId) => {
  if (newProductoId) {
    formulario.value.color_id = null
    formulario.value.precio_costo = 0
    proveedorHint.value = ''
    await cargarColoresDisponibles()
  }
}

const onColorChange = async () => {
  if (!formulario.value.color_id || !formulario.value.producto_id) {
    formulario.value.precio_costo = 0
    proveedorHint.value = ''
    return
  }

  try {
    // Buscar el costo máximo entre todos los proveedores de este producto+color
    const response = await api.get('/api/productos')
    const producto = response.data.find(p => p.id === formulario.value.producto_id)
    
    const coloresProveedores = producto?.coloresPorProveedor || producto?.colores_por_proveedor || []
    
    // Filtrar por el color seleccionado y buscar el costo más alto
    const proveedoresDelColor = coloresProveedores.filter(cp => cp.color_id === formulario.value.color_id)
    
    if (proveedoresDelColor.length === 0) {
      formulario.value.precio_costo = 0
      proveedorHint.value = 'No hay proveedores para este color'
      return
    }
    
    // Encontrar el de mayor costo
    const proveedorMaxCosto = proveedoresDelColor.reduce((max, current) => {
      return (current.costo > max.costo) ? current : max
    })
    
    formulario.value.precio_costo = parseFloat(proveedorMaxCosto.costo)
    proveedorHint.value = `Proveedor con mayor costo: ${proveedorMaxCosto.proveedor?.nombre || 'N/A'} ($${formatearNumero(proveedorMaxCosto.costo)})`
    
  } catch (error) {
    console.error('Error al calcular costo máximo:', error)
    formulario.value.precio_costo = 0
    proveedorHint.value = 'Error al calcular costo'
  }
}

const guardar = async () => {
  guardando.value = true
  try {
    if (props.modoEdicion) {
      await api.put(`/api/lista-precios/${formulario.value.id}`, formulario.value)
      alert('Precio actualizado correctamente')
    } else {
      await api.post('/api/lista-precios', formulario.value)
      alert('Precio creado correctamente')
    }
    
    emit('guardado')
    cerrar()
  } catch (error) {
    console.error('Error al guardar precio:', error)
    alert('Error al guardar el precio')
  } finally {
    guardando.value = false
  }
}

const cerrar = () => {
  dialogVisible.value = false
  resetFormulario()
}

const resetFormulario = () => {
  formulario.value = {
    id: null,
    producto_id: null,
    color_id: null,
    precio_costo: 0,
    margen: 45,
    vigencia_desde: new Date().toISOString().split('T')[0],
    vigencia_hasta: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    activo: true
  }
  coloresDisponibles.value = []
  proveedorHint.value = ''
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL').format(numero || 0)
}
</script>

<style scoped>
/* Estilos específicos del modal si es necesario */
</style>
