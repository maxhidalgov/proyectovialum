<template>
  <v-card class="pa-4">
    <v-card-title class="d-flex align-center">
      <v-icon left>mdi-window-maximize</v-icon>
      Armador Universal de Ventanas
    </v-card-title>

    <v-divider class="my-3" />

    <!-- Panel de configuración superior -->
    <v-row dense class="mb-4">
      <!-- Dimensiones -->
      <v-col cols="12" md="3">
        <v-text-field
          v-model.number="configuracion.ancho"
          label="Ancho Total (mm)"
          type="number"
          density="compact"
          variant="outlined"
          min="100"
        />
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          v-model.number="configuracion.alto"
          label="Alto Total (mm)"
          type="number"
          density="compact"
          variant="outlined"
          min="100"
        />
      </v-col>

      <!-- Selector de Perfil Marco -->
      <v-col cols="12" md="6">
        <v-autocomplete
          v-model="configuracion.perfilMarcoId"
          :items="perfilesMarco"
          item-title="nombre"
          item-value="id"
          label="Perfil del Marco"
          density="compact"
          variant="outlined"
          clearable
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #prepend>
                <v-icon>mdi-border-all</v-icon>
              </template>
              <v-list-item-title>{{ item.raw.nombre }}</v-list-item-title>
              <v-list-item-subtitle>Código: {{ item.raw.codigo }}</v-list-item-subtitle>
            </v-list-item>
          </template>
        </v-autocomplete>
      </v-col>
    </v-row>

    <!-- Selectores de Perfiles Divisores -->
    <v-row dense class="mb-4">
      <v-col cols="12" md="6">
        <v-autocomplete
          v-model="configuracion.perfilDivisorHorizontalId"
          :items="perfilesDivisores"
          item-title="nombre"
          item-value="id"
          label="Perfil Divisor Horizontal"
          density="compact"
          variant="outlined"
          clearable
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #prepend>
                <v-icon>mdi-minus</v-icon>
              </template>
              <v-list-item-title>{{ item.raw.nombre }}</v-list-item-title>
              <v-list-item-subtitle>Código: {{ item.raw.codigo }}</v-list-item-subtitle>
            </v-list-item>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col cols="12" md="6">
        <v-autocomplete
          v-model="configuracion.perfilDivisorVerticalId"
          :items="perfilesDivisores"
          item-title="nombre"
          item-value="id"
          label="Perfil Divisor Vertical"
          density="compact"
          variant="outlined"
          clearable
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #prepend>
                <v-icon>mdi-slash-forward</v-icon>
              </template>
              <v-list-item-title>{{ item.raw.nombre }}</v-list-item-title>
              <v-list-item-subtitle>Código: {{ item.raw.codigo }}</v-list-item-subtitle>
            </v-list-item>
          </template>
        </v-autocomplete>
      </v-col>
    </v-row>

    <v-divider class="my-3" />

    <!-- Canvas Konva -->
    <div class="canvas-container">
      <v-stage
        :config="stageConfig"
        @mousedown="handleStageClick"
      >
        <v-layer>
          <!-- Fondo del área de trabajo -->
          <v-rect
            :config="{
              x: 0,
              y: 0,
              width: stageConfig.width,
              height: stageConfig.height,
              fill: '#f5f5f5',
              stroke: '#ddd',
              strokeWidth: 1,
            }"
          />

          <!-- Marco exterior -->
          <v-group :config="grupoMarcoConfig">
            <v-rect v-bind="marcoExterior" />
            
            <!-- Renderizar estructura recursiva -->
            <SeccionArmador
              v-for="(seccion, idx) in configuracion.secciones"
              :key="`seccion-${idx}-${forceRenderKey}`"
              :seccion="seccion"
              :x="marcoAncho"
              :y="marcoAncho"
              :ancho-disponible="anchoInterior"
              :alto-disponible="altoInterior"
              :escala="escala"
              :nivel="0"
              :path="`${idx}`"
              :path-seleccionado="seccionSeleccionada"
              :tipos-ventana="tiposVentana"
              :perfil-divisor-h="configuracion.perfilDivisorHorizontalId"
              :perfil-divisor-v="configuracion.perfilDivisorVerticalId"
              @seleccionar="handleSeleccionarSeccion"
              @dividir="handleDividirSeccion"
              @asignar-tipo="handleAsignarTipo"
              @eliminar="handleEliminarSeccion"
            />
          </v-group>
        </v-layer>
      </v-stage>
    </div>

    <!-- Panel de control de sección seleccionada -->
    <v-card v-if="seccionSeleccionada" variant="tonal" class="mt-4 pa-3">
      <v-card-subtitle class="d-flex align-center justify-space-between">
        <span>
          <v-icon left>mdi-cursor-default-click</v-icon>
          Sección seleccionada: {{ seccionSeleccionada }}
        </span>
        <v-btn
          size="small"
          icon
          @click="seccionSeleccionada = null"
        >
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-subtitle>

      <v-row dense class="mt-2">
        <!-- Botones de división -->
        <v-col cols="6" md="3">
          <v-btn
            block
            color="primary"
            variant="tonal"
            @click="dividirSeccionSeleccionada('horizontal')"
          >
            <v-icon left>mdi-arrow-split-horizontal</v-icon>
            Dividir Horizontal
          </v-btn>
        </v-col>
        <v-col cols="6" md="3">
          <v-btn
            block
            color="primary"
            variant="tonal"
            @click="dividirSeccionSeleccionada('vertical')"
          >
            <v-icon left>mdi-arrow-split-vertical</v-icon>
            Dividir Vertical
          </v-btn>
        </v-col>

        <!-- Asignar tipo de ventana -->
        <v-col cols="12" md="4">
          <v-select
            :model-value="getSeccionPorPath(seccionSeleccionada)?.tipoVentanaId"
            :items="tiposVentana"
            item-title="nombre"
            item-value="id"
            label="Asignar Tipo de Ventana"
            density="compact"
            variant="outlined"
            @update:model-value="asignarTipoASeccion"
          />
        </v-col>

        <!-- Eliminar sección -->
        <v-col cols="12" md="2">
          <v-btn
            block
            color="error"
            variant="tonal"
            @click="eliminarSeccionSeleccionada"
          >
            <v-icon left>mdi-delete</v-icon>
            Eliminar
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- Botones de acción -->
    <v-card-actions class="mt-4">
      <v-btn
        color="secondary"
        variant="outlined"
        @click="reiniciarArmador"
      >
        <v-icon left>mdi-refresh</v-icon>
        Reiniciar
      </v-btn>
      <v-spacer />
      <v-btn
        color="primary"
        @click="exportarConfiguracion"
      >
        <v-icon left>mdi-check</v-icon>
        Aplicar Configuración
      </v-btn>
    </v-card-actions>

    <!-- Debug panel (opcional) -->
    <v-expansion-panels v-if="false" class="mt-4">
      <v-expansion-panel>
        <v-expansion-panel-title>
          <v-icon left>mdi-bug</v-icon>
          Debug: Estructura JSON
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <pre class="text-caption">{{ JSON.stringify(configuracion, null, 2) }}</pre>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </v-card>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import SeccionArmador from './SeccionArmador.vue'
import api from '@/axiosInstance'

const props = defineProps({
  ancho: { type: Number, default: 2000 },
  alto: { type: Number, default: 2000 },
  colorMarco: { type: String, default: 'blanco' },
  configuracionInicial: { type: Object, default: null },
  tiposVentana: { type: Array, default: () => [] },
})

const emit = defineEmits(['guardar', 'cancelar'])

// Estado principal
const configuracion = ref({
  ancho: props.ancho,
  alto: props.alto,
  perfilMarcoId: null,
  perfilDivisorHorizontalId: null,
  perfilDivisorVerticalId: null,
  secciones: [
    {
      tipo: 'vacio', // 'vacio', 'compuesta', 'ventana'
      tipoVentanaId: null,
      orientacion: null, // 'horizontal', 'vertical'
      subsecciones: [],
      porcentaje: 100,
    }
  ],
})

// Listas de perfiles disponibles
const perfilesMarco = ref([])
const perfilesDivisores = ref([])

// UI State
const seccionSeleccionada = ref(null)
const forceRenderKey = ref(0)

// Canvas config
const CANVAS_WIDTH = 800
const CANVAS_HEIGHT = 500
const PADDING = 50

const stageConfig = {
  width: CANVAS_WIDTH,
  height: CANVAS_HEIGHT,
}

// Escala para ajustar la ventana al canvas
const escala = computed(() => {
  const anchoDisponible = CANVAS_WIDTH - PADDING * 2
  const altoDisponible = CANVAS_HEIGHT - PADDING * 2
  
  const escalaAncho = anchoDisponible / configuracion.value.ancho
  const escalaAlto = altoDisponible / configuracion.value.alto
  
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9
})

// Dimensiones del marco en pantalla
const marcoAncho = computed(() => 30) // Ancho fijo en píxeles para el marco

const anchoTotal = computed(() => configuracion.value.ancho * escala.value)
const altoTotal = computed(() => configuracion.value.alto * escala.value)
const anchoInterior = computed(() => anchoTotal.value - marcoAncho.value * 2)
const altoInterior = computed(() => altoTotal.value - marcoAncho.value * 2)

const grupoMarcoConfig = computed(() => ({
  x: (CANVAS_WIDTH - anchoTotal.value) / 2,
  y: (CANVAS_HEIGHT - altoTotal.value) / 2,
}))

const marcoExterior = computed(() => ({
  x: 0,
  y: 0,
  width: anchoTotal.value,
  height: altoTotal.value,
  fill: '#D0D0D0',
  stroke: '#333',
  strokeWidth: 2,
}))

// Cargar perfiles desde el backend
onMounted(async () => {
  try {
    const response = await api.get('/api/productos')
    const productos = response.data
    
    // Filtrar perfiles de marco (ajusta tipo_producto_id según tu BD)
    perfilesMarco.value = productos.filter(p => 
      [3, 4, 5].includes(p.tipo_producto_id) // IDs de tipos de perfil marco
    )
    
    // Filtrar perfiles divisores
    perfilesDivisores.value = productos.filter(p => 
      [3, 4, 5].includes(p.tipo_producto_id) // Mismo tipo o diferente según tu caso
    )

    console.log('✅ Perfiles cargados:', {
      marcos: perfilesMarco.value.length,
      divisores: perfilesDivisores.value.length
    })
    
  } catch (error) {
    console.error('❌ Error cargando perfiles:', error)
  }

  // Cargar configuración inicial si existe
  if (props.configuracionInicial) {
    configuracion.value = { ...configuracion.value, ...props.configuracionInicial }
  }
})

// Handlers
function handleStageClick(e) {
  // Deseleccionar si se hace click en el fondo
  if (e.target === e.target.getStage()) {
    seccionSeleccionada.value = null
  }
}

function handleSeleccionarSeccion(path) {
  console.log('🎯 Sección seleccionada:', path)
  seccionSeleccionada.value = path
}

function handleDividirSeccion({ path, orientacion }) {
  console.log('✂️ Dividir sección:', path, 'orientación:', orientacion)
  dividirSeccionPorPath(path, orientacion)
}

function handleAsignarTipo({ path, tipoVentanaId }) {
  console.log('🪟 Asignar tipo ventana:', tipoVentanaId, 'a sección:', path)
  asignarTipoPorPath(path, tipoVentanaId)
}

function handleEliminarSeccion(path) {
  console.log('🗑️ Eliminar sección:', path)
  eliminarSeccionPorPath(path)
}

// Utilidades para navegar el árbol de secciones
function getSeccionPorPath(path) {
  const indices = path.split('.').map(Number)
  let current = configuracion.value.secciones
  
  for (let i = 0; i < indices.length; i++) {
    const idx = indices[i]
    if (i === indices.length - 1) {
      return current[idx]
    }
    current = current[idx]?.subsecciones || []
  }
  return null
}

function dividirSeccionPorPath(path, orientacion) {
  const seccion = getSeccionPorPath(path)
  if (!seccion) return

  // Validar que la sección es lo suficientemente grande para dividir
  const dimensionMinima = 100 // mm mínimo por subsección
  const dimensionRelevante = orientacion === 'horizontal' 
    ? configuracion.value.alto 
    : configuracion.value.ancho
    
  if (dimensionRelevante < dimensionMinima * 2) {
    alert(`La sección es muy pequeña para dividir. Mínimo ${dimensionMinima * 2}mm en dirección ${orientacion}.`)
    return
  }

  // Convertir a compuesta con 2 subsecciones
  seccion.tipo = 'compuesta'
  seccion.orientacion = orientacion
  seccion.subsecciones = [
    {
      tipo: 'vacio',
      tipoVentanaId: null,
      orientacion: null,
      subsecciones: [],
      porcentaje: 50,
    },
    {
      tipo: 'vacio',
      tipoVentanaId: null,
      orientacion: null,
      subsecciones: [],
      porcentaje: 50,
    }
  ]

  forceRenderKey.value++
}

function asignarTipoPorPath(path, tipoVentanaId) {
  const seccion = getSeccionPorPath(path)
  if (!seccion) return

  seccion.tipo = 'ventana'
  seccion.tipoVentanaId = tipoVentanaId
  seccion.subsecciones = [] // Limpiar subsecciones si las tenía

  forceRenderKey.value++
}

function eliminarSeccionPorPath(path) {
  const indices = path.split('.').map(Number)
  
  // No se puede eliminar la sección raíz
  if (indices.length === 1) {
    alert('No se puede eliminar la sección raíz. Usa "Reiniciar" para comenzar de nuevo.')
    return
  }

  // Navegar hasta el padre
  let current = configuracion.value.secciones
  for (let i = 0; i < indices.length - 1; i++) {
    current = current[indices[i]].subsecciones
  }

  // Eliminar la sección
  current.splice(indices[indices.length - 1], 1)

  // Si queda solo una subsección, colapsar
  if (current.length === 1) {
    const padre = getSeccionPorPath(indices.slice(0, -1).join('.'))
    if (padre) {
      Object.assign(padre, current[0])
    }
  }

  seccionSeleccionada.value = null
  forceRenderKey.value++
}

// Acciones del panel de control
function dividirSeccionSeleccionada(orientacion) {
  if (!seccionSeleccionada.value) return
  dividirSeccionPorPath(seccionSeleccionada.value, orientacion)
}

function asignarTipoASeccion(tipoVentanaId) {
  if (!seccionSeleccionada.value) return
  asignarTipoPorPath(seccionSeleccionada.value, tipoVentanaId)
}

function eliminarSeccionSeleccionada() {
  if (!seccionSeleccionada.value) return
  eliminarSeccionPorPath(seccionSeleccionada.value)
}

function reiniciarArmador() {
  if (confirm('¿Estás seguro de reiniciar el armador? Se perderán todos los cambios.')) {
    configuracion.value.secciones = [
      {
        tipo: 'vacio',
        tipoVentanaId: null,
        orientacion: null,
        subsecciones: [],
        porcentaje: 100,
      }
    ]
    seccionSeleccionada.value = null
    forceRenderKey.value++
  }
}

function exportarConfiguracion() {
  console.log('📤 Exportando configuración:', configuracion.value)
  emit('guardar', configuracion.value)
}

// Exponer método para obtener configuración
defineExpose({
  getConfiguracion: () => configuracion.value,
  setConfiguracion: (config) => {
    configuracion.value = { ...configuracion.value, ...config }
    forceRenderKey.value++
  }
})
</script>

<style scoped>
.canvas-container {
  border: 2px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background: #fafafa;
}

pre {
  max-height: 300px;
  overflow: auto;
  background: #f5f5f5;
  padding: 12px;
  border-radius: 4px;
}
</style>
