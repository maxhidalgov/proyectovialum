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
    }
  }

  return {
    points,
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
  }
}

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
})

// SIN OFFSET ni ESCALA - usar dimensiones reales 1:1
const offset = 0
const escala = 1
const marcoAnchoMM = 60 // Ancho del marco en milímetros

const marcoAncho = marcoAnchoMM * escala
const screenWidth = props.ancho * escala
const screenHeight = props.alto * escala

const stageConfig = computed(() => ({
  width: props.ancho,
  height: props.alto,
}))

// Marco con mitras
const topMitra = computed(() =>
  getMitraMarco([
    offset, offset,
    offset + screenWidth, offset,
    offset + screenWidth - marcoAncho, offset + marcoAncho,
    offset + marcoAncho, offset + marcoAncho,
  ])
)

const rightMitra = computed(() =>
  getMitraMarco([
    offset + screenWidth, offset,
    offset + screenWidth, offset + screenHeight,
    offset + screenWidth - marcoAncho, offset + screenHeight - marcoAncho,
    offset + screenWidth - marcoAncho, offset + marcoAncho,
  ])
)

const bottomMitra = computed(() =>
  getMitraMarco([
    offset, offset + screenHeight,
    offset + screenWidth, offset + screenHeight,
    offset + screenWidth - marcoAncho, offset + screenHeight - marcoAncho,
    offset + marcoAncho, offset + screenHeight - marcoAncho,
  ])
)

const leftMitra = computed(() =>
  getMitraMarco([
    offset, offset,
    offset, offset + screenHeight,
    offset + marcoAncho, offset + screenHeight - marcoAncho,
    offset + marcoAncho, offset + marcoAncho,
  ])
)

const glassConfig = computed(() => ({
  x: offset + marcoAncho,
  y: offset + marcoAncho,
  width: screenWidth - 2 * marcoAncho,
  height: screenHeight - 2 * marcoAncho,
  fill: 'rgba(135, 206, 235, 0.3)',
  stroke: 'rgba(0, 0, 0, 0.2)',
  strokeWidth: 1,
}))
</script>
