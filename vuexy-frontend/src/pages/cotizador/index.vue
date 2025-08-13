<template>
  <v-container fluid>
    <v-card class="pa-6" elevation="2">
      <v-card-title class="text-h5 mb-4">Nueva Cotización</v-card-title>
      

      <!-- Datos Generales -->
      <v-card-subtitle class="text-h5">Datos generales</v-card-subtitle>
      <v-divider class="mb-4" />

      <!-- Cliente + botón en fila -->
      <v-row dense>
        <v-col cols="6" md="4">
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

      <!-- Material y Color -->
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
      <v-card-subtitle class="text-h5">Vidrio por defecto</v-card-subtitle>
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
      <v-card-subtitle class="text-h5">Ventanas</v-card-subtitle>
      <v-divider class="mb-4" />

<v-btn color="primary" @click="abrirModalVentana" :disabled="!tiposVentanaTodos.length">
  <v-icon left>mdi-plus</v-icon> Agregar ventana
</v-btn>

        <v-data-table 
          :headers="headersVentanas"
          :items="cotizacion.ventanas"
          class="mt-4"
          :items-per-page="5"
        >


<template #item.tipo="{ item }">
  {{ mapaTiposVentana[Number(item.tipo)] || item.tipo }}
</template>


        <template #item.acciones="{ item, index }">
          <v-btn icon @click="editarVentana(index)">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon color="error" @click="eliminarVentana(index)">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>

      <!-- Modal para agregar/editar ventana -->
      <AgregarVentanaModal
        v-model:mostrar="mostrarModalVentana"
        :materiales="materiales"
        :colores="colores"
        :tiposVidrio="tiposVidrio"
        :productosVidrio="productosVidrio"
        :tiposVentana="tiposVentanaTodos"
        :ventana="ventanaEnEdicion"
        :material-default="cotizacion.material"
        :color-default="cotizacion.color"
        :tipo-vidrio-default="cotizacion.tipoVidrio"
        :producto-vidrio-default="cotizacion.productoVidrioProveedor"
        @guardar="guardarVentana"
      />

      <!-- Botón para agregar ventana -->
      <v-divider class="my-4" />
      <v-btn
        color="primary"
        :loading="loading"
        :disabled="loading"
        @click="guardarCotizacion"
      >
        <template #loader>
          <v-progress-circular indeterminate color="white" size="20" />
        </template>
        Guardar Cotización
      </v-btn>
       <!-- Renderización de ventanas para captura de imágenes -->
      <div v-if="cotizacion.ventanas.length > 0" class="mt-6">
        <v-card-subtitle class="text-h5">Vista previa de ventanas</v-card-subtitle>
        <v-divider class="mb-4" />
        <div v-for="(ventana, index) in cotizacion.ventanas" :key="index" class="mb-4">
          <v-card class="pa-4" outlined>
            <v-card-title>{{ mapaTiposVentana[ventana.tipo] || `Ventana ${index + 1}` }}</v-card-title>
            <v-row>
              <v-col cols="6">
                <VentanaEditor
                  v-if="ventana.tipo === 2"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaProyectante
                  v-else-if="ventana.tipo === 45"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaCorredera
                  v-else-if="ventana.tipo === 3"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
                />
                <VentanaCorredera98
                  v-else-if="ventana.tipo === 52"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
              />
                <BayWindow
                  v-else-if="ventana.tipo === 47"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :ancho-izquierda="ventana.ancho_izquierda"
                  :ancho-centro="ventana.ancho_centro"
                  :ancho-derecha="ventana.ancho_derecha"
                  :tipo-ventana-izquierda="ventana.tipoVentanaIzquierda"
                  :tipo-ventana-centro="ventana.tipoVentanaCentro"
                  :tipo-ventana-derecha="ventana.tipoVentanaDerecha"
                />
                <VistaVentanaCorrederaAndes
                  v-else-if="ventana.tipo === 46"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
                />
                <VistaVentanaMonorriel
                  v-else-if="ventana.tipo === 53"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :lado-apertura="ventana.ladoApertura"
                />
                <VentanaAbatir
                  v-else-if="ventana.tipo === 49"  
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :lado-inicial="ventana.ladoApertura || 'izquierda'"
                />
                <PuertaS60
                  v-else-if="ventana.tipo === 50"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :lado-apertura="ventana.ladoApertura"
                  :direccion-apertura="ventana.direccionApertura"
                  :paso-libre="ventana.pasoLibre"
                />
                <VistaMamparaS60
                  v-else-if="ventana.tipo === 51"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :hoja-activa="ventana.hojaActiva"
                  :direccion-apertura="ventana.direccionApertura"
                  :paso-libre="ventana.pasoLibre"
                />
                <VistaVentanaCompuestaDinamica
                  v-else-if="ventana.tipo === 58"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :orientacion="ventana.orientacionComp"
                  :items="ventana.itemsComp"
                />
              </v-col>
              <v-col cols="6">
                <v-card variant="outlined">
                  <v-card-title>Detalles</v-card-title>
                  <v-card-text>
                    <p><strong>Ancho:</strong> {{ ventana.ancho }}mm</p>
                    <p><strong>Alto:</strong> {{ ventana.alto }}mm</p>
                    <p><strong>Cantidad:</strong> {{ ventana.cantidad }}</p>
                    <p><strong>Precio:</strong> ${{ ventana.precio }}</p>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card>
        </div>
      </div>
    </v-card>
  </v-container>

 
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'
import { useRouter } from 'vue-router'
import Visor3D from '@/layouts/components/Visor3D.vue'
import { color } from 'three/src/nodes/TSL.js'
import VistaVentanaCorrederaAndes from '@/components/VistaVentanaCorrederaAndes.vue'
import AgregarVentanaModal from '@/pages/AgregarVentanaModal2.vue'
import VentanaEditor from '@/components/VistaVentanaFijaS60.vue'
import VentanaCorredera from '@/components/VistaVentanaCorredera.vue'
import VentanaProyectante from '@/components/VistaVentanaProyectanteS60.vue'
import BayWindow from '@/components/VistaBayWindow.vue'
import VentanaAbatir from '@/components/VistaVentanaAbatirS60.vue'
import PuertaS60 from '@/components/VistaPuertaS60.vue'
import VistaMamparaS60 from '@/components/VistaMamparaS60.vue'
import VentanaCorredera98 from '@/components/VistaVentanaCorredera98.vue'
import VistaVentanaMonorriel from '@/components/VistaVentanaMonorriel.vue'
import VistaVentanaCompuestaDinamica from '@/components/VistaVentanaCompuestaDinamica.vue'



const ventanaRefs = ref([]) // mantener referencias
const tiposVentanaTodos = ref([])

const margenVenta = 0.45 // Margen del 45%
const router = useRouter()

const mapaTiposVentana = computed(() => {
  const map = {}
  for (const t of tiposVentanaTodos.value) {
    map[Number(t.id)] = t.nombre
  }
  return map
})

// Cotización general
const cotizacion = reactive({
  cliente_id: null,
  observaciones: '',
  material: '',
  color: '',
  tipoVidrio: '',
  productoVidrioProveedor: '',
  ventanas: [],
       
})

const tiposVentanaBayKonva = [
  { id: 1, nombre: 'Fija' },
  { id: 2, nombre: 'Proyectante' },
  { id: 3, nombre: 'Corredera' },

]
const tiposVentanaCentro = [
  { id: 2, nombre: 'Fija' },
  { id: 3, nombre: 'Corredera Sliding' },
  { id: 45, nombre: 'Proyectante S60' },
  //{ id: 46, nombre: 'Corredera Andes' },
]

const mostrarDetalles = ref({})
const loading = ref(false)

// Formulario de cliente
const form = reactive({
  cliente: null,
})

// Datos generales
const materiales = ref([])
const colores = ref([])
const tiposVidrio = ref([])
const productosVidrio = ref([])

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
  const tipo = cotizacion.tipoVidrio
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
   console.log('TIPOS VENTANA CARGADOS:', tiposVentanaTodos.value)
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
  const tipo = ventana.tipoVidrio ?? cotizacion.tipoVidrio
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
const mostrarModalVentana = ref(false)
const ventanaEnEdicion = ref(null)

const headersVentanas = [
  { title: 'Tipo', key: 'tipo' },
  { title: 'Ancho', key: 'ancho' },
  { title: 'Alto', key: 'alto' },
  { title: 'Cantidad', key: 'cantidad' },
  { title: 'Precio', key: 'precio', align: 'end' },
  { title: 'Acciones', key: 'acciones', sortable: false },
]

const abrirModalVentana = () => {
  ventanaEnEdicion.value = null // Para agregar nueva
  mostrarModalVentana.value = true
}

const editarVentana = (index) => {
  ventanaEnEdicion.value = { ...cotizacion.ventanas[index], index }
  mostrarModalVentana.value = true
}

const guardarVentana = (ventana) => {
    console.log('VENTANA RECIBIDA:', ventana)
  if (ventana.index !== undefined) {
    cotizacion.ventanas[ventana.index] = { ...ventana }
  } else {
    cotizacion.ventanas.push({ ...ventana })
  }
  mostrarModalVentana.value = false
}

const eliminarVentana = (index) => {
  cotizacion.ventanas.splice(index, 1)
}

const agregarVentana = (ventanaModal = null) => {
  const base = {
    tipo: null,
    ancho: null,
    alto: null,
    cantidad: 1,
    material: cotizacion.material,
    color: cotizacion.color,
    tipoVidrio: cotizacion.tipoVidrio,
    productoVidrioProveedor: cotizacion.productoVidrioProveedor ?? null,
    hojas_totales: 2,
    hojas_moviles: 2,
    materiales: [],
    costo_total: 0,
    costo_total_unitario: 0,
    costo: 0,
    precio_unitario: 0,
    precio: 0,
    hoja1AlFrente: true,
    tipoVentanaIzquierda: {
      compuesta: false,
      partes: [
        { tipo: null, alto: null }, // Parte superior
        { tipo: null, alto: null }, // Parte inferior (solo si compuesta = true)
      ]
    },
    tipoVentanaDerecha: {
      compuesta: false,
      partes: [
        { tipo: null, alto: null },
        { tipo: null, alto: null },
      ]
    },
     tipoVentanaCentro: {
    tipo: null,
    hojas_totales: null,
    hojas_moviles: null,
    hojaMovilSeleccionada: null,
    hoja1AlFrente: true
  },
    ancho_izquierda: null,
    ancho_centro: null,
    ancho_derecha: null,

  }

  const nuevaVentana = { ...base, ...(ventanaModal || {}) }

    if (nuevaVentana.tipo === 47) {
    nuevaVentana.ancho_izquierda = null
    nuevaVentana.ancho_centro = null
    nuevaVentana.ancho_derecha = null
  }

  cotizacion.ventanas.push(nuevaVentana)

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
      hojas_moviles: nuevaVentana.tipo === 3 || nuevaVentana.tipo === 46 ? nuevaVentana.hojas_moviles : undefined,
    }
    recalcularCosto(payload, nuevaVentana)
  }
}



const tiposVentanaFiltrados = (ventana) => {
  const materialId = ventana.material ?? cotizacion.material
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

    // Asignar costo unitario (para mostrar si se desea)
    ventanaRef.costo_total_unitario = res.data.costo_unitario


    // Multiplicar por cantidad para obtener el costo total real
    const cantidad = ventanaRef.cantidad > 0 ? ventanaRef.cantidad : 1
    //ventanaRef.costo_total = res.data.costo_total * cantidad
    ventanaRef.costo_total = res.data.costo_unitario * ventanaRef.cantidad

    // Recalcular precio con margen de utilidad
    ventanaRef.precio = Math.ceil(ventanaRef.costo_total / (1 - margenVenta))

    // Asignar materiales
    ventanaRef.materiales = res.data.materiales
  } catch (err) {
    console.error('❌ Error al calcular materiales', err)
    ventanaRef.costo_total = 0
    ventanaRef.materiales = []
  }
}, 1000)

watch(() => cotizacion.ventanas, (ventanas) => {
  ventanas.forEach((ventana) => {
    watch(() => [
      ventana.tipo,
      ventana.ancho,
      ventana.alto,
      ventana.cantidad,
      ventana.material,
      ventana.color,
      ventana.tipoVidrio,
      ventana.productoVidrioProveedor,
      ventana.hojas_totales,
      ventana.hojas_moviles
    ],
    () => {
      const errores = []

      if (!ventana.tipo) errores.push('tipo_ventana_id faltante')
      if (!ventana.ancho) errores.push('ancho faltante')
      if (!ventana.alto) errores.push('alto faltante')
      if (!ventana.cantidad || ventana.cantidad <= 0) errores.push('cantidad inválida')
      if (!ventana.productoVidrioProveedor) errores.push('productoVidrioProveedor faltante')

      const relacion = buscarRelacionVidrioProveedor(ventana.productoVidrioProveedor)

      if (!relacion) errores.push(`relación producto-proveedor no encontrada (ID: ${ventana.productoVidrioProveedor})`)

      if (errores.length > 0) {
        console.warn(`❌ No se puede recalcular la ventana (tipo ${ventana.tipo}):`, errores.join(', '))
        return
      }

      const payload = {
        ...ventana,
        productoVidrio: relacion.producto_id,
        proveedorVidrio: relacion.proveedor_id,
        hojas_moviles: ventana.tipo === 3 || ventana.tipo === 46 ? ventana.hojas_moviles : undefined,
      }

      console.log('✅ Recalculando ventana:', payload)
      recalcularCosto(payload, ventana)
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

const exportarImagenesVentanas = async () => {
  await new Promise(resolve => setTimeout(resolve, 2000))
  const imagenes = []
  
  console.log('🔍 INICIANDO CAPTURA DE IMÁGENES')
  console.log('🔍 VENTANA REFS:', ventanaRefs.value)
  console.log('🔍 TOTAL VENTANAS:', cotizacion.ventanas.length)
  
  for (let i = 0; i < cotizacion.ventanas.length; i++) {
    const ventana = cotizacion.ventanas[i]
    console.log(`🔍 VENTANA ${i} - TIPO:`, ventana.tipo)
    
    try {
      const componente = ventanaRefs.value[i]
      console.log(`🔍 COMPONENTE ${i}:`, componente)
      console.log(`🔍 TIPO DE COMPONENTE ${i}:`, typeof componente)
      console.log(`🔍 $el DE COMPONENTE ${i}:`, componente?.$el)
      console.log(`🔍 TIPO DE $el ${i}:`, typeof componente?.$el)
      
      // ✅ VERIFICAR SI EL COMPONENTE TIENE MÉTODO exportarImagen
      if (componente?.exportarImagen && typeof componente.exportarImagen === 'function') {
        console.log(`🔧 Usando exportarImagen() del componente ${i}`)
        try {
          const base64 = await componente.exportarImagen()
          if (base64 && base64 !== null) {
            console.log(`✅ IMAGEN ${i} CAPTURADA VIA exportarImagen:`, base64.substring(0, 50))
            imagenes.push(base64)
            continue
          } else {
            console.warn(`⚠️ exportarImagen() devolvió null para componente ${i}`)
          }
        } catch (exportError) {
          console.error(`❌ Error en exportarImagen del componente ${i}:`, exportError)
        }
      }
      
      // ✅ VERIFICAR QUE $el EXISTE Y ES UN ELEMENTO DOM
      if (componente?.$el && 
          componente.$el.nodeType === Node.ELEMENT_NODE && 
          typeof componente.$el.querySelectorAll === 'function') {
        
        console.log(`🔍 ELEMENTO DOM ${i} VÁLIDO:`, componente.$el.tagName)
        
        const todosLosCanvas = componente.$el.querySelectorAll('canvas')
        console.log(`🔍 CANVAS ENCONTRADOS EN COMPONENTE ${i}:`, todosLosCanvas.length)
        
        let canvas = null
        
        // Buscar canvas con contenido
        for (let j = 0; j < todosLosCanvas.length; j++) {
          const testCanvas = todosLosCanvas[j]
          console.log(`🔍 CANVAS ${i}.${j} - DIMENSIONES:`, testCanvas.width, 'x', testCanvas.height)
          
          try {
            const ctx = testCanvas.getContext('2d')
            const imageData = ctx.getImageData(0, 0, testCanvas.width, testCanvas.height)
            const hasContent = imageData.data.some(pixel => pixel !== 0)
            
            console.log(`🔍 CANVAS ${i}.${j} - TIENE CONTENIDO:`, hasContent)
            
            if (hasContent) {
              canvas = testCanvas
              break
            }
          } catch (canvasError) {
            console.error(`❌ Error verificando canvas ${i}.${j}:`, canvasError)
          }
        }
        
        if (canvas) {
          // ✅ FORZAR REDIBUJADO PARA KONVA
          try {
            const stage = canvas.getStage?.()
            if (stage) {
              console.log(`🔄 Forzando redibujado de Konva en ventana ${i}`)
              stage.draw()
              await new Promise(resolve => setTimeout(resolve, 500))
            }
          } catch (e) {
            console.log(`ℹ️ Ventana ${i} no es Konva`)
          }
          
          const base64 = canvas.toDataURL('image/png')
          console.log(`✅ IMAGEN ${i} CAPTURADA VIA CANVAS:`, base64.substring(0, 50))
          imagenes.push(base64)
        } else if (todosLosCanvas.length > 0) {
          // ✅ USAR PRIMER CANVAS AUNQUE ESTÉ VACÍO
          console.log(`🔧 Usando primer canvas aunque esté vacío...`)
          try {
            const base64 = todosLosCanvas[0].toDataURL('image/png')
            imagenes.push(base64)
          } catch (toDataError) {
            console.error(`❌ Error en toDataURL:`, toDataError)
            imagenes.push(null)
          }
        } else {
          console.warn(`⚠️ No se encontraron canvas en componente ${i}`)
          imagenes.push(null)
        }
      } else {
        console.warn(`⚠️ Componente ${i} no tiene $el válido o querySelectorAll`)
        console.log(`🔍 ¿$el existe?:`, !!componente?.$el)
        console.log(`🔍 ¿Es Element?:`, componente?.$el instanceof Element)
        console.log(`🔍 ¿Tiene querySelectorAll?:`, typeof componente?.$el?.querySelectorAll)
        
        // ✅ ÚLTIMO RECURSO: BUSCAR EN DOCUMENT
        console.log(`🔧 Último recurso: buscando canvas globalmente...`)
        const canvasGlobales = document.querySelectorAll('canvas')
        console.log(`🔍 Canvas globales encontrados:`, canvasGlobales.length)
        
        if (canvasGlobales.length > i) {
          try {
            const base64 = canvasGlobales[i].toDataURL('image/png')
            console.log(`✅ IMAGEN ${i} CAPTURADA VIA BÚSQUEDA GLOBAL`)
            imagenes.push(base64)
          } catch (globalError) {
            console.error(`❌ Error en canvas global:`, globalError)
            imagenes.push(null)
          }
        } else {
          imagenes.push(null)
        }
      }
    } catch (error) {
      console.error(`❌ ERROR GENERAL capturando imagen ${i}:`, error)
      imagenes.push(null)
    }
  }

  console.log('🖼️ RESULTADO FINAL:', imagenes.map((img, i) => `${i}: ${img ? 'OK' : 'NULL'}`))
  return imagenes
}

const guardarCotizacion = async () => {
  loading.value = true
  try {
    const imagenes = await exportarImagenesVentanas()
        // ✅ AGREGAR ESTOS LOGS
    console.log('🖼️ IMÁGENES CAPTURADAS:', imagenes)
    console.log('🖼️ NÚMERO DE IMÁGENES:', imagenes.length)
    console.log('🖼️ PRIMERA IMAGEN (primeros 100 chars):', imagenes[0]?.substring(0, 100))
    const clienteSeleccionado = form.cliente?.raw
    if (!clienteSeleccionado || cotizacion.ventanas.length === 0) {
      alert('Debes seleccionar un cliente y agregar al menos una ventana')
      return
    }
    const payload = {
      cliente_id: clienteSeleccionado.id,
      vendedor_id: 1,
      fecha: new Date().toISOString().split('T')[0],
      estado_cotizacion_id: cotizacion.estado_cotizacion_id ?? 1, // default: Evaluacióna
      observaciones: cotizacion.observaciones,
      imagenes_ventanas: imagenes, // base64 strings
      ventanas: cotizacion.ventanas.map(v => {
        const relacion = buscarRelacionVidrioProveedor(v.productoVidrioProveedor)
        return {
          tipo_ventana_id: v.tipo,
          ancho: v.ancho,
          alto: v.alto,
          cantidad: v.cantidad,
          color_id: v.color,
          producto_vidrio_proveedor_id: v.productoVidrioProveedor,  
          producto_id: relacion?.producto_id,
          proveedor_id: relacion?.proveedor_id,
          costo: v.costo_total || v.costo || 0,
          costo_unitario: v.costo_unitario || 0,
          precio: v.precio || 0,
          precio_unitario: v.precio_unitario || 0,
          tipo_ventana_izquierda: v.tipoVentanaIzquierda ?? null,
          tipo_ventana_centro: v.tipoVentanaCentro ?? null,
          tipo_ventana_derecha: v.tipoVentanaDerecha ?? null,
          ancho_izquierda: v.ancho_izquierda ?? null,
          ancho_centro: v.ancho_centro ?? null,
          ancho_derecha: v.ancho_derecha ?? null,

        }
      }),
    }
        // ✅ AGREGAR ESTE LOG
    console.log('📤 PAYLOAD A ENVIAR:', payload)
    console.log('📤 VENTANAS ESPECÍFICAS:', payload.ventanas)

    await api.post('api/cotizaciones', payload)

    alert('Cotización guardada correctamente')
    router.push({ name: 'cotizaciones' })

  } catch (error) {
    console.error('Error al guardar cotización:', error)
    alert('Error al guardar la cotización')
  } finally {
    loading.value = false
  }
}





</script>



<style scoped>
.v-card-subtitle {
  font-weight: 600;
}
</style>
