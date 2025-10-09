<template>
  <v-container fluid>
    <v-card class="pa-6" elevation="2">
      <v-card-title class="text-h5 mb-4">
        {{ modoEdicion ? 'Editar Cotización' : 'Nueva Cotización' }}
      </v-card-title>
      

      <!-- Datos Generales -->
      <v-card-subtitle class="text-h5">Datos generales</v-card-subtitle>
      <v-divider class="mb-4" />

      <!-- Cliente + botón en fila -->
      <v-row dense>
        <v-col cols="6" md="4">
          <v-row no-gutters align="center">
            <v-col>
              <!-- BUSCADOR TÍPICO CON DROPDOWN -->
              <div style="position: relative;">
                <v-text-field
                  v-model="terminoBusquedaCliente"
                  @input="buscarClientesSimple"
                  @focus="onFocusBuscador"
                  @click:clear="limpiarBusqueda"
                  label="Cliente"
                  placeholder="Buscar por RUT o nombre..."
                  outlined
                  clearable
                  :loading="buscandoClientes"
                  color="primary"
                  :append-inner-icon="form.cliente ? 'mdi-check-circle' : 'mdi-magnify'"
                  :hint="form.cliente ? `Seleccionado: ${form.cliente.razon_social}` : ''"
                  persistent-hint
                />
                
                <!-- DROPDOWN DE RESULTADOS -->
                <v-card
                  v-if="mostrarDropdown && clientesBuscados.length > 0"
                  style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 300px; overflow-y: auto;"
                  class="mt-1"
                  elevation="8"
                >
                  <v-list density="compact">
                    <v-list-item
                      v-for="cliente in clientesBuscados"
                      :key="cliente.id"
                      @click="seleccionarCliente(cliente)"
                      class="cursor-pointer"
                      hover
                    >
                      <template v-slot:prepend>
                        <v-icon>mdi-account</v-icon>
                      </template>
                      <v-list-item-title>{{ cliente.razon_social }}</v-list-item-title>
                      <v-list-item-subtitle>{{ cliente.identification }}</v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-card>
              </div>
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

      <!-- Lista de Ventanas -->
      <v-card-subtitle class="text-h5">Items de la Cotización</v-card-subtitle>
      <v-divider class="mb-4" />

      <!-- Botones para agregar items -->
      <v-row class="mb-4">
        <v-col cols="auto">
          <v-btn color="primary" @click="toggleSeccionVentana" :disabled="!tiposVentanaTodos.length">
            <v-icon left>mdi-window-closed-variant</v-icon>
            Ventana
          </v-btn>
        </v-col>
        <v-col cols="auto">
          <v-btn color="success" @click="abrirModalProductos">
            <v-icon left>mdi-package-variant</v-icon>
            Productos
          </v-btn>
        </v-col>
      </v-row>

      <!-- Sección colapsable de pre-configuración de ventanas -->
      <v-expand-transition>
        <v-card v-if="mostrarSeccionVentana" class="mb-4" outlined>
          <v-card-text>
            <v-card-subtitle class="text-subtitle-1 font-weight-bold">Pre-configuración de Ventana</v-card-subtitle>
            <v-row dense class="mt-2">
              <v-col cols="12" md="6">
                <v-select
                  v-model="cotizacion.material"
                  :items="materiales"
                  item-title="nombre"
                  item-value="id"
                  label="Material"
                  outlined
                  dense
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
                  dense
                  color="primary"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.tipoVidrio"
                  :items="tiposVidrio"
                  item-title="nombre"
                  item-value="id"
                  label="Tipo de vidrio"
                  outlined
                  dense
                  color="primary"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.productoVidrioProveedor"
                  :items="productosVidrioFiltradosGeneral"
                  item-title="nombre"
                  item-value="id"
                  label="Producto de vidrio"
                  outlined
                  dense
                  color="primary"
                />
              </v-col>
            </v-row>
            
            <v-btn color="primary" @click="abrirModalVentana" block class="mt-2">
              <v-icon left>mdi-plus</v-icon>
              Agregar Ventana
            </v-btn>
          </v-card-text>
        </v-card>
      </v-expand-transition>

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

      <!-- Tabla de productos -->
      <v-data-table
        v-if="cotizacion.productos.length > 0"
        :headers="headersProductos"
        :items="cotizacion.productos"
        class="mt-4"
        :items-per-page="5"
      >
        <template #top>
          <v-toolbar flat color="transparent">
            <v-toolbar-title class="text-subtitle-1">Productos</v-toolbar-title>
          </v-toolbar>
        </template>

        <template #item.precio_costo="{ item }">
          ${{ formatearNumero(item.precio_costo) }}
        </template>

        <template #item.margen="{ item }">
          {{ item.margen }}%
        </template>

        <template #item.precio_venta="{ item }">
          ${{ formatearNumero(item.precio_venta) }}
        </template>

        <template #item.acciones="{ item, index }">
          <v-btn icon color="error" @click="eliminarProducto(index)">
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

      <!-- Modal para agregar productos -->
      <ModalProductos
        v-model:mostrar="mostrarModalProductos"
        @agregar-productos="agregarProductosCotizacion"
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
        {{ modoEdicion ? 'Guardar Cambios' : 'Guardar Cotización' }}
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

  <!-- Modal para crear cliente LOCAL -->
  <v-dialog v-model="modalCliente" max-width="600px">
    <v-card>
      <v-card-title class="text-h5 pa-4">
        <v-icon class="mr-2" color="primary">mdi-account-plus</v-icon>
        Crear Cliente Nuevo
      </v-card-title>
      <v-divider></v-divider>
      <v-card-text class="pa-4">
        <v-alert type="info" variant="tonal" class="mb-4">
          Este cliente se guardará en tu base de datos local. Para facturar, deberás seleccionar un cliente sincronizado con Bsale.
        </v-alert>
        <v-form ref="formCliente">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="nuevoCliente.razon_social"
                label="Razón Social / Nombre *"
                :rules="[v => !!v || 'Razón social es requerida']"
                required
                outlined
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.identification"
                label="RUT"
                outlined
                hint="Formato: 12345678-9"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.email"
                label="Email"
                type="email"
                outlined
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.phone"
                label="Teléfono"
                outlined
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.ciudad"
                label="Ciudad"
                outlined
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="nuevoCliente.address"
                label="Dirección"
                outlined
              ></v-text-field>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          text
          @click="modalCliente = false"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          @click="guardarCliente"
        >
          <v-icon left>mdi-content-save</v-icon>
          Guardar Cliente
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
 
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'
import { useRouter, useRoute } from 'vue-router'
import Visor3D from '@/layouts/components/Visor3D.vue'
import { color } from 'three/src/nodes/TSL.js'
import VistaVentanaCorrederaAndes from '@/components/VistaVentanaCorrederaAndes.vue'
import AgregarVentanaModal from '@/pages/AgregarVentanaModal2.vue'
import ModalProductos from '@/pages/ModalProductos.vue'
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
const route = useRoute()

// Detectar modo edición
const modoEdicion = ref(false)
const cotizacionId = ref(null)

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
  productos: [], // Productos agregados a la cotización
       
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
const clientesBuscados = ref([])
const buscandoClientes = ref(false)
const clienteAutocomplete = ref(null)
const terminoBusquedaCliente = ref('')
const mostrarDropdown = ref(false)

const clienteSearch = ref('')
const modalCliente = ref(false)
const comboboxKey = ref(0)

const nuevoCliente = ref({
  razon_social: '',
  identification: '',
  email: '',
  phone: '',
  address: '',
  ciudad: '',
  comuna: '',
  giro: ''
})

const productosVidrioCombinados = computed(() => {
  return productosVidrio.value.flatMap(p => {
    if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
      return []
    }
    return p.colores_por_proveedor.map(cpp => ({
      id: `${p.id}-${cpp.proveedor_id}`,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id,
      nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
    }))
  })
})

const productosVidrioFiltradosGeneral = computed(() => {
  const tipo = cotizacion.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p => {
      // Validar que colores_por_proveedor exista y sea un array
      if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
        return []
      }
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,  // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    })
})

onMounted(async () => {
  console.log('🔄 Iniciando carga de datos...')
  
  // Detectar modo edición
  if (route.query.id) {
    modoEdicion.value = true
    cotizacionId.value = route.query.id
    console.log('📝 MODO EDICIÓN activado - ID:', cotizacionId.value)
  }
  
  try {
    // Cargar datos básicos (rápido)
    const [
      materialesRes, coloresRes, tiposProductoRes,
      productosRes, tiposVentanaRes
    ] = await Promise.all([
      api.get('/api/tipos_material'),
      api.get('/api/colores'),
      api.get('/api/tipos_producto'),
      api.get('/api/productos'),
      api.get('/api/tipos_ventana')
    ])
    
    console.log('✅ Datos básicos cargados')

  materiales.value = materialesRes.data
  colores.value = coloresRes.data
  tiposVidrio.value = tiposProductoRes.data.filter(tp => [1, 2].includes(tp.id))
  productosVidrio.value = productosRes.data.filter(p => [1, 2].includes(p.tipo_producto_id))
  
  console.log('📦 PRODUCTOS DE VIDRIO CARGADOS:', productosVidrio.value.length)
  console.log('📦 Primer producto:', productosVidrio.value[0])
  if (productosVidrio.value[0]) {
    console.log('   - Tiene colores_por_proveedor?', !!productosVidrio.value[0].colores_por_proveedor)
    console.log('   - Es array?', Array.isArray(productosVidrio.value[0].colores_por_proveedor))
    console.log('   - Cantidad:', productosVidrio.value[0].colores_por_proveedor?.length)
  }
  
  tiposVentanaTodos.value = tiposVentanaRes.data
  console.log('TIPOS VENTANA CARGADOS:', tiposVentanaTodos.value)
  
  // Cargar solo los primeros clientes (rápido)
  console.log('🔄 Cargando primeros clientes...')
  cargarClientesIniciales()
  
  // SI ESTÁ EN MODO EDICIÓN, CARGAR LA COTIZACIÓN
  if (modoEdicion.value) {
    await cargarCotizacionExistente()
  }
  
  // Cerrar dropdown al hacer clic fuera
  document.addEventListener('click', (e) => {
    const target = e.target
    if (!target.closest('.v-text-field') && !target.closest('.v-card')) {
      mostrarDropdown.value = false
    }
  })
  
  } catch (error) {
    console.error('❌ Error cargando datos:', error)
    alert('Error cargando datos: ' + error.message)
  }
})

const buscarRelacionVidrioProveedor = (id) => {
  id = parseInt(id)
  return productosVidrio.value.flatMap(p => {
    if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
      return []
    }
    return p.colores_por_proveedor.map(cpp => ({
      id: cpp.id,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id
    }))
  }).find(p => p.id === id)
}

// Función para cargar cotización existente en modo edición
const cargarCotizacionExistente = async () => {
  try {
    console.log('📥 Cargando cotización ID:', cotizacionId.value)
    const response = await api.get(`/api/cotizaciones/${cotizacionId.value}`)
    const cotizacionData = response.data
    
    console.log('✅ Cotización cargada:', cotizacionData)
    console.log('📦 DETALLES COMPLETOS:', cotizacionData.detalles)
    
    // Poblar cliente
    if (cotizacionData.cliente) {
      form.cliente = cotizacionData.cliente
      cotizacion.cliente_id = cotizacionData.cliente.id
      terminoBusquedaCliente.value = cotizacionData.cliente.razon_social
    }
    
    // Poblar observaciones
    cotizacion.observaciones = cotizacionData.observaciones || ''
    
    // Poblar ventanas
    if (cotizacionData.ventanas && cotizacionData.ventanas.length > 0) {
      cotizacion.ventanas = cotizacionData.ventanas.map(v => {
        const relacion = buscarRelacionVidrioProveedor(v.producto_vidrio_proveedor_id)
        
        return {
          tipo: v.tipo_ventana_id,
          ancho: v.ancho,
          alto: v.alto,
          cantidad: v.cantidad || 1,
          color: v.color_id,
          tipoVidrio: v.tipo_vidrio_id,
          productoVidrioProveedor: v.producto_vidrio_proveedor_id,
          productoVidrio: relacion?.producto_id,
          proveedorVidrio: relacion?.proveedor_id,
          costo: v.costo || 0,
          costo_unitario: v.costo_unitario || 0,
          costo_total: v.costo || 0,
          precio: v.precio || 0,
          precio_unitario: v.precio_unitario || 0,
          hojas_totales: v.hojas_totales,
          hojas_moviles: v.hojas_moviles,
          materiales: v.materiales || [],
          // Para ventanas compuestas
          tipoVentanaIzquierda: v.tipo_ventana_izquierda,
          tipoVentanaCentro: v.tipo_ventana_centro,
          tipoVentanaDerecha: v.tipo_ventana_derecha,
          ancho_izquierda: v.ancho_izquierda,
          ancho_centro: v.ancho_centro,
          ancho_derecha: v.ancho_derecha,
          // ID para actualización
          id: v.id
        }
      })
      
      console.log('✅ Ventanas cargadas:', cotizacion.ventanas.length)
    }
    
    // Poblar productos
    if (cotizacionData.detalles && cotizacionData.detalles.length > 0) {
      cotizacion.productos = cotizacionData.detalles
        .filter(d => d.tipo_item === 'producto')
        .map(p => {
          console.log('📦 Detalle del producto:', p)
          
          // Obtener info del producto desde las relaciones (manejar snake_case y camelCase)
          const productoInfo = p.producto_lista || p.productoLista || {}
          const listaPrecioInfo = p.lista_precio || p.listaPrecio || {}
          const tipoProductoInfo = productoInfo.tipo_producto || productoInfo.tipoProducto || {}
          const unidadInfo = productoInfo.unidad || {}
          
          return {
            id: p.id, // ID para actualización
            producto_lista_id: p.producto_lista_id,
            lista_precio_id: p.lista_precio_id,
            descripcion: p.descripcion,
            cantidad: p.cantidad,
            precio_venta: p.precio_unitario,
            total: p.total,
            // Información adicional del producto
            codigo: productoInfo.codigo || '',
            nombre: productoInfo.nombre || p.descripcion,
            tipo: tipoProductoInfo.nombre || '',
            unidad: unidadInfo.nombre || unidadInfo.simbolo || '',
            precio_costo: listaPrecioInfo.precio_costo || 0,
            margen: listaPrecioInfo.margen || 0,
          }
        })
      
      console.log('✅ Productos cargados:', cotizacion.productos.length)
      console.log('📦 Primer producto mapeado:', cotizacion.productos[0])
    }
    
  } catch (error) {
    console.error('❌ Error cargando cotización:', error)
    alert('Error al cargar la cotización')
    router.push({ name: 'cotizaciones' })
  }
}

// Computed para mostrar productos de vidrio con proveedor
const productosVidrioFiltradosConProveedor = (ventana) => {
  const tipo = ventana.tipoVidrio ?? cotizacion.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p => {
      if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
        return []
      }
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id, // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    })
}

// Función de clientes filtrados eliminada - ahora usamos búsqueda async

// Ventanas
const mostrarModalVentana = ref(false)
const mostrarSeccionVentana = ref(false)
const mostrarModalProductos = ref(false)
const ventanaEnEdicion = ref(null)

const headersVentanas = [
  { title: 'Tipo', key: 'tipo' },
  { title: 'Ancho', key: 'ancho' },
  { title: 'Alto', key: 'alto' },
  { title: 'Cantidad', key: 'cantidad' },
  { title: 'Precio', key: 'precio', align: 'end' },
  { title: 'Acciones', key: 'acciones', sortable: false },
]

const headersProductos = [
  { title: 'Código', key: 'codigo' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Tipo', key: 'tipo_producto' },
  { title: 'Unidad', key: 'unidad' },
  { title: 'Cantidad', key: 'cantidad' },
  { title: 'Precio Costo', key: 'precio_costo', align: 'end' },
  { title: 'Margen', key: 'margen', align: 'center' },
  { title: 'Precio Venta', key: 'precio_venta', align: 'end' },
  { title: 'Acciones', key: 'acciones', sortable: false },
]

const abrirModalVentana = () => {
  ventanaEnEdicion.value = null // Para agregar nueva
  mostrarModalVentana.value = true
}

const toggleSeccionVentana = () => {
  mostrarSeccionVentana.value = !mostrarSeccionVentana.value
}

const abrirModalProductos = () => {
  mostrarModalProductos.value = true
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

const agregarProductosCotizacion = (productos) => {
  console.log('📦 PRODUCTOS RECIBIDOS DEL MODAL:', productos)
  // Agregar productos al arreglo de la cotización
  productos.forEach(item => {
    const productoParaCotizacion = {
      producto_lista_id: item.producto_lista_id, // ✅ ID del producto
      lista_precio_id: item.lista_precio_id, // ✅ ID de la lista de precios
      nombre: item.nombre || item.producto?.nombre,
      codigo: item.codigo || item.producto?.codigo_proveedor,
      tipo_producto: item.tipo || item.producto?.tipoProducto?.nombre || '-',
      unidad: item.unidad || item.producto?.unidad?.abreviacion || '-',
      cantidad: item.cantidad,
      precio_costo: item.precio_costo,
      margen: item.margen,
      precio_venta: item.precio_venta,
      descripcion: item.descripcion || item.nombre || item.producto?.nombre || '',
      total: item.precio_venta * item.cantidad
    }
    
    console.log('✅ PRODUCTO AGREGADO A COTIZACIÓN:', productoParaCotizacion)
    cotizacion.productos.push(productoParaCotizacion)
  })
}

const eliminarProducto = (index) => {
  cotizacion.productos.splice(index, 1)
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL').format(numero || 0)
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
  console.log('✅ Cliente seleccionado:', cliente)
  if (cliente) {
    console.log('✅ Nombre:', cliente.razon_social)
    console.log('✅ RUT:', cliente.identification)
  }
})

const abrirModalCliente = () => {
  modalCliente.value = true
}

// FUNCIONES SIMPLES QUE SÍ FUNCIONAN
const buscarClientesSimple = async () => {
  const query = terminoBusquedaCliente.value?.trim()
  console.log('🔍 BÚSQUEDA LOCAL:', query)
  
  // Si el usuario empieza a escribir de nuevo, limpiar la selección anterior
  if (form.cliente && query !== form.cliente.razon_social) {
    form.cliente = null
    cotizacion.cliente_id = null
  }
  
  if (!query || query.length < 2) {
    clientesBuscados.value = []
    mostrarDropdown.value = false
    return
  }
  
  buscandoClientes.value = true
  mostrarDropdown.value = true
  
  try {
    // Ahora busca en la base de datos local en lugar de Bsale
    const response = await api.get(`/api/clientes/buscar?q=${encodeURIComponent(query)}`)
    console.log('✅ RESPUESTA LOCAL:', response.data)
    
    if (response.data?.length > 0) {
      clientesBuscados.value = response.data.map(cliente => ({
        id: cliente.id,
        razon_social: cliente.razon_social || `${cliente.first_name || ''} ${cliente.last_name || ''}`.trim() || 'Sin nombre',
        identification: cliente.identification || '',
        email: cliente.email || '',
        phone: cliente.phone || ''
      }))
      console.log('✅ CLIENTES PROCESADOS:', clientesBuscados.value)
      mostrarDropdown.value = true
    } else {
      clientesBuscados.value = []
      mostrarDropdown.value = false
      console.log('❌ NO HAY CLIENTES')
    }
  } catch (error) {
    console.error('❌ ERROR:', error)
    clientesBuscados.value = []
    mostrarDropdown.value = false
  } finally {
    buscandoClientes.value = false
  }
}

const seleccionarCliente = (cliente) => {
  console.log('✅ CLIENTE SELECCIONADO:', cliente)
  form.cliente = cliente
  cotizacion.cliente_id = cliente.id // ✅ Actualizar el ID en cotización
  terminoBusquedaCliente.value = cliente.razon_social // Mostrar el nombre en el input
  mostrarDropdown.value = false // Ocultar dropdown
  clientesBuscados.value = [] // Limpiar resultados
}

const onFocusBuscador = () => {
  // Mostrar dropdown si hay resultados
  if (clientesBuscados.value.length > 0) {
    mostrarDropdown.value = true
  }
}

const limpiarBusqueda = () => {
  terminoBusquedaCliente.value = ''
  clientesBuscados.value = []
  mostrarDropdown.value = false
  form.cliente = null
}

const guardarCliente = async () => {
  try {
    console.log('💾 Guardando cliente local:', nuevoCliente.value)
    
    // Guardar en la tabla local de clientes
    const res = await api.post('/api/clientes', nuevoCliente.value)
    
    console.log('✅ Respuesta completa:', res.data)
    
    // La API devuelve { message, cliente }
    const clienteCreado = res.data.cliente || res.data
    
    console.log('✅ Cliente creado:', clienteCreado)
    
    // Agregar a la lista de clientes disponibles
    clientesBuscados.value.push(clienteCreado)
    
    // Seleccionar el cliente recién creado usando el mismo flujo que seleccionarCliente
    form.cliente = clienteCreado
    cotizacion.cliente_id = clienteCreado.id
    terminoBusquedaCliente.value = clienteCreado.razon_social
    mostrarDropdown.value = false
    clientesBuscados.value = []
    
    // Cerrar modal y limpiar formulario
    modalCliente.value = false
    nuevoCliente.value = {
      razon_social: '',
      identification: '',
      email: '',
      phone: '',
      address: '',
      ciudad: ''
    }
    
    console.log('✅ Cliente seleccionado:', form.cliente)
    console.log('✅ Cliente ID asignado:', cotizacion.cliente_id)
    console.log('✅ Término búsqueda:', terminoBusquedaCliente.value)
    
    alert('✅ Cliente creado y seleccionado exitosamente')
  } catch (error) {
    console.error('❌ Error al crear cliente:', error)
    alert('❌ Error al crear cliente: ' + (error.response?.data?.message || error.message))
  }
}

// Función de búsqueda de clientes con debounce
const buscarClientes = async (query) => {
  // Si no hay query, usar el término de búsqueda del input
  if (!query) {
    query = terminoBusquedaCliente.value
  }
  console.log('🔍 Buscando clientes con query:', query)
  
  if (!query || query.length < 2) {
    // Si no hay búsqueda, mostrar los clientes iniciales
    clientesBuscados.value = clientes.value.slice(0, 20)
    console.log('📋 Mostrando clientes iniciales:', clientesBuscados.value.length)
    return
  }

  buscandoClientes.value = true
  
  try {
    console.log('🌐 Buscando en API de Bsale...')
    const response = await api.get(`/api/bsale-clientes/buscar?q=${encodeURIComponent(query)}`)
    
    console.log('✅ Respuesta de API:', response.data)
    
    if (response.data && response.data.items && response.data.items.length > 0) {
      clientesBuscados.value = response.data.items.map(cliente => {
        console.log('🔍 Procesando cliente:', cliente)
        
        // Construir razon_social de manera más robusta
        let razonSocial = ''
        if (cliente.company && cliente.company.trim()) {
          razonSocial = cliente.company.trim()
        } else if (cliente.firstName || cliente.lastName) {
          razonSocial = `${cliente.firstName || ''} ${cliente.lastName || ''}`.trim()
        } else if (cliente.razon_social) {
          razonSocial = cliente.razon_social
        } else if (cliente.displayName) {
          razonSocial = cliente.displayName
        } else {
          razonSocial = 'Cliente sin nombre'
        }
        
        // Asegurarse de que no esté vacío
        if (!razonSocial || razonSocial.trim() === '') {
          razonSocial = `Cliente ID: ${cliente.id}`
        }
        
        const clienteProcesado = {
          id: cliente.id,
          razon_social: razonSocial,
          identification: cliente.identification || '',
          email: cliente.email || '',
          phone: cliente.phone || '',
          address: cliente.address || '',
          city: cliente.city || '',
          municipality: cliente.municipality || '',
          first_name: cliente.firstName || '',
          last_name: cliente.lastName || '',
          company: cliente.company || '',
          tipo_cliente: cliente.companyOrPerson == 1 ? 'empresa' : 'persona'
        }
        
        console.log('✅ Cliente procesado:', clienteProcesado)
        return clienteProcesado
      })
      
      console.log('✅ Total clientes procesados:', clientesBuscados.value.length)
      console.log('✅ Lista final:', clientesBuscados.value)
      
      // Verificar estructura para autocomplete
      if (clientesBuscados.value.length > 0) {
        console.log('🔍 Primer cliente para autocomplete:', {
          id: clientesBuscados.value[0].id,
          razon_social: clientesBuscados.value[0].razon_social,
          hasId: !!clientesBuscados.value[0].id,
          hasTitle: !!clientesBuscados.value[0].razon_social
        })
        
        // Forzar que se abra el menú después de un pequeño delay
        setTimeout(() => {
          if (clienteAutocomplete.value && clienteAutocomplete.value.menu) {
            console.log('🎯 Forzando apertura del menú...')
            clienteAutocomplete.value.menu = true
          }
        }, 100)
      }
    } else {
      console.log('❌ No se encontraron clientes en la respuesta')
      clientesBuscados.value = []
    }
    
  } catch (error) {
    console.error('❌ Error en búsqueda:', error)
    console.error('❌ Detalles del error:', error.response?.data)
    clientesBuscados.value = []
  } finally {
    buscandoClientes.value = false
  }
}

const buscarClientesDebounced = debounce(buscarClientes, 300)

// Texto dinámico para cuando no hay resultados
const getNoDataText = () => {
  if (buscandoClientes.value) {
    return 'Buscando clientes...'
  }
  if (!clienteSearch.value || clienteSearch.value.length < 2) {
    return 'Escribe al menos 2 caracteres para buscar'
  }
  return 'No se encontraron clientes con ese criterio'
}

// Función para cargar solo los primeros clientes (rápido)
const cargarClientesIniciales = async () => {
  try {
    // Cargar solo los primeros 50 clientes (límite de Bsale por página)
    const response = await api.get('/api/bsale-clientes?limit=50&offset=0')
    console.log('✅ Primeros clientes cargados:', response.data)
    
    const clientesProcesados = response.data.items?.map(cliente => ({
      id: cliente.id,
      razon_social: cliente.razon_social || cliente.displayName || 'Sin nombre',
      identification: cliente.identification,
      email: cliente.email,
      phone: cliente.phone,
      address: cliente.address,
      city: cliente.city,
      municipality: cliente.municipality,
      first_name: cliente.firstName,
      last_name: cliente.lastName,
      company: cliente.company,
      tipo_cliente: cliente.companyOrPerson == 1 ? 'empresa' : 'persona'
    })) || []
    
    clientes.value = clientesProcesados
    clientesBuscados.value = clientesProcesados.slice(0, 20)
    
    console.log('✅ Clientes iniciales listos:', clientes.value.length)
  } catch (error) {
    console.error('❌ Error cargando clientes iniciales:', error)
    clientesBuscados.value = []
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
    const clienteSeleccionado = form.cliente
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
          id: v.id, // ✅ Incluir ID para actualización
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
      productos: (cotizacion.productos || []).map(p => ({
        id: p.id, // ✅ Incluir ID para actualización
        producto_lista_id: p.producto_lista_id,
        lista_precio_id: p.lista_precio_id,
        descripcion: p.descripcion || p.nombre || '',
        cantidad: p.cantidad,
        precio_unitario: p.precio_venta / p.cantidad, // Precio unitario
        total: p.total || (p.precio_venta * p.cantidad)
      })),
    }
        // ✅ AGREGAR ESTE LOG
    console.log('📤 PAYLOAD A ENVIAR:', payload)
    console.log('📤 VENTANAS ESPECÍFICAS:', payload.ventanas)
    console.log('📤 PRODUCTOS ESPECÍFICOS:', payload.productos)

    // ✅ USAR PUT SI ESTÁ EN MODO EDICIÓN, POST SI ES NUEVA
    if (modoEdicion.value) {
      console.log('🔄 Actualizando cotización existente ID:', cotizacionId.value)
      await api.put(`/api/cotizaciones/${cotizacionId.value}`, payload)
      alert('Cotización actualizada correctamente')
    } else {
      console.log('✨ Creando nueva cotización')
      await api.post('/api/cotizaciones', payload)
      alert('Cotización guardada correctamente')
    }

    router.push({ name: 'cotizaciones' })

  } catch (error) {
    console.error('Error al guardar cotización:', error)
    
    // Mostrar mensaje específico si es error de cliente
    if (error.response?.data?.message) {
      alert(error.response.data.message)
    } else {
      alert('Error al guardar la cotización')
    }
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
