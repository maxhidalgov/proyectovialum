<template>
  <v-container fluid>
    <v-card class="pa-6" elevation="2">
      <v-card-title class="text-h5 mb-4">Nueva Cotización</v-card-title>

      <!-- Datos Generales -->
      <v-card-subtitle class="text-h6">Datos generales</v-card-subtitle>
      <v-divider class="mb-4" />

      <!-- Cliente + botón en fila -->
      <v-row dense>
        <v-col cols="6">
          <v-row no-gutters align="center">
            <v-col>
              <v-combobox
                v-model="form.cliente"
                :rules="[v => !!v || 'Selecciona un cliente']"
                v-model:search="clienteSearch"
                :items="clientesFiltrados"
                item-title="razon_social"
                item-value="uid"
                label="Cliente"
                return-object
                clearable
                :menu-props="{ virtualScroll: false }"
                :key="comboboxKey"
                outlined
                color="primary"
              />
            </v-col>
            <v-col cols="auto">
              <v-btn icon color="primary" @click="abrirModalCliente" class="ml-2">
                <v-icon>mdi-plus</v-icon>
              </v-btn>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <!-- Observaciones -->
      <v-row dense>
        <v-col cols="6">
          <v-textarea
            v-model="cotizacion.observaciones"
            label="Observaciones"
            outlined
            color="primary"
            auto-grow
          />
        </v-col>
      </v-row>

      <!-- Material y Color en una nueva fila -->
      <v-row dense class="mb-4">
        <v-col cols="12" md="6">
          <v-select
            v-model="cotizacion.material"
            :items="materiales"
            item-title="nombre"
            item-value="id"
            label="Material"
            outlined
            color="primary"
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            v-model="cotizacion.color"
            :items="colores"
            item-title="nombre"
            item-value="id"
            label="Color"
            outlined
            color="primary"
          />
        </v-col>
      </v-row>

      <!-- Vidrio por defecto -->
      <v-card-subtitle class="text-h6">Vidrio por defecto</v-card-subtitle>
      <v-divider class="mb-4" />
      <v-row dense class="mb-4">
        <v-col cols="12" sm="6">
          <v-select v-model="cotizacion.tipoVidrio" :items="tiposVidrio" item-title="nombre" item-value="id" label="Tipo de vidrio" outlined color="primary" />
        </v-col>

        <v-col cols="12" sm="6">
          <v-select v-model="cotizacion.productoVidrioProveedor" :items="productosVidrioFiltradosGeneral" item-title="nombre" item-value="id" label="Producto de vidrio" outlined color="primary" />
        </v-col>
      </v-row>

      <!-- Lista de Ventanas -->
      <v-card-subtitle class="text-h6">Ventanas</v-card-subtitle>
      <v-divider class="mb-4" />

      <div v-for="(ventana, index) in cotizacion.ventanas" :key="index" class="mb-4">
        <v-card class="pa-4" outlined>
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-select v-model="ventana.tipo" :items="tiposVentanaFiltrados(ventana)" item-title="nombre" item-value="id" label="Tipo de ventana" outlined color="primary" />
            </v-col>

            <template v-if="ventana.tipo === 3">
              <v-col cols="6" sm="3">
                <v-select v-model="ventana.hojas_totales" :items="[2, 3, 4, 6]" label="Hojas totales" outlined color="primary" />
              </v-col>
              <v-col cols="6" sm="3">
                <v-select v-model="ventana.hojas_moviles" :items="[1, 2, 3, 4]" label="Hojas móviles" :disabled="!ventana.hojas_totales" :rules="[v => !v || v <= ventana.hojas_totales || 'No puede exceder total']" outlined color="primary" />
              </v-col>
            </template>

            <v-col cols="6" sm="3">
              <v-text-field v-model="ventana.ancho" label="Ancho (mm)" type="number" outlined color="primary" />
            </v-col>
            <v-col cols="6" sm="3">
              <v-text-field v-model="ventana.alto" label="Alto (mm)" type="number" outlined color="primary" />
            </v-col>

            <v-col cols="12" sm="3">
              <v-select v-model="ventana.material" :items="materiales" item-title="nombre" item-value="id" label="Material (opcional)" outlined color="primary" />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select v-model="ventana.color" :items="colores" item-title="nombre" item-value="id" label="Color (opcional)" outlined color="primary" />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select v-model="ventana.tipoVidrio" :items="tiposVidrio" item-title="nombre" item-value="id" label="Tipo de vidrio (opcional)" outlined color="primary" />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select v-model="ventana.productoVidrioProveedor" :items="productosVidrioFiltradosConProveedor(ventana)" item-title="nombre" item-value="id" label="Producto de vidrio (opcional)" outlined color="primary" />
            </v-col>

            <v-col cols="12">
              <v-alert v-if="ventana.costo_total" type="info" variant="tonal" dense class="mb-2">
                <strong>Costo total de materiales:</strong> ${{ ventana.costo_total.toLocaleString() }}
              </v-alert>
              <v-alert v-if="ventana.precio" type="success" variant="tonal" dense class="mt-2">
                <strong>Precio sugerido:</strong> ${{ ventana.precio.toLocaleString() }}
              </v-alert>

              <v-btn @click="mostrarDetalles = !mostrarDetalles" color="primary" variant="outlined">
                <v-icon left>mdi-eye</v-icon>
                {{ mostrarDetalles ? 'Ocultar' : 'Ver' }} Costos
              </v-btn>

              <v-data-table v-if="mostrarDetalles && ventana.materiales && ventana.materiales.length"
                :headers="[
                  { title: 'Producto', key: 'nombre' },
                  { title: 'Proveedor', key: 'proveedor' },
                  { title: 'Cantidad', key: 'cantidad' },
                  { title: 'Unidad', key: 'unidad' },
                  { title: 'Costo Unitario', key: 'costo_unitario' },
                  { title: 'Costo Total', key: 'costo_total' },
                ]"
                :items="ventana.materiales"
                :items-per-page="-1"
                class="elevation-1 mt-4"
                hide-default-footer
              >
                <template #item.costo_unitario="{ item }">
                  ${{ item.costo_unitario.toLocaleString() }}
                </template>
                <template #item.costo_total="{ item }">
                  ${{ item.costo_total.toLocaleString() }}
                </template>
              </v-data-table>
            </v-col>

            <v-col cols="12" class="text-right">
              <v-btn color="error" variant="outlined" @click="eliminarVentana(index)">
                <v-icon left>mdi-delete</v-icon> Quitar ventana
              </v-btn>
            </v-col>
          </v-row>
        </v-card>
      </div>

      <v-btn @click="agregarVentana" color="primary" variant="outlined" class="mb-4">
        <v-icon left>mdi-plus</v-icon> Agregar ventana
      </v-btn>

      <v-divider class="my-4" />
      <v-btn color="primary" @click="guardarCotizacion" block prepend-icon="mdi-content-save" elevation="2" class="py-4">
        Guardar Cotización
      </v-btn>
    </v-card>

    <!-- MODAL NUEVO CLIENTE -->
    <v-dialog v-model="modalCliente" max-width="600px">
      <v-card>
        <v-card-title>Nuevo Cliente</v-card-title>
        <v-card-text>
          <v-form ref="formNuevoCliente" @submit.prevent="guardarCliente">
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.firstName" label="Nombre" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.lastName" label="Apellido" />
              </v-col>
            </v-row>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.company" label="Razón Social" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.code" label="RUT" />
              </v-col>
            </v-row>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.email" label="Email" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.phone" label="Teléfono" />
              </v-col>
            </v-row>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.city" label="Ciudad" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="nuevoCliente.municipality" label="Comuna" />
              </v-col>
            </v-row>
            <v-text-field v-model="nuevoCliente.address" label="Dirección" />
            <v-text-field v-model="nuevoCliente.activity" label="Giro" />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="modalCliente = false">Cancelar</v-btn>
          <v-btn color="primary" @click="guardarCliente">Guardar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'
import { useRouter } from 'vue-router'

const margenVenta = 0.45 // Margen del 45%
const router = useRouter()

// Cotización general
const cotizacion = ref({
  cliente_id: null,
  observaciones: '',
  material: '',
  color: '',
  tipoVidrio: '',
  productoVidrioProveedor: '',
  ventanas: [],
})

const mostrarDetalles = ref(false);

// Formulario de cliente
const form = reactive({
  cliente: null,
})

// Datos generales
const materiales = ref([])
const colores = ref([])
const tiposVidrio = ref([])
const productosVidrio = ref([])
const tiposVentanaTodos = ref([])
const clientes = ref([])

const clienteSearch = ref('')
const modalCliente = ref(false)
const comboboxKey = ref(0)

const nuevoCliente = ref({
  firstName: '', lastName: '', company: '', code: '', email: '',
  phone: '', city: '', municipality: '', address: '', activity: ''
})

const productosVidrioCombinados = computed(() => {
  return productosVidrio.value.flatMap(p => 
    p.colores_por_proveedor.map(cpp => ({
      id: `${p.id}-${cpp.proveedor_id}`,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id,
      nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
    }))
  )
})

const productosVidrioFiltradosGeneral = computed(() => {
  const tipo = cotizacion.value.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p =>
      p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,  // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    )
})

onMounted(async () => {
  const [
    materialesRes, coloresRes, tiposProductoRes,
    productosRes, tiposVentanaRes, clientesRes
  ] = await Promise.all([
    api.get('/api/tipos_material'),
    api.get('/api/colores'),
    api.get('/api/tipos_producto'),
    api.get('/api/productos'),
    api.get('/api/tipos_ventana'),
    api.get('/api/clientes'),
  ])

  materiales.value = materialesRes.data
  colores.value = coloresRes.data
  tiposVidrio.value = tiposProductoRes.data.filter(tp => [1, 2].includes(tp.id))
  productosVidrio.value = productosRes.data.filter(p => [1, 2].includes(p.tipo_producto_id))
  tiposVentanaTodos.value = tiposVentanaRes.data
  clientes.value = clientesRes.data
})

const buscarRelacionVidrioProveedor = (id) => {
  id = parseInt(id)
  return productosVidrio.value.flatMap(p =>
    p.colores_por_proveedor.map(cpp => ({
      id: cpp.id,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id
    }))
  ).find(p => p.id === id)
}

// Computed para mostrar productos de vidrio con proveedor
const productosVidrioFiltradosConProveedor = (ventana) => {
  const tipo = ventana.tipoVidrio ?? cotizacion.value.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p =>
      p.colores_por_proveedor.map(cpp => ({
        id: cpp.id, // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    )
}

const clientesFiltrados = computed(() => {
  const filtro = clienteSearch.value.toLowerCase()
  const vistos = new Set()
  return clientes.value
    .filter(c => c.razon_social?.toLowerCase().includes(filtro))
    .filter(c => {
      if (vistos.has(c.id)) return false
      vistos.add(c.id)
      return true
    })
    .map((c, index) => ({
      uid: `${c.id}-${index}`,
      id: c.id,
      razon_social: c.razon_social?.trim(),
      raw: c,
    }))
})

// Ventanas
const agregarVentana = (ventanaModal = null) => {
  const base = {
    tipo: null,
    ancho: null,
    alto: null,
    material: cotizacion.value.material,
    color: cotizacion.value.color,
    tipoVidrio: cotizacion.value.tipoVidrio,
    productoVidrioProveedor: cotizacion.value.productoVidrioProveedor ?? null,
    hojas_totales: 2,
    hojas_moviles: 2,
    materiales: [],
    costo_total: 0,
    precio: 0,
  }

  const nuevaVentana = { ...base, ...(ventanaModal || {}) }

  cotizacion.value.ventanas.push(nuevaVentana)

  const relacion = buscarRelacionVidrioProveedor(nuevaVentana.productoVidrioProveedor)

  if (
    nuevaVentana.tipo &&
    nuevaVentana.ancho &&
    nuevaVentana.alto &&
    relacion
  ) {
    const payload = {
      ...nuevaVentana,
      productoVidrio: relacion.producto_id,
      proveedorVidrio: relacion.proveedor_id,
      hojas_moviles: nuevaVentana.tipo === 3 ? nuevaVentana.hojas_moviles : undefined,
    }
    recalcularCosto(payload, nuevaVentana)
  }
}

const eliminarVentana = (index) => {
  cotizacion.value.ventanas.splice(index, 1)
}

const tiposVentanaFiltrados = (ventana) => {
  const materialId = ventana.material ?? cotizacion.value.material
  return tiposVentanaTodos.value.filter(t => t.material_id === materialId)
}

const recalcularCosto = debounce(async (payload, ventanaRef) => {
  // Validación de campos requeridos
  if (!payload.productoVidrio || !payload.proveedorVidrio) {
    console.warn('⚠️ Faltan datos en el payload para calcular materiales:', payload)
    return
  }

  try {
    const res = await api.post('/api/cotizador/calcular-materiales', payload)
    ventanaRef.costo_total = res.data.costo_total
    ventanaRef.materiales = res.data.materiales
    ventanaRef.precio = Math.ceil(res.data.costo_total / (1 - margenVenta))
  } catch (err) {
    console.error('❌ Error al calcular materiales', err)
    ventanaRef.costo_total = 0
    ventanaRef.materiales = []
  }
}, 1000)

watch(() => cotizacion.value.ventanas, (ventanas) => {
  ventanas.forEach((ventana) => {
    watch(() => [
      ventana.tipo,
      ventana.ancho,
      ventana.alto,
      ventana.material,
      ventana.color,
      ventana.tipoVidrio,
      ventana.productoVidrioProveedor,
      ventana.hojas_totales,
      ventana.hojas_moviles
    ],
    () => {
      if (
        ventana.tipo &&
        ventana.ancho &&
        ventana.alto &&
        ventana.productoVidrioProveedor
      ) {
        const relacion = buscarRelacionVidrioProveedor(ventana.productoVidrioProveedor)
        console.log('🧪 Relación en watch:', relacion)
        const payload = {
          ...ventana,
          productoVidrio: relacion?.producto_id,
          proveedorVidrio: relacion?.proveedor_id,
          hojas_moviles: ventana.tipo === 3 ? ventana.hojas_moviles : undefined,
        }
        recalcularCosto(payload, ventana)
      }
    },
    { deep: true, immediate: false })
  })
}, { deep: true })

watch(() => form.cliente, cliente => {
  if (cliente?.raw) {
    console.log('✅ Cliente seleccionado:', cliente.raw)
  }
})

const abrirModalCliente = () => {
  modalCliente.value = true
}

const guardarCliente = async () => {
  try {
    const res = await api.post('/api/bsale-clientes', nuevoCliente.value)
    clientes.value.push(res.data.cliente)
    form.cliente = res.data.cliente
    modalCliente.value = false
  } catch (error) {
    alert('❌ Error al crear cliente')
    console.error(error)
  }
}

const guardarCotizacion = async () => {
  try {
    const clienteSeleccionado = form.cliente?.raw
    if (!clienteSeleccionado || cotizacion.value.ventanas.length === 0) {
      alert('Debes seleccionar un cliente y agregar al menos una ventana')
      return
    }
    const payload = {
      cliente_id: clienteSeleccionado.id,
      vendedor_id: 1,
      fecha: new Date().toISOString().split('T')[0],
      estado_cotizacion_id: cotizacion.value.estado_cotizacion_id ?? 1, // default: Evaluación
      observaciones: cotizacion.value.observaciones,
      ventanas: cotizacion.value.ventanas.map(v => {
        const relacion = buscarRelacionVidrioProveedor(v.productoVidrioProveedor)
        return {
          tipo_ventana_id: v.tipo,
          ancho: v.ancho,
          alto: v.alto,
          color_id: v.color,
          producto_vidrio_proveedor_id: v.productoVidrioProveedor,  
          producto_id: relacion?.producto_id,
          proveedor_id: relacion?.proveedor_id,
          costo: v.costo_total || 0,
          precio: v.precio || 0
        }
      }),
    }

    await api.post('api/cotizaciones', payload)

    alert('Cotización guardada correctamente')
    router.push({ name: 'cotizaciones' })

  } catch (error) {
    console.error('Error al guardar cotización:', error)
    alert('Error al guardar la cotización')
  }
}





</script>



<style scoped>
.v-card-subtitle {
  font-weight: 600;
}
</style>
