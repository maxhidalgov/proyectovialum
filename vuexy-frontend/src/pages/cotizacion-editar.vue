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
                <v-autocomplete
                  v-model="cotizacion.cliente_id"
                  :items="clientes"
                  item-title="razon_social"
                  item-value="id"
                  label="Cliente"
                  clearable
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="cotizacion.fecha" label="Fecha" type="date" />
              </v-col>

              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.estado_cotizacion_id"
                  :items="estadosCotizacion"
                  item-value="id"
                  item-title="nombre"
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
        <v-card-title>
          Ventanas
          <v-spacer />
          <v-btn icon @click="modalAgregarVentana = true" v-if="cotizacion.estado_cotizacion_id === 1">
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>

        <div v-if="cotizacion.estado_cotizacion_id === 1">
          <div
            v-for="(ventana, index) in cotizacion.ventanas"
            :key="index"
            class="mb-6"
          >
            <VentanaForm
              :ventana="ventana"
              :tiposVentana="tiposVentanaTodos"
              :colores="colores"
              :tiposVidrio="tiposVidrio"
              :productosVidrio="productosVidrioFiltrados"
            />

            <v-row class="mt-2 px-4">
              <v-col cols="6">
                <v-alert type="info" variant="tonal" color="blue">
                  <strong>Costo:</strong> ${{ ventana.costo?.toLocaleString?.() || 0 }}
                </v-alert>
              </v-col>
              <v-col cols="6">
                <v-alert type="success" variant="tonal" color="green">
                  <strong>Precio:</strong> ${{ ventana.precio?.toLocaleString?.() || 0 }}
                </v-alert>
              </v-col>
            </v-row>
          </div>
        </div>

        <div v-else>
          <v-data-table
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
                {{ item.color_obj?.nombre || '—' }}
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
        </div>
      </v-card>

      <AgregarVentanaModal
        v-model="modalAgregarVentana"
        :tiposVentana="tiposVentanaTodos"
        :colores="colores"
        :materiales="materiales"
        :tiposVidrio="tiposVidrio"
        :productosVidrio="productosVidrio"
        @agregar="agregarVentanaDesdeModal"
      />
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
import AgregarVentanaModal from './AgregarVentanaModal.vue'
import debounce from 'lodash/debounce'

const modalAgregarVentana = ref(false)
const agregarVentana = (ventana) => {
  cotizacion.value.ventanas.push(ventana)

const relacion = buscarRelacionVidrioProveedor(ventana.producto_vidrio_proveedor_id)

const payload = {
  tipo: ventana.tipo_ventana_id,
  ancho: ventana.ancho,
  alto: ventana.alto,
  material: ventana.material,
  color: ventana.color_id ?? ventana.color, // por si acaso
  tipoVidrio: ventana.tipo_vidrio_id,
  productoVidrioProveedor: ventana.producto_vidrio_proveedor_id,
  productoVidrio: relacion?.producto_id,
  proveedorVidrio: relacion?.proveedor_id,
  hojas_totales: ventana.hojas_totales,
  hojas_moviles: ventana.tipo_ventana_id === 3 ? ventana.hojas_moviles : undefined,
}

  console.log('➡️ Payload para cálculo:', payload)
  recalcularCosto(payload, ventana)

}
const route = useRoute()
const router = useRouter()
const cotizacionId = route.query.id
const estados = ref([])
const estadosCotizacion = ref([])
const cotizacionOriginal = ref(null)
const clientes = ref([])
const materiales = ref([])

const mostrarModalVentana = ref(false)
const nuevaVentana = ref(crearVentanaVacia())

function crearVentanaVacia() {
  return {
    tipo_ventana_id: null,
    ancho: null,
    alto: null,
    color_id: null,
    tipo_vidrio_id: null,
    producto_vidrio_proveedor_id: null,
    hojas_totales: 2,
    hojas_moviles: 2,
    costo: 0,
    precio: 0,
    materiales: [],
  }
}

const buscarRelacionVidrioProveedor = (id) => {
  return productosVidrio.value.flatMap(p =>
    p.colores_por_proveedor.map(cpp => ({
      id: cpp.id,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id
    }))
  ).find(p => p.id === id)
}

const recalcularCosto = debounce(async (payload, ventanaRef) => {
  if (
    !payload.productoVidrio ||
    !payload.proveedorVidrio ||
    !payload.tipo ||
    !payload.ancho ||
    !payload.alto
  ) {
    console.warn('⚠️ Payload incompleto para cálculo:', payload)
    return
  }

  try {
    console.log('➡️ Enviando a /api/cotizador/calcular-materiales:', payload)

    const res = await api.post('/api/cotizador/calcular-materiales', payload)

    console.log('✅ Respuesta del backend:', res.data)

    ventanaRef.costo = res.data.costo_total ?? 0
    ventanaRef.costo_total = res.data.costo_total // opcional, si usas ese nombre en la UI
    ventanaRef.precio = Math.ceil((res.data.costo_total ?? 0) / (1 - 0.45))
    ventanaRef.materiales = res.data.materiales ?? []

    console.log('🧮 Costo asignado:', ventanaRef.costo)
    console.log('🧮 Precio asignado:', ventanaRef.precio)

  } catch (error) {
    console.error('❌ Error al calcular materiales:', error)
    ventanaRef.costo = 0
    ventanaRef.precio = 0
    ventanaRef.materiales = []
  }
}, 800)

function abrirModalVentana() {
  nuevaVentana.value = crearVentanaVacia()
  mostrarModalVentana.value = true
}

function cerrarModalVentana() {
  mostrarModalVentana.value = false
}

function confirmarAgregarVentana() {
  cotizacion.value.ventanas.push({ ...nuevaVentana.value })
  mostrarModalVentana.value = false
}

const cotizacion = ref({
  fecha: '',
  estado_cotizacion_id: null,
  observaciones: '',
  ventanas: [],
})




const ventanaPorAgregar = ref(null)

const abrirModalAgregarVentana = () => {
  ventanaPorAgregar.value = {
    tipo: null,
    ancho: null,
    alto: null,
    color: null,
    tipoVidrio: cotizacion.value.tipoVidrio ?? null,
    productoVidrioProveedor: cotizacion.value.productoVidrioProveedor ?? null,
    hojas_totales: 2,
    hojas_moviles: 2,
    materiales: [],
    costo_total: 0,
    precio: 0,
  }
  modalAgregarVentana.value = true
}

const agregarVentanaDesdeModal = (ventana) => {
  const relacion = buscarRelacionVidrioProveedor(ventana.producto_vidrio_proveedor_id)

  const ventanaFinal = {
    ...ventana,
    costo: 0,
    precio: 0,
    materiales: [],
  }

cotizacion.value.ventanas.push(ventanaFinal)

setTimeout(() => {
  recalcularCosto(payload, ventanaFinal)
}, 0)

  const payload = {
    ...ventanaFinal,
    tipo: ventanaFinal.tipo_ventana_id,
    tipoVidrio: ventanaFinal.tipo_vidrio_id,
    productoVidrio: relacion?.producto_id,
    proveedorVidrio: relacion?.proveedor_id,
    hojas_moviles: ventanaFinal.tipo_ventana_id === 3 ? ventanaFinal.hojas_moviles : undefined,
  }

  console.log('✅ Ejecutando recalcularCosto para ventana agregada...')
  recalcularCosto(payload, ventanaFinal)

  modalAgregarVentana.value = false
}

const form = ref(null)

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
  const original = JSON.stringify(cotizacionOriginal.value)
  const actual = JSON.stringify(cotizacion.value)

  if (original === actual) {
    alert('No hay cambios para guardar')
    return
  }

  try {
    const payload = {
      cliente_id: cotizacion.value.cliente_id,
      fecha: cotizacion.value.fecha,
      estado_cotizacion_id: cotizacion.value.estado_cotizacion_id,
      observaciones: cotizacion.value.observaciones,
      ventanas: cotizacion.value.ventanas.map(v => ({
        id: v.id,
        tipo_ventana_id: v.tipo_ventana_id,
        ancho: v.ancho,
        alto: v.alto,
        color_id: v.color_id,
        producto_vidrio_proveedor_id: v.producto_vidrio_proveedor_id,
        costo: v.costo || 0,
        precio: v.precio || 0,
      })),
    }

    await api.put(`/api/cotizaciones/${cotizacion.value.id}`, payload)
    alert('Cotización actualizada correctamente')
    volver()
  } catch (error) {
    console.error('❌ Error al guardar cambios:', error)
    alert('Error al guardar cambios')
  }
}

onMounted(async () => {
  try {
    const [cotizacionRes, tiposVentanaRes, coloresRes, tiposVidrioRes, productosRes, estadosRes, clientesRes, materialesRes] = await Promise.all([
      api.get(`/api/cotizaciones/${cotizacionId}`),
      api.get('/api/tipos_ventana'),
      api.get('/api/colores'),
      api.get('/api/tipos_producto'),
      api.get('/api/productos'),
      api.get('/api/estados-cotizacion'),
      api.get('/api/clientes'),
      api.get('/api/tipos_material')
    ])

    cotizacion.value = cotizacionRes.data
    clientes.value = clientesRes.data
    materiales.value = materialesRes.data
    cotizacion.value.ventanas = cotizacion.value.ventanas.map(v => ({
      ...v,
      tipo_vidrio_id: v.producto_vidrio_proveedor?.producto?.tipo_producto_id ?? null,
    }))
    cotizacionOriginal.value = JSON.parse(JSON.stringify(cotizacion.value))
    tiposVentanaTodos.value = tiposVentanaRes.data
    colores.value = coloresRes.data
    tiposVidrio.value = tiposVidrioRes.data.filter(t => [1, 2].includes(t.id))
    productosVidrio.value = productosRes.data.filter(p => [1, 2].includes(p.tipo_producto_id))
    estadosCotizacion.value = estadosRes.data
  } catch (error) {
    console.error('Error al cargar cotización:', error)
  }
})
</script>
