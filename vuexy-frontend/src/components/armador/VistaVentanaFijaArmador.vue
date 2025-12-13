<template>
  <v-stage :config="stageConfig">
    <v-layer>
      <!-- Marco exterior con biseles -->
      <v-line :config="topMitra" />
      <v-line :config="rightMitra" />
      <v-line :config="bottomMitra" />
      <v-line :config="leftMitra" />

      <!-- Vidrio -->
      <v-rect :config="glassConfig" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed } from 'vue'

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
}

const texturas = {
  roble: new Image(),
  nogal: new Image(),
}

texturas.roble.src = new URL('@/assets/images/roble.png', import.meta.url).href
texturas.nogal.src = new URL('@/assets/images/nogal.png', import.meta.url).href

const colorMarcoHex = computed(() => {
  let nombre = ''
  if (typeof props.colorMarco === 'object' && props.colorMarco?.nombre) {
    nombre = String(props.colorMarco.nombre).toLowerCase()
  } else if (typeof props.colorMarco === 'string') {
    nombre = props.colorMarco.toLowerCase()
  }
  return colorHexMap[nombre] || '#ffffff'
})

function getMitraMarco(points) {
  let nombre = ''
  if (typeof props.colorMarco === 'object' && props.colorMarco?.nombre) {
    nombre = String(props.colorMarco.nombre).toLowerCase()
  } else if (typeof props.colorMarco === 'string') {
    nombre = props.colorMarco.toLowerCase()
  }

  if (['roble', 'nogal'].includes(nombre) && texturas[nombre]) {
    return {
      points,
      closed: true,
      fillPatternImage: texturas[nombre],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: 0.2, y: 0.2 },
      stroke: 'black',
      strokeWidth: 1,
    }
  }

  return {
    points,
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 1,
  }
}

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
})

// SIN escala - usar dimensiones reales 1:1
const marcoAnchoMM = 60 // Ancho del marco en milímetros
const screenWidth = computed(() => props.ancho)
const screenHeight = computed(() => props.alto)

const stageConfig = computed(() => ({
  width: props.ancho,
  height: props.alto,
}))

// Cálculo de vértices para el marco con mitras (sin offset)
const topMitra = computed(() =>
  getMitraMarco([
    0, 0,
    screenWidth.value, 0,
    screenWidth.value - marcoAnchoMM, marcoAnchoMM,
    marcoAnchoMM, marcoAnchoMM,
  ])
)

const rightMitra = computed(() =>
  getMitraMarco([
    screenWidth.value, 0,
    screenWidth.value, screenHeight.value,
    screenWidth.value - marcoAnchoMM, screenHeight.value - marcoAnchoMM,
    screenWidth.value - marcoAnchoMM, marcoAnchoMM,
  ])
)

const bottomMitra = computed(() => 
  getMitraMarco([
    screenWidth.value, screenHeight.value,
    0, screenHeight.value,
    marcoAnchoMM, screenHeight.value - marcoAnchoMM,
    screenWidth.value - marcoAnchoMM, screenHeight.value - marcoAnchoMM,
  ])
)

const leftMitra = computed(() => 
  getMitraMarco([
    0, screenHeight.value,
    0, 0,
    marcoAnchoMM, marcoAnchoMM,
    marcoAnchoMM, screenHeight.value - marcoAnchoMM,
  ])
)

const glassConfig = computed(() => ({
  x: marcoAnchoMM,
  y: marcoAnchoMM,
  width: screenWidth.value - marcoAnchoMM * 2,
  height: screenHeight.value - marcoAnchoMM * 2,
  fill: '#b3e5fc',
  stroke: 'black',
  strokeWidth: 0.5,
}))
</script>
