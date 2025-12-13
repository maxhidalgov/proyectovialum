<template>
  <div class="armador-container">
    <!-- Toolbar Superior -->
    <div class="toolbar-top">
      <v-btn-group density="compact" variant="outlined">
        <v-btn icon @click="deshacer" :disabled="!puedeDeshacer">
          <v-icon>mdi-undo</v-icon>
          <v-tooltip activator="parent">Deshacer</v-tooltip>
        </v-btn>
        <v-btn icon @click="rehacer" :disabled="!puedeRehacer">
          <v-icon>mdi-redo</v-icon>
          <v-tooltip activator="parent">Rehacer</v-tooltip>
        </v-btn>
        <v-btn icon @click="eliminarSeleccion" :disabled="!elementoSeleccionado">
          <v-icon>mdi-delete</v-icon>
          <v-tooltip activator="parent">Eliminar</v-tooltip>
        </v-btn>
        <v-btn icon @click="limpiarTodo">
          <v-icon>mdi-broom</v-icon>
          <v-tooltip activator="parent">Limpiar</v-tooltip>
        </v-btn>
        <v-btn icon @click="guardar">
          <v-icon>mdi-content-save</v-icon>
          <v-tooltip activator="parent">Guardar</v-tooltip>
        </v-btn>
      </v-btn-group>

      <v-chip class="ml-4">
        {{ anchoTotal }}mm × {{ altoTotal }}mm
      </v-chip>
    </div>

    <div class="armador-layout">
      <!-- Toolbar Izquierdo (Herramientas de Dibujo) -->
      <div class="toolbar-left">
        <div class="toolbar-buttons">
          <!-- Herramienta Seleccionar -->
          <v-btn 
            :variant="herramientaActiva === 'seleccionar' ? 'flat' : 'text'"
            :color="herramientaActiva === 'seleccionar' ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = 'seleccionar'"
          >
            <v-icon size="x-large">mdi-cursor-default</v-icon>
            <v-tooltip activator="parent" location="right">Seleccionar</v-tooltip>
          </v-btn>

          <!-- Herramienta Rectángulo -->
          <v-btn 
            :variant="herramientaActiva === 'rectangulo' ? 'flat' : 'text'"
            :color="herramientaActiva === 'rectangulo' ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = 'rectangulo'"
          >
            <v-icon size="x-large">mdi-rectangle-outline</v-icon>
            <v-tooltip activator="parent" location="right">Dibujar Marco</v-tooltip>
          </v-btn>

          <v-divider class="my-3" />

          <!-- Herramienta Divisor Horizontal -->
          <v-btn 
            :variant="herramientaActiva === 'divisor-h' ? 'flat' : 'text'"
            :color="herramientaActiva === 'divisor-h' ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = 'divisor-h'"
          >
            <v-icon size="x-large">mdi-minus</v-icon>
            <v-tooltip activator="parent" location="right">Divisor Horizontal</v-tooltip>
          </v-btn>

          <!-- Herramienta Divisor Vertical -->
          <v-btn 
            :variant="herramientaActiva === 'divisor-v' ? 'flat' : 'text'"
            :color="herramientaActiva === 'divisor-v' ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = 'divisor-v'"
          >
            <v-icon size="x-large" style="transform: rotate(90deg)">mdi-minus</v-icon>
            <v-tooltip activator="parent" location="right">Divisor Vertical</v-tooltip>
          </v-btn>

          <v-divider class="my-3" />

          <!-- Herramienta Vidrio -->
          <v-btn 
            :variant="herramientaActiva === 'vidrio' ? 'flat' : 'text'"
            :color="herramientaActiva === 'vidrio' ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = 'vidrio'"
          >
            <v-icon size="x-large">mdi-square</v-icon>
            <v-tooltip activator="parent" location="right">Seleccionar Vidrio</v-tooltip>
          </v-btn>

          <v-divider class="my-3" />

          <!-- Tipos de Ventana (primeros 6) -->
          <v-btn 
            v-for="tipo in tiposVentana.slice(0, 6)" 
            :key="tipo.id"
            :variant="herramientaActiva === `tipo-${tipo.id}` ? 'flat' : 'text'"
            :color="herramientaActiva === `tipo-${tipo.id}` ? 'primary' : 'default'"
            icon 
            size="x-large"
            class="tool-btn mb-2"
            @click="herramientaActiva = `tipo-${tipo.id}`"
          >
            <v-icon size="x-large">mdi-window-closed-variant</v-icon>
            <v-tooltip activator="parent" location="right">{{ tipo.nombre }}</v-tooltip>
          </v-btn>

          <!-- Más tipos de ventana -->
          <v-menu v-if="tiposVentana.length > 6" location="right">
            <template #activator="{ props }">
              <v-btn 
                icon 
                size="x-large" 
                class="tool-btn"
                v-bind="props"
              >
                <v-icon size="x-large">mdi-dots-horizontal</v-icon>
                <v-tooltip activator="parent" location="right">Más tipos</v-tooltip>
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item
                v-for="tipo in tiposVentana.slice(6)"
                :key="tipo.id"
                @click="herramientaActiva = `tipo-${tipo.id}`"
              >
                <template #prepend>
                  <v-icon>mdi-window-closed-variant</v-icon>
                </template>
                <v-list-item-title>{{ tipo.nombre }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </div>
      </div>

      <!-- Canvas Central - Solo para diseñar estructura -->
      <div class="canvas-area" ref="canvasContainer">
        <v-stage
          ref="stage"
          :config="stageConfig"
          @mousedown="handleMouseDown"
          @mousemove="handleMouseMove"
          @mouseup="handleMouseUp"
        >
          <v-layer ref="mainLayer" @click="handleCanvasClick">
            <!-- Fondo -->
            <v-rect :config="fondoConfig" />

            <!-- Renderizar Marcos -->
            <v-group
              v-for="marco in marcos"
              :key="marco.id"
            >
              <!-- Marco exterior -->
              <v-rect
                :config="{
                  x: marco.x,
                  y: marco.y,
                  width: marco.ancho,
                  height: marco.alto,
                  fill: '#6B7280',
                  stroke: elementoSeleccionado?.id === marco.id ? '#FF5722' : '#374151',
                  strokeWidth: elementoSeleccionado?.id === marco.id ? 3 : 2,
                  listening: true,
                }"
                @click="seleccionarElemento(marco)"
              />

              <!-- Área interna (vidrio) -->
              <v-rect
                :config="{
                  x: marco.x + marco.anchoMarco,
                  y: marco.y + marco.anchoMarco,
                  width: marco.ancho - marco.anchoMarco * 2,
                  height: marco.alto - marco.anchoMarco * 2,
                  fill: marco.colorVidrio || '#00FF0080',
                  stroke: '#999',
                  strokeWidth: 1,
                  listening: true,
                  name: `vidrio-${marco.id}`,
                }"
                @click="(e) => seleccionarVidrio(marco, e)"
                @mouseenter="handleMouseEnterVidrio"
                @mouseleave="handleMouseLeaveVidrio"
              />

              <!-- Renderizar divisores del marco -->
              <template v-for="divisor in getDivisoresDelMarco(marco.id)" :key="divisor.id">
                <v-line
                  :config="{
                    points: getDivisorPoints(divisor, marco),
                    stroke: elementoSeleccionado?.id === divisor.id ? '#FF5722' : '#374151',
                    strokeWidth: divisor.ancho || 40,
                    listening: true,
                  }"
                  @click="(e) => { e.cancelBubble = true; seleccionarElemento(divisor); }"
                />
                
                <!-- Línea de guía visual -->
                <v-line
                  v-if="divisor.orientacion === 'horizontal'"
                  :config="{
                    points: [
                      marco.x,
                      divisor.posicion,
                      marco.x + marco.ancho,
                      divisor.posicion,
                    ],
                    stroke: '#2196F3',
                    strokeWidth: 2,
                    dash: [5, 5],
                    listening: false,
                  }"
                />
                <v-line
                  v-if="divisor.orientacion === 'vertical'"
                  :config="{
                    points: [
                      divisor.posicion,
                      marco.y,
                      divisor.posicion,
                      marco.y + marco.alto,
                    ],
                    stroke: '#2196F3',
                    strokeWidth: 2,
                    dash: [5, 5],
                    listening: false,
                  }"
                />
              </template>

              <!-- Etiquetas de dimensiones -->
              <v-text
                :config="{
                  x: marco.x + marco.ancho / 2,
                  y: marco.y - 20,
                  text: `${Math.round(calcularAnchoMm(marco.ancho))}mm`,
                  fontSize: 12,
                  fill: '#2196F3',
                  align: 'center',
                  listening: false,
                }"
              />
              <v-text
                :config="{
                  x: marco.x - 50,
                  y: marco.y + marco.alto / 2,
                  text: `${Math.round(calcularAltoMm(marco.alto))}mm`,
                  fontSize: 12,
                  fill: '#2196F3',
                  align: 'center',
                  rotation: -90,
                  listening: false,
                }"
              />

              <!-- Áreas de asignación de tipos (solo visualización) -->
              <template v-for="asignacion in getAsignacionesDelMarco(marco.id)" :key="asignacion.id">
                <v-rect
                  :config="{
                    x: asignacion.x,
                    y: asignacion.y,
                    width: asignacion.ancho,
                    height: asignacion.alto,
                    fill: 'rgba(33, 150, 243, 0.15)',
                    stroke: elementoSeleccionado?.id === asignacion.id ? '#FF5722' : '#2196F3',
                    strokeWidth: elementoSeleccionado?.id === asignacion.id ? 3 : 2,
                    dash: [5, 5],
                    listening: true,
                  }"
                  @click="(e) => { e.cancelBubble = true; seleccionarElemento(asignacion); }"
                />
                
                <!-- Etiqueta -->
                <v-text
                  :config="{
                    x: asignacion.x + 5,
                    y: asignacion.y + 5,
                    text: getTipoVentanaNombre(asignacion.tipoVentanaId),
                    fontSize: 11,
                    fontStyle: 'bold',
                    fill: '#1976D2',
                    listening: false,
                  }"
                />
              </template>
            </v-group>

            <!-- Línea de dibujo temporal -->
            <v-line
              v-if="lineaTemporal"
              :config="{
                points: lineaTemporal.points,
                stroke: '#2196F3',
                strokeWidth: 2,
                dash: [10, 5],
                listening: false,
              }"
            />
          </v-layer>
        </v-stage>
      </div>

      <!-- Panel Derecho (Propiedades y Vista Previa) -->
      <div class="panel-right">
        <v-tabs v-model="tabActivo" density="compact" bg-color="primary">
          <v-tab value="propiedades">
            <v-icon left>mdi-cog</v-icon>
            Propiedades
          </v-tab>
          <v-tab value="vista">
            <v-icon left>mdi-eye</v-icon>
            Vista Previa
          </v-tab>
        </v-tabs>

        <v-window v-model="tabActivo" class="window-content">
          <!-- Tab de Propiedades -->
          <v-window-item value="propiedades">
            <div v-if="elementoSeleccionado" class="pa-3">
              <!-- Propiedades de Marco -->
            <template v-if="elementoSeleccionado.tipo === 'marco'">
              <v-text-field
                v-model.number="elementoSeleccionado.ancho"
                label="Ancho (mm)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model.number="elementoSeleccionado.alto"
                label="Alto (mm)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model.number="elementoSeleccionado.anchoMarco"
                label="Ancho Marco (mm)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
              <v-autocomplete
                v-model="elementoSeleccionado.perfilMarcoId"
                :items="perfilesMarco"
                item-title="nombre"
                item-value="id"
                label="Perfil del Marco"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
            </template>

            <!-- Propiedades de Vidrio -->
            <template v-if="elementoSeleccionado.tipo === 'vidrio'">
              <v-autocomplete
                v-model="elementoSeleccionado.tipoVidrioId"
                :items="tiposVidrio"
                item-title="nombre"
                item-value="id"
                label="Tipo de Vidrio"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
                @update:model-value="actualizarVidrioMarco"
              />
              
              <v-autocomplete
                v-model="elementoSeleccionado.productoVidrioId"
                :items="productosVidrioFiltrados"
                item-title="nombre"
                item-value="id"
                label="Producto de Vidrio"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
                @update:model-value="actualizarVidrioMarco"
              />
              
              <v-radio-group v-model="elementoSeleccionado.escarcha" hide-details class="mb-3" @update:model-value="actualizarVidrioMarco">
                <template #label>
                  <span class="text-caption">Escarcha</span>
                </template>
                <v-radio label="Con escarcha" value="on" density="compact" />
                <v-radio label="Sin escarcha" value="off" density="compact" />
              </v-radio-group>

              <v-radio-group v-model="elementoSeleccionado.conPersiana" hide-details class="mb-3" @update:model-value="actualizarVidrioMarco">
                <template #label>
                  <span class="text-caption">Persiana</span>
                </template>
                <v-radio label="Con persiana" value="on" density="compact" />
                <v-radio label="Sin persiana" value="off" density="compact" />
              </v-radio-group>

              <v-text-field
                v-model.number="elementoSeleccionado.squareMeterHeight"
                label="Altura m²"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
                @update:model-value="actualizarVidrioMarco"
              />
            </template>

            <!-- Propiedades de Divisor -->
            <template v-if="elementoSeleccionado.tipo === 'divisor'">
              <v-text-field
                v-model.number="elementoSeleccionado.posicion"
                label="Posición (px)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model.number="elementoSeleccionado.ancho"
                label="Ancho (mm)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
              <v-autocomplete
                v-model="elementoSeleccionado.perfilId"
                :items="perfilesDivisores"
                item-title="nombre"
                item-value="id"
                :label="`Perfil Divisor ${elementoSeleccionado.orientacion === 'horizontal' ? 'Horizontal' : 'Vertical'}`"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
              />
            </template>

            <!-- Propiedades de Asignación de Tipo -->
            <template v-if="elementoSeleccionado.tipoVentanaId">
              <v-alert type="info" variant="tonal" class="mb-3">
                <div class="text-subtitle-2">Tipo de Ventana Asignado</div>
                <div class="text-body-2 mt-1">
                  {{ getTipoVentanaNombre(elementoSeleccionado.tipoVentanaId) }}
                </div>
                <div class="text-caption text-grey mt-1">
                  ID: {{ elementoSeleccionado.tipoVentanaId }}
                </div>
              </v-alert>
              
              <v-select
                v-model="elementoSeleccionado.tipoVentanaId"
                :items="tiposVentana"
                item-title="nombre"
                item-value="id"
                label="Cambiar Tipo"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-3"
                @update:model-value="actualizarTipoAsignacion"
              />
            </template>

            <v-divider class="my-3" />

            <v-btn
              block
              color="error"
              variant="outlined"
              @click="eliminarSeleccion"
            >
              <v-icon left>mdi-delete</v-icon>
              Eliminar
            </v-btn>
          </div>
          <div v-else class="pa-4 text-center text-grey">
            Selecciona un elemento para ver sus propiedades
          </div>
        </v-window-item>

        <!-- Tab de Vista Previa -->
        <v-window-item value="vista">
          <div class="vista-previa-container">
            <div class="vista-previa-content">
              <!-- Renderizar estructura con componentes reales -->
              <div
                v-for="asignacion in asignacionesTipo"
                :key="`vista-${asignacion.id}`"
                class="ventana-componente"
                :style="{
                  width: `${calcularAnchoMm(asignacion.ancho)}px`,
                  height: `${calcularAltoMm(asignacion.alto)}px`,
                }"
              >
                <component
                  v-if="getComponenteVista(asignacion.tipoVentanaId)"
                  :is="getComponenteVista(asignacion.tipoVentanaId)"
                  :ancho="calcularAnchoMm(asignacion.ancho)"
                  :alto="calcularAltoMm(asignacion.alto)"
                  :color-marco="colorMarco"
                />
                <div class="ventana-label">
                  {{ getTipoVentanaNombre(asignacion.tipoVentanaId) }}
                </div>
              </div>
              <div v-if="asignacionesTipo.length === 0" class="pa-4 text-center text-grey">
                Asigna tipos de ventana para ver la vista previa
              </div>
            </div>
          </div>
        </v-window-item>
      </v-window>
    </div>
  </div>

    <!-- Botones de Acción -->
    <div class="actions-bar mt-4">
      <v-btn @click="$emit('cancelar')" variant="text">
        Cancelar
      </v-btn>
      <v-btn @click="aplicarConfiguracion" color="primary" variant="flat">
        <v-icon left>mdi-check</v-icon>
        Aplicar Configuración
      </v-btn>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import api from '@/axiosInstance'

// Importar componentes de vista para renderizar como imágenes
import VistaVentanaFijaS60 from './VistaVentanaFijaS60.vue'
import VistaVentanaProyectanteS60 from './VistaVentanaProyectanteS60.vue'
import VistaVentanaCorredera from './VistaVentanaCorredera.vue'
import VistaVentanaAbatirS60 from './VistaVentanaAbatirS60.vue'
import VistaPuertaS60 from './VistaPuertaS60.vue'

// Mapa de componentes por tipo ID
const componentesVista = {
  2: VistaVentanaFijaS60,
  45: VistaVentanaProyectanteS60,
  3: VistaVentanaCorredera,
  49: VistaVentanaAbatirS60,
  50: VistaPuertaS60,
}

const props = defineProps({
  ventana: { type: Object, default: null },
  tiposVentana: { type: Array, default: () => [] },
  tiposVidrio: { type: Array, default: () => [] },
  productosVidrio: { type: Array, default: () => [] },
  colorMarco: { type: String, default: 'blanco' },
  ancho: { type: Number, default: 2000 },
  alto: { type: Number, default: 1600 },
})

const emit = defineEmits(['actualizar', 'cancelar'])

// Estado
const tabActivo = ref('propiedades')
const herramientaActiva = ref('seleccionar')
const marcos = ref([])
const divisores = ref([])
const asignacionesTipo = ref([])
const elementoSeleccionado = ref(null)
const historial = ref([])
const historialIndex = ref(-1)

// Canvas
const canvasContainer = ref(null)
const stage = ref(null)
const mainLayer = ref(null)
const lineaTemporal = ref(null)
const dibujando = ref(false)
const puntoInicio = ref(null)

// Dimensiones
const anchoTotal = computed(() => props.ancho || 2400)
const altoTotal = computed(() => props.alto || 1600)
const stageConfig = ref({
  width: 900,
  height: 600,
})

const fondoConfig = computed(() => ({
  x: 0,
  y: 0,
  width: stageConfig.value.width,
  height: stageConfig.value.height,
  fill: '#F5F5F5',
}))

// Datos
const perfilesMarco = ref([])
const perfilesDivisores = ref([])

// Computed
const puedeDeshacer = computed(() => historialIndex.value > 0)
const puedeRehacer = computed(() => historialIndex.value < historial.value.length - 1)

const productosVidrioFiltrados = computed(() => {
  if (!elementoSeleccionado.value?.tipoVidrioId) return []
  
  return props.productosVidrio
    .filter(p => p.tipo_producto_id === elementoSeleccionado.value.tipoVidrioId)
    .flatMap(p =>
      p.colores_por_proveedor?.map(cpp => ({
        id: cpp.id,
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor'})`
      })) || []
    )
})

// Métodos
function generarId() {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
}

function guardarEnHistorial() {
  const estado = {
    marcos: JSON.parse(JSON.stringify(marcos.value)),
    divisores: JSON.parse(JSON.stringify(divisores.value)),
    asignacionesTipo: JSON.parse(JSON.stringify(asignacionesTipo.value)),
  }
  
  // Eliminar estados futuros si estamos en medio del historial
  historial.value = historial.value.slice(0, historialIndex.value + 1)
  historial.value.push(estado)
  historialIndex.value++
  
  // Limitar historial a 50 estados
  if (historial.value.length > 50) {
    historial.value.shift()
    historialIndex.value--
  }
}

function deshacer() {
  if (!puedeDeshacer.value) return
  historialIndex.value--
  restaurarEstado(historial.value[historialIndex.value])
}

function rehacer() {
  if (!puedeRehacer.value) return
  historialIndex.value++
  restaurarEstado(historial.value[historialIndex.value])
}

function restaurarEstado(estado) {
  marcos.value = JSON.parse(JSON.stringify(estado.marcos))
  divisores.value = JSON.parse(JSON.stringify(estado.divisores))
  asignacionesTipo.value = JSON.parse(JSON.stringify(estado.asignacionesTipo))
  elementoSeleccionado.value = null
}

function handleMouseDown(e) {
  if (herramientaActiva.value === 'seleccionar') return

  const pos = e.target.getStage().getPointerPosition()
  puntoInicio.value = pos
  dibujando.value = true

  if (herramientaActiva.value === 'rectangulo') {
    // Comenzar a dibujar rectángulo
  } else if (herramientaActiva.value === 'divisor-h' || herramientaActiva.value === 'divisor-v') {
    // Comenzar línea de divisor
    lineaTemporal.value = {
      points: [pos.x, pos.y, pos.x, pos.y],
    }
  }
}

function handleMouseMove(e) {
  if (!dibujando.value) return

  const pos = e.target.getStage().getPointerPosition()

  if (herramientaActiva.value === 'divisor-h') {
    // Línea horizontal
    lineaTemporal.value = {
      points: [0, pos.y, stageConfig.value.width, pos.y],
    }
  } else if (herramientaActiva.value === 'divisor-v') {
    // Línea vertical
    lineaTemporal.value = {
      points: [pos.x, 0, pos.x, stageConfig.value.height],
    }
  }
}

function handleMouseUp(e) {
  if (!dibujando.value) return

  const pos = e.target.getStage().getPointerPosition()

  if (herramientaActiva.value === 'rectangulo') {
    // Crear marco
    crearMarco(puntoInicio.value, pos)
  } else if (herramientaActiva.value === 'divisor-h' || herramientaActiva.value === 'divisor-v') {
    // Crear divisor
    crearDivisor(pos)
  }

  dibujando.value = false
  lineaTemporal.value = null
  puntoInicio.value = null
}

function handleCanvasClick(e) {
  const target = e.target
  
  // Click para asignar tipo de ventana (solo si no se hizo click en otro elemento)
  if (herramientaActiva.value.startsWith('tipo-')) {
    const tipoId = parseInt(herramientaActiva.value.replace('tipo-', ''))
    const pos = e.target.getStage().getPointerPosition()
    
    console.log('Intentando asignar tipo de ventana:', tipoId, 'en posición:', pos)
    asignarTipoVentana(pos, tipoId)
    return
  }
  
  // Click simple para divisor (solo en modo divisor)
  if ((herramientaActiva.value === 'divisor-h' || herramientaActiva.value === 'divisor-v') && !dibujando.value) {
    const pos = e.target.getStage().getPointerPosition()
    crearDivisor(pos)
  }
}

function crearMarco(inicio, fin) {
  const x = Math.min(inicio.x, fin.x)
  const y = Math.min(inicio.y, fin.y)
  const ancho = Math.abs(fin.x - inicio.x)
  const alto = Math.abs(fin.y - inicio.y)

  if (ancho < 50 || alto < 50) return // Muy pequeño

  const marco = {
    id: generarId(),
    tipo: 'marco',
    x,
    y,
    ancho,
    alto,
    anchoMarco: 50,
    perfilMarcoId: null,
    colorVidrio: '#00FF0080',
  }

  marcos.value.push(marco)
  guardarEnHistorial()
  herramientaActiva.value = 'seleccionar'
}

function crearDivisor(pos) {
  // Encontrar el marco que contiene este punto
  const marco = marcos.value.find(m =>
    pos.x >= m.x && pos.x <= m.x + m.ancho &&
    pos.y >= m.y && pos.y <= m.y + m.alto
  )

  if (!marco) return

  const orientacion = herramientaActiva.value === 'divisor-h' ? 'horizontal' : 'vertical'
  const posicion = orientacion === 'horizontal' ? pos.y : pos.x

  const divisor = {
    id: generarId(),
    tipo: 'divisor',
    marcoId: marco.id,
    orientacion,
    posicion,
    ancho: 40,
    perfilId: null,
  }

  divisores.value.push(divisor)
  guardarEnHistorial()
}

function asignarTipoVentana(pos, tipoId) {
  console.log('asignarTipoVentana llamado:', { pos, tipoId, marcos: marcos.value })
  
  // Encontrar marco que contiene este punto (en el área del vidrio)
  const marco = marcos.value.find(m => {
    const dentroX = pos.x >= m.x + m.anchoMarco && pos.x <= m.x + m.ancho - m.anchoMarco
    const dentroY = pos.y >= m.y + m.anchoMarco && pos.y <= m.y + m.alto - m.anchoMarco
    return dentroX && dentroY
  })

  console.log('Marco encontrado:', marco)
  
  if (!marco) {
    console.warn('No se encontró marco en la posición del click')
    return
  }

  // Calcular área según divisores (por ahora sin subdivisiones)
  const asignacion = {
    id: generarId(),
    marcoId: marco.id,
    tipoVentanaId: tipoId,
    x: marco.x + marco.anchoMarco,
    y: marco.y + marco.anchoMarco,
    ancho: marco.ancho - marco.anchoMarco * 2,
    alto: marco.alto - marco.anchoMarco * 2,
  }

  console.log('Asignación creada:', asignacion)
  asignacionesTipo.value.push(asignacion)
  guardarEnHistorial()
  
  // Volver a herramienta seleccionar después de asignar
  herramientaActiva.value = 'seleccionar'
}

function seleccionarElemento(elemento) {
  elementoSeleccionado.value = elemento
}

function seleccionarVidrio(marco, e) {
  // Detener propagación en Konva
  if (e && e.cancelBubble !== undefined) {
    e.cancelBubble = true
  }
  
  // Si estamos en modo tipo de ventana, asignar en lugar de seleccionar
  if (herramientaActiva.value.startsWith('tipo-')) {
    const tipoId = parseInt(herramientaActiva.value.replace('tipo-', ''))
    const pos = e.target.getStage().getPointerPosition()
    asignarTipoVentana(pos, tipoId)
    return
  }
  
  const marcoOriginal = marcos.value.find(m => m.id === marco.id)
  if (!marcoOriginal) return
  
  elementoSeleccionado.value = {
    ...marcoOriginal,
    tipo: 'vidrio',
    tipoVidrioId: marcoOriginal.tipoVidrioId || null,
    productoVidrioId: marcoOriginal.productoVidrioId || null,
    escarcha: marcoOriginal.escarcha || 'off',
    conPersiana: marcoOriginal.conPersiana || 'off',
    squareMeterHeight: marcoOriginal.squareMeterHeight || 0,
  }
  
  // Cambiar a herramienta vidrio si estamos en modo seleccionar
  if (herramientaActiva.value === 'seleccionar') {
    herramientaActiva.value = 'vidrio'
  }
}

function handleMouseEnterVidrio(e) {
  const stage = e.target.getStage()
  if (stage) {
    stage.container().style.cursor = 'pointer'
  }
}

function handleMouseLeaveVidrio(e) {
  const stage = e.target.getStage()
  if (stage) {
    stage.container().style.cursor = 'default'
  }
}

function actualizarVidrioMarco() {
  if (!elementoSeleccionado.value || elementoSeleccionado.value.tipo !== 'vidrio') return
  
  const marco = marcos.value.find(m => m.id === elementoSeleccionado.value.id)
  if (marco) {
    marco.tipoVidrioId = elementoSeleccionado.value.tipoVidrioId
    marco.productoVidrioId = elementoSeleccionado.value.productoVidrioId
    marco.escarcha = elementoSeleccionado.value.escarcha
    marco.conPersiana = elementoSeleccionado.value.conPersiana
    marco.squareMeterHeight = elementoSeleccionado.value.squareMeterHeight
    guardarEnHistorial()
  }
}

function eliminarSeleccion() {
  if (!elementoSeleccionado.value) return

  if (elementoSeleccionado.value.tipo === 'marco') {
    marcos.value = marcos.value.filter(m => m.id !== elementoSeleccionado.value.id)
    divisores.value = divisores.value.filter(d => d.marcoId !== elementoSeleccionado.value.id)
    asignacionesTipo.value = asignacionesTipo.value.filter(a => a.marcoId !== elementoSeleccionado.value.id)
  } else if (elementoSeleccionado.value.tipo === 'divisor') {
    divisores.value = divisores.value.filter(d => d.id !== elementoSeleccionado.value.id)
  } else if (elementoSeleccionado.value.tipoVentanaId) {
    // Es una asignación de tipo
    asignacionesTipo.value = asignacionesTipo.value.filter(a => a.id !== elementoSeleccionado.value.id)
  }

  guardarEnHistorial()
  elementoSeleccionado.value = null
}

function actualizarTipoAsignacion() {
  if (!elementoSeleccionado.value || !elementoSeleccionado.value.tipoVentanaId) return
  
  const asignacion = asignacionesTipo.value.find(a => a.id === elementoSeleccionado.value.id)
  if (asignacion) {
    asignacion.tipoVentanaId = elementoSeleccionado.value.tipoVentanaId
    guardarEnHistorial()
  }
}

function limpiarTodo() {
  if (confirm('¿Estás seguro de que quieres limpiar todo?')) {
    marcos.value = []
    divisores.value = []
    asignacionesTipo.value = []
    elementoSeleccionado.value = null
    guardarEnHistorial()
  }
}

function getDivisoresDelMarco(marcoId) {
  return divisores.value.filter(d => d.marcoId === marcoId)
}

function getAsignacionesDelMarco(marcoId) {
  return asignacionesTipo.value.filter(a => a.marcoId === marcoId)
}

function getDivisorPoints(divisor, marco) {
  if (divisor.orientacion === 'horizontal') {
    return [
      marco.x,
      divisor.posicion,
      marco.x + marco.ancho,
      divisor.posicion,
    ]
  } else {
    return [
      divisor.posicion,
      marco.y,
      divisor.posicion,
      marco.y + marco.alto,
    ]
  }
}

function getTipoVentanaNombre(tipoId) {
  const tipo = props.tiposVentana.find(t => t.id === tipoId)
  return tipo ? tipo.nombre : `Tipo ${tipoId}`
}

function getComponenteVista(tipoId) {
  return componentesVista[tipoId] || null
}

function calcularAnchoMm(anchoPx) {
  const escalaMmPorPx = props.ancho / stageConfig.value.width
  return anchoPx * escalaMmPorPx
}

function calcularAltoMm(altoPx) {
  const escalaMmPorPx = props.alto / stageConfig.value.height
  return altoPx * escalaMmPorPx
}

function guardar() {
  guardarEnHistorial()
  // Lógica adicional de guardado
}

function aplicarConfiguracion() {
  const configuracion = {
    marcos: marcos.value,
    divisores: divisores.value,
    asignacionesTipo: asignacionesTipo.value,
    anchoTotal: anchoTotal.value,
    altoTotal: altoTotal.value,
  }
  emit('actualizar', configuracion)
}

// Cargar datos
onMounted(async () => {
  try {
    const [marcosRes, divisoresRes] = await Promise.all([
      api.get('/api/productos', { params: { tipo_producto_id: '3,4,5' } }),
      api.get('/api/productos', { params: { tipo_producto_id: '6,7' } }),
    ])
    perfilesMarco.value = marcosRes.data
    perfilesDivisores.value = divisoresRes.data

    // Estado inicial
    guardarEnHistorial()
  } catch (error) {
    console.error('Error cargando perfiles:', error)
  }
})

// Watch para actualizar canvas
watch([marcos, divisores, asignacionesTipo], () => {
  if (mainLayer.value) {
    mainLayer.value.getNode().batchDraw()
  }
}, { deep: true })
</script>

<style scoped>
.armador-container {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.toolbar-top {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  border-bottom: 1px solid #e0e0e0;
  background: white;
}

.armador-layout {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.toolbar-left {
  width: 80px;
  min-width: 80px;
  border-right: 1px solid #e0e0e0;
  background: white;
  padding: 12px 8px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.toolbar-buttons {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.tool-btn {
  width: 64px !important;
  height: 64px !important;
  min-width: 64px !important;
  flex-shrink: 0;
}

.tool-btn .v-icon {
  font-size: 32px !important;
}

.toolbar-left .v-divider {
  width: 100%;
  margin: 8px 0;
}

.canvas-area {
  flex: 1;
  overflow: auto;
  background: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.panel-right {
  width: 300px;
  border-left: 1px solid #e0e0e0;
  background: white;
  display: flex;
  flex-direction: column;
}

.window-content {
  flex: 1;
  overflow-y: auto;
}

.vista-previa-container {
  padding: 16px;
  overflow-y: auto;
  max-height: calc(100vh - 200px);
}

.vista-previa-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.ventana-componente {
  border: 2px solid #2196F3;
  border-radius: 8px;
  padding: 8px;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  position: relative;
}

.ventana-label {
  position: absolute;
  top: -10px;
  left: 10px;
  background: #2196F3;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: bold;
}

.actions-bar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 16px;
  border-top: 1px solid #e0e0e0;
  background: white;
}
</style>
