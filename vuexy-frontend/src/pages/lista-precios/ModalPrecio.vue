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

            <!-- Selector de Color/Proveedor -->
            <v-col cols="12">
              <v-autocomplete
                v-model="formulario.producto_color_proveedor_id"
                :items="productosColorProveedor"
                :item-title="item => `${item.color?.nombre || 'Sin color'} - ${item.proveedor?.nombre || 'Sin proveedor'}`"
                item-value="id"
                label="Color y Proveedor *"
                prepend-inner-icon="mdi-palette"
                variant="outlined"
                density="compact"
                :disabled="!formulario.producto_id"
                clearable
                :rules="[v => !!v || 'Debes seleccionar un color y proveedor']"
                @update:model-value="onColorProveedorChange"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #title>
                      {{ item.raw.color?.nombre || 'Sin color' }} - {{ item.raw.proveedor?.nombre || 'Sin proveedor' }}
                    </template>
                    <template #subtitle>
                      Costo: ${{ formatearNumero(item.raw.costo) }}
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Precio Costo -->
            <v-col cols="12" md="4">
              <v-text-field
                v-model.number="formulario.precio_costo"
                label="Precio Costo *"
                type="number"
                prefix="$"
                variant="outlined"
                density="compact"
                :rules="[
                  v => v !== null && v !== undefined && v !== '' || 'El precio costo es requerido',
                  v => v >= 0 || 'Debe ser mayor o igual a 0'
                ]"
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
const productosColorProveedor = ref([])
const guardando = ref(false)
const form = ref(null)

const formulario = ref({
  id: null,
  producto_id: null,
  producto_color_proveedor_id: null,
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
  const venta = costo * (1 + margen / 100)
  return formatearNumero(venta)
})

// Watchers
watch(() => props.modelValue, async (newVal) => {
  if (newVal) {
    await cargarProductos()
    
    if (props.modoEdicion && props.precio) {
      // Modo edición
      formulario.value.producto_id = props.precio.producto_id
      await cargarProductoColorProveedor()
      
      // Establecer todos los valores
      formulario.value = {
        id: props.precio.id,
        producto_id: props.precio.producto_id,
        producto_color_proveedor_id: props.precio.producto_color_proveedor_id,
        precio_costo: parseFloat(props.precio.precio_costo),
        margen: parseFloat(props.precio.margen),
        vigencia_desde: props.precio.vigencia_desde ? props.precio.vigencia_desde.split(' ')[0] : '',
        vigencia_hasta: props.precio.vigencia_hasta ? props.precio.vigencia_hasta.split(' ')[0] : '',
        activo: props.precio.activo
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

const cargarProductoColorProveedor = async () => {
  if (!formulario.value.producto_id) {
    productosColorProveedor.value = []
    return
  }

  try {
    const response = await api.get('/api/productos')
    const producto = response.data.find(p => p.id === formulario.value.producto_id)
    
    const coloresProveedores = producto?.coloresPorProveedor || producto?.colores_por_proveedor || []
    
    if (coloresProveedores.length > 0) {
      productosColorProveedor.value = coloresProveedores
    } else {
      productosColorProveedor.value = []
      console.warn('⚠️ Producto sin colores/proveedores definidos')
    }
  } catch (error) {
    console.error('Error al cargar producto-color-proveedor:', error)
    productosColorProveedor.value = []
  }
}

const onProductoChange = async (newProductoId) => {
  if (newProductoId) {
    formulario.value.producto_color_proveedor_id = null
    formulario.value.precio_costo = 0
    await cargarProductoColorProveedor()
  }
}

const onColorProveedorChange = () => {
  if (!formulario.value.producto_color_proveedor_id) return

  const pcp = productosColorProveedor.value.find(
    p => p.id === formulario.value.producto_color_proveedor_id
  )

  if (pcp && pcp.costo) {
    formulario.value.precio_costo = parseFloat(pcp.costo)
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
    producto_color_proveedor_id: null,
    precio_costo: 0,
    margen: 45,
    vigencia_desde: new Date().toISOString().split('T')[0],
    vigencia_hasta: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    activo: true
  }
  productosColorProveedor.value = []
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL').format(numero || 0)
}
</script>

<style scoped>
/* Estilos específicos del modal si es necesario */
</style>
