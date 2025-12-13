<template>
  <v-stage :config="stageConfig">
    <v-layer>
      <!-- Marco principal -->
      <v-rect v-bind="marcoTop" />
      <v-rect v-bind="marcoRight" />
      <v-rect v-bind="marcoBottom" />
      <v-rect v-bind="marcoLeft" />

      <!-- Hoja 2 (detrás o adelante según estado) -->
      <template v-if="!hoja1Adelante">
        <v-rect v-bind="vidrio2" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Top" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Right" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Bottom" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Left" @click="toggleHoja2" />
        
        <!-- Flecha indicando movimiento hoja 2 (solo si es móvil) -->
        <template v-if="hoja2Movil">
          <v-line :config="flecha2Config" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
          <v-line :config="flecha2Punta1" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
          <v-line :config="flecha2Punta2" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
        </template>
        <!-- Indicador de hoja fija -->
        <v-text v-else :config="texto2Fijo" />
      </template>

      <!-- Hoja 1 (adelante o detrás según estado) -->
      <v-rect v-bind="vidrio1" @click="toggleHoja1" />
      <v-rect v-bind="hoja1Top" @click="toggleHoja1" />
      <v-rect v-bind="hoja1Right" @click="toggleHoja1" />
      <v-rect v-bind="hoja1Bottom" @click="toggleHoja1" />
      <v-rect v-bind="hoja1Left" @click="toggleHoja1" />
      
      <!-- Flecha indicando movimiento hoja 1 (solo si es móvil) -->
      <template v-if="hoja1Movil">
        <v-line :config="flecha1Config" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
        <v-line :config="flecha1Punta1" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
        <v-line :config="flecha1Punta2" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
      </template>
      <!-- Indicador de hoja fija -->
      <v-text v-else :config="texto1Fijo" />

      <!-- Hoja 2 si va adelante -->
      <template v-if="hoja1Adelante">
        <v-rect v-bind="vidrio2" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Top" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Right" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Bottom" @click="toggleHoja2" />
        <v-rect v-bind="hoja2Left" @click="toggleHoja2" />
        
        <!-- Flecha indicando movimiento hoja 2 (solo si es móvil) -->
        <template v-if="hoja2Movil">
          <v-line :config="flecha2Config" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
          <v-line :config="flecha2Punta1" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
          <v-line :config="flecha2Punta2" @mousedown="cambiarOrden" @touchstart="cambiarOrden" />
        </template>
        <!-- Indicador de hoja fija -->
        <v-text v-else :config="texto2Fijo" />
      </template>
    </v-layer>
  </v-stage>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
})

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
}

const hoja1Adelante = ref(true)
const hoja1Movil = ref(true)
const hoja2Movil = ref(true)

const cambiarOrden = () => {
  console.log('🔄 Cambiando orden de hojas correderas')
  hoja1Adelante.value = !hoja1Adelante.value
}

const toggleHoja1 = () => {
  hoja1Movil.value = !hoja1Movil.value
  console.log('🪟 Hoja 1:', hoja1Movil.value ? 'Móvil' : 'Fija')
}

const toggleHoja2 = () => {
  hoja2Movil.value = !hoja2Movil.value
  console.log('🪟 Hoja 2:', hoja2Movil.value ? 'Móvil' : 'Fija')
}

const stageConfig = computed(() => ({
  width: props.ancho,
  height: props.alto,
}))

const marcoAnchoMM = 25
const color = computed(() => colorHexMap[props.colorMarco] || '#ffffff')

// Marco exterior
const marcoTop = computed(() => ({
  x: 0,
  y: 0,
  width: props.ancho,
  height: marcoAnchoMM,
  fill: color.value,
  stroke: '#333',
  strokeWidth: 1,
}))

const marcoRight = computed(() => ({
  x: props.ancho - marcoAnchoMM,
  y: 0,
  width: marcoAnchoMM,
  height: props.alto,
  fill: color.value,
  stroke: '#333',
  strokeWidth: 1,
}))

const marcoBottom = computed(() => ({
  x: 0,
  y: props.alto - marcoAnchoMM,
  width: props.ancho,
  height: marcoAnchoMM,
  fill: color.value,
  stroke: '#333',
  strokeWidth: 1,
}))

const marcoLeft = computed(() => ({
  x: 0,
  y: 0,
  width: marcoAnchoMM,
  height: props.alto,
  fill: color.value,
  stroke: '#333',
  strokeWidth: 1,
}))

// Dimensiones interiores
const interiorW = computed(() => props.ancho - 2 * marcoAnchoMM)
const interiorH = computed(() => props.alto - 2 * marcoAnchoMM)
const interiorX = marcoAnchoMM
const interiorY = marcoAnchoMM

const hojaAncho = computed(() => interiorW.value / 2)
const hojaAlto = computed(() => interiorH.value)
const hojaMarco = 25

// Hoja 1 (izquierda - adelante)
const hoja1X = computed(() => interiorX)
const hoja1Y = computed(() => interiorY)

const vidrio1 = computed(() => ({
  x: hoja1X.value + hojaMarco,
  y: hoja1Y.value + hojaMarco,
  width: hojaAncho.value - 2 * hojaMarco,
  height: hojaAlto.value - 2 * hojaMarco,
  fill: 'rgba(173, 216, 230, 0.3)',
  stroke: '#aaa',
  strokeWidth: 0.5,
  cursor: 'pointer',
}))

const hoja1Top = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value,
  width: hojaAncho.value,
  height: hojaMarco,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja1Right = computed(() => ({
  x: hoja1X.value + hojaAncho.value - hojaMarco,
  y: hoja1Y.value,
  width: hojaMarco,
  height: hojaAlto.value,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja1Bottom = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value + hojaAlto.value - hojaMarco,
  width: hojaAncho.value,
  height: hojaMarco,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja1Left = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value,
  width: hojaMarco,
  height: hojaAlto.value,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

// Hoja 2 (derecha - detrás)
const hoja2X = computed(() => interiorX + hojaAncho.value)
const hoja2Y = computed(() => interiorY)

const vidrio2 = computed(() => ({
  x: hoja2X.value + hojaMarco,
  y: hoja2Y.value + hojaMarco,
  width: hojaAncho.value - 2 * hojaMarco,
  height: hojaAlto.value - 2 * hojaMarco,
  fill: 'rgba(173, 216, 230, 0.3)',
  stroke: '#aaa',
  strokeWidth: 0.5,
  cursor: 'pointer',
}))

const hoja2Top = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value,
  width: hojaAncho.value,
  height: hojaMarco,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja2Right = computed(() => ({
  x: hoja2X.value + hojaAncho.value - hojaMarco,
  y: hoja2Y.value,
  width: hojaMarco,
  height: hojaAlto.value,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja2Bottom = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value + hojaAlto.value - hojaMarco,
  width: hojaAncho.value,
  height: hojaMarco,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

const hoja2Left = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value,
  width: hojaMarco,
  height: hojaAlto.value,
  fill: color.value,
  stroke: '#666',
  strokeWidth: 1,
}))

// Flechas indicando movimiento (clickeables para cambiar orden)
const flechaLargo = 120
const flechaPunta = 25

// Flecha horizontal para hoja 1 (izquierda)
const flecha1Config = computed(() => ({
  points: [
    hoja1X.value + hojaAncho.value / 2 - flechaLargo / 2,
    hoja1Y.value + hojaAlto.value / 2,
    hoja1X.value + hojaAncho.value / 2 + flechaLargo / 2,
    hoja1Y.value + hojaAlto.value / 2,
  ],
  stroke: hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

const flecha1Punta1 = computed(() => ({
  points: [
    hoja1X.value + hojaAncho.value / 2 + flechaLargo / 2,
    hoja1Y.value + hojaAlto.value / 2,
    hoja1X.value + hojaAncho.value / 2 + flechaLargo / 2 - flechaPunta,
    hoja1Y.value + hojaAlto.value / 2 - flechaPunta / 2,
  ],
  stroke: hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

const flecha1Punta2 = computed(() => ({
  points: [
    hoja1X.value + hojaAncho.value / 2 + flechaLargo / 2,
    hoja1Y.value + hojaAlto.value / 2,
    hoja1X.value + hojaAncho.value / 2 + flechaLargo / 2 - flechaPunta,
    hoja1Y.value + hojaAlto.value / 2 + flechaPunta / 2,
  ],
  stroke: hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

// Flecha horizontal para hoja 2 (derecha)
const flecha2Config = computed(() => ({
  points: [
    hoja2X.value + hojaAncho.value / 2 - flechaLargo / 2,
    hoja2Y.value + hojaAlto.value / 2,
    hoja2X.value + hojaAncho.value / 2 + flechaLargo / 2,
    hoja2Y.value + hojaAlto.value / 2,
  ],
  stroke: !hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

const flecha2Punta1 = computed(() => ({
  points: [
    hoja2X.value + hojaAncho.value / 2 - flechaLargo / 2,
    hoja2Y.value + hojaAlto.value / 2,
    hoja2X.value + hojaAncho.value / 2 - flechaLargo / 2 + flechaPunta,
    hoja2Y.value + hojaAlto.value / 2 - flechaPunta / 2,
  ],
  stroke: !hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

const flecha2Punta2 = computed(() => ({
  points: [
    hoja2X.value + hojaAncho.value / 2 - flechaLargo / 2,
    hoja2Y.value + hojaAlto.value / 2,
    hoja2X.value + hojaAncho.value / 2 - flechaLargo / 2 + flechaPunta,
    hoja2Y.value + hojaAlto.value / 2 + flechaPunta / 2,
  ],
  stroke: !hoja1Adelante.value ? '#FF5722' : '#9E9E9E',
  strokeWidth: 5,
  lineCap: 'round',
  hitStrokeWidth: 20,
  cursor: 'pointer',
}))

// Textos indicadores de hoja fija
const texto1Fijo = computed(() => ({
  x: hoja1X.value + hojaAncho.value / 2,
  y: hoja1Y.value + hojaAlto.value / 2,
  text: 'FIJA',
  fontSize: 30,
  fontStyle: 'bold',
  fill: '#757575',
  align: 'center',
  offsetX: 30,
  offsetY: 15,
}))

const texto2Fijo = computed(() => ({
  x: hoja2X.value + hojaAncho.value / 2,
  y: hoja2Y.value + hojaAlto.value / 2,
  text: 'FIJA',
  fontSize: 30,
  fontStyle: 'bold',
  fill: '#757575',
  align: 'center',
  offsetX: 30,
  offsetY: 15,
}))
</script>
