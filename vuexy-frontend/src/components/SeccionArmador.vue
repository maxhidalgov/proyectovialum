<template>
  <v-group>
    <!-- Sección (área clickeable) - Solo para tipos 'vacio' y 'ventana' -->
    <v-rect
      v-if="seccion.tipo !== 'compuesta'"
      :config="areaConfig"
      @click="handleClick"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
    />

    <!-- Borde de selección -->
    <v-rect
      v-if="(seleccionado || hover) && seccion.tipo !== 'compuesta'"
      :config="bordeSeleccionConfig"
    />

    <!-- Renderizar contenido según tipo -->
    <template v-if="seccion.tipo === 'ventana' && seccion.tipoVentanaId">
      <!-- Icono de tipo de ventana -->
      <v-text
        :config="{
          x: x + anchoSeccion / 2,
          y: y + altoSeccion / 2,
          text: getNombreTipoVentana(seccion.tipoVentanaId),
          fontSize: 14,
          fill: '#333',
          align: 'center',
          width: anchoSeccion,
          offsetY: 7,
        }"
      />
      
      <!-- Icono visual -->
      <v-line
        :config="{
          points: [
            x + 10, y + 10,
            x + anchoSeccion - 10, y + 10,
            x + anchoSeccion - 10, y + altoSeccion - 10,
            x + 10, y + altoSeccion - 10,
          ],
          closed: true,
          stroke: '#2196F3',
          strokeWidth: 2,
          dash: [5, 5],
        }"
      />
    </template>

    <template v-else-if="seccion.tipo === 'compuesta' && seccion.subsecciones?.length > 0">
      <!-- Divisor visual -->
      <v-line
        v-if="seccion.orientacion === 'horizontal'"
        :config="divisorHorizontalConfig"
      />
      <v-line
        v-if="seccion.orientacion === 'vertical'"
        :config="divisorVerticalConfig"
      />

      <!-- Renderizar subsecciones recursivamente -->
      <SeccionArmador
        v-for="(sub, idx) in seccion.subsecciones"
        :key="`${path}.${idx}`"
        :seccion="sub"
        :x="getSubseccionX(idx)"
        :y="getSubseccionY(idx)"
        :ancho-disponible="getSubseccionAncho(idx)"
        :alto-disponible="getSubseccionAlto(idx)"
        :escala="escala"
        :nivel="nivel + 1"
        :path="`${path}.${idx}`"
        :path-seleccionado="pathSeleccionado"
        :tipos-ventana="tiposVentana"
        :perfil-divisor-h="perfilDivisorH"
        :perfil-divisor-v="perfilDivisorV"
        @seleccionar="$emit('seleccionar', $event)"
        @dividir="$emit('dividir', $event)"
        @asignar-tipo="$emit('asignar-tipo', $event)"
        @eliminar="$emit('eliminar', $event)"
      />
    </template>

    <template v-else>
      <!-- Sección vacía - mostrar texto de ayuda -->
      <v-text
        :config="{
          x: x + anchoSeccion / 2,
          y: y + altoSeccion / 2 - 10,
          text: 'Click para dividir',
          fontSize: 12,
          fill: '#999',
          align: 'center',
          width: anchoSeccion,
          offsetY: 6,
        }"
      />
      <v-text
        :config="{
          x: x + anchoSeccion / 2,
          y: y + altoSeccion / 2 + 10,
          text: 'o asignar ventana',
          fontSize: 10,
          fill: '#999',
          align: 'center',
          width: anchoSeccion,
          offsetY: 5,
        }"
      />
    </template>

    <!-- Indicador de nivel (para debug) -->
    <v-text
      v-if="false"
      :config="{
        x: x + 5,
        y: y + 5,
        text: `L${nivel}`,
        fontSize: 10,
        fill: '#666',
      }"
    />
  </v-group>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  seccion: { type: Object, required: true },
  x: { type: Number, required: true },
  y: { type: Number, required: true },
  anchoDisponible: { type: Number, required: true },
  altoDisponible: { type: Number, required: true },
  escala: { type: Number, default: 1 },
  nivel: { type: Number, default: 0 },
  path: { type: String, required: true },
  pathSeleccionado: { type: String, default: null },
  tiposVentana: { type: Array, default: () => [] },
  perfilDivisorH: { type: Number, default: null },
  perfilDivisorV: { type: Number, default: null },
})

const emit = defineEmits(['seleccionar', 'dividir', 'asignar-tipo', 'eliminar'])

const hover = ref(false)

// Determinar si esta sección está seleccionada
const seleccionado = computed(() => props.pathSeleccionado === props.path)

// Dimensiones de esta sección
const anchoSeccion = computed(() => {
  if (props.seccion.anchoFijo) {
    return Math.max(20, props.seccion.anchoFijo * props.escala)
  }
  return Math.max(20, props.anchoDisponible * (props.seccion.porcentaje || 100) / 100)
})

const altoSeccion = computed(() => {
  if (props.seccion.altoFijo) {
    return Math.max(20, props.seccion.altoFijo * props.escala)
  }
  return Math.max(20, props.altoDisponible * (props.seccion.porcentaje || 100) / 100)
})

// Configuración del área
const areaConfig = computed(() => ({
  x: props.x,
  y: props.y,
  width: Math.max(1, anchoSeccion.value),
  height: Math.max(1, altoSeccion.value),
  fill: getColorFondo(),
  stroke: '#999',
  strokeWidth: 1,
  cornerRadius: Math.min(2, Math.max(0, anchoSeccion.value / 10, altoSeccion.value / 10)),
  listening: true,
}))

const bordeSeleccionConfig = computed(() => ({
  x: props.x - 2,
  y: props.y - 2,
  width: Math.max(5, anchoSeccion.value + 4),
  height: Math.max(5, altoSeccion.value + 4),
  stroke: seleccionado.value ? '#FF5722' : '#2196F3',
  strokeWidth: 3,
  dash: [8, 4],
  cornerRadius: Math.min(2, Math.max(0, anchoSeccion.value / 10, altoSeccion.value / 10)),
}))

// Configuración del divisor
const DIVISOR_ANCHO = 8

const divisorHorizontalConfig = computed(() => {
  const totalSubsecciones = props.seccion.subsecciones?.length || 0
  const posicionDivisor = props.y + (altoSeccion.value * (props.seccion.subsecciones?.[0]?.porcentaje || 50) / 100)
  
  return {
    points: [
      props.x,
      posicionDivisor - DIVISOR_ANCHO / 2,
      props.x + anchoSeccion.value,
      posicionDivisor - DIVISOR_ANCHO / 2,
      props.x + anchoSeccion.value,
      posicionDivisor + DIVISOR_ANCHO / 2,
      props.x,
      posicionDivisor + DIVISOR_ANCHO / 2,
    ],
    closed: true,
    fill: '#757575',
    stroke: '#424242',
    strokeWidth: 1,
  }
})

const divisorVerticalConfig = computed(() => {
  const posicionDivisor = props.x + (anchoSeccion.value * (props.seccion.subsecciones?.[0]?.porcentaje || 50) / 100)
  
  return {
    points: [
      posicionDivisor - DIVISOR_ANCHO / 2,
      props.y,
      posicionDivisor + DIVISOR_ANCHO / 2,
      props.y,
      posicionDivisor + DIVISOR_ANCHO / 2,
      props.y + altoSeccion.value,
      posicionDivisor - DIVISOR_ANCHO / 2,
      props.y + altoSeccion.value,
    ],
    closed: true,
    fill: '#757575',
    stroke: '#424242',
    strokeWidth: 1,
  }
})

// Funciones para calcular posiciones de subsecciones
function getSubseccionX(idx) {
  if (props.seccion.orientacion === 'vertical') {
    // División vertical: las secciones están lado a lado
    let xAcum = props.x
    for (let i = 0; i < idx; i++) {
      xAcum += getSubseccionAncho(i)
      if (i < idx - 1) xAcum += DIVISOR_ANCHO
    }
    return xAcum
  }
  return props.x
}

function getSubseccionY(idx) {
  if (props.seccion.orientacion === 'horizontal') {
    // División horizontal: las secciones están apiladas
    let yAcum = props.y
    for (let i = 0; i < idx; i++) {
      yAcum += getSubseccionAlto(i)
      if (i < idx - 1) yAcum += DIVISOR_ANCHO
    }
    return yAcum
  }
  return props.y
}

function getSubseccionAncho(idx) {
  if (props.seccion.orientacion === 'vertical') {
    const sub = props.seccion.subsecciones[idx]
    return anchoSeccion.value * (sub.porcentaje || 50) / 100
  }
  return anchoSeccion.value
}

function getSubseccionAlto(idx) {
  if (props.seccion.orientacion === 'horizontal') {
    const sub = props.seccion.subsecciones[idx]
    return altoSeccion.value * (sub.porcentaje || 50) / 100
  }
  return altoSeccion.value
}

// Utilidades
function getColorFondo() {
  if (props.seccion.tipo === 'ventana') {
    return '#E3F2FD' // Azul claro para ventanas asignadas
  } else if (props.seccion.tipo === 'compuesta') {
    return 'transparent' // Transparente para compuestas
  }
  return '#FAFAFA' // Gris muy claro para vacías
}

function getNombreTipoVentana(tipoId) {
  const tipo = props.tiposVentana.find(t => t.id === tipoId)
  return tipo ? tipo.nombre : `Tipo ${tipoId}`
}

function handleClick(e) {
  // Siempre emitir la selección
  emit('seleccionar', props.path)
  // Detener propagación para que no llegue al stage
  e.cancelBubble = true
}

function handleMouseEnter(e) {
  if (e.target.getLayer()) {
    e.target.getLayer().getStage().container().style.cursor = 'pointer'
  }
  hover.value = true
}

function handleMouseLeave(e) {
  if (e.target.getLayer()) {
    e.target.getLayer().getStage().container().style.cursor = 'default'
  }
  hover.value = false
}
</script>

<style scoped>
/* Estilos específicos si es necesario */
</style>
