<template>
  <v-stage :config="stageConfig">
    <v-layer>
      <!-- Marco exterior con mitras -->
      <v-line :config="topMitra" />
      <v-line :config="rightMitra" />
      <v-line :config="bottomMitra" />
      <v-line :config="leftMitra" />

      <!-- Vidrio general -->
      <v-rect :config="glassConfig" />

      <!-- Mitras de hoja interior -->
      <v-line :config="hojaTopMitra" />
      <v-line :config="hojaRightMitra" />
      <v-line :config="hojaBottomMitra" />
      <v-line :config="hojaLeftMitra" />

      <!-- Líneas de apertura en V (sin manilla en armador) -->
      <v-line :config="lineaApertura1" />
      <v-line :config="lineaApertura2" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed } from 'vue'

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

const texturas = {
  roble: new Image(),
  nogal: new Image(),
}
texturas.roble.src = new URL('@/assets/images/roble.png', import.meta.url).href
texturas.nogal.src = new URL('@/assets/images/nogal.png', import.meta.url).href

const colorMarcoHex = computed(() => {
  const nombre = typeof props.colorMarco === 'object' ? props.colorMarco?.nombre?.toLowerCase() : props.colorMarco?.toLowerCase()
  return colorHexMap[nombre] || '#ffffff'
})

function getMitraMarco(points, usoHoja = false) {
  const nombre = typeof props.colorMarco === 'object' ? props.colorMarco?.nombre?.toLowerCase() : props.colorMarco?.toLowerCase()

  if (['roble', 'nogal'].includes(nombre) && texturas[nombre]) {
    return {
      points,
      closed: true,
      fillPatternImage: texturas[nombre],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: usoHoja ? 0.15 : 0.2, y: usoHoja ? 0.15 : 0.2 },
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

// SIN OFFSET ni ESCALA - usar dimensiones reales 1:1
const offset = 0
const escala = 1
const marcoAnchoMM = 60
const hojaSeparacion = 0

const marcoAncho = marcoAnchoMM * escala
const screenWidth = props.ancho * escala
const screenHeight = props.alto * escala

const stageConfig = computed(() => ({
  width: props.ancho,
  height: props.alto,
}))

// Marco exterior
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
    offset + screenWidth, offset + screenHeight,
    offset, offset + screenHeight,
    offset + marcoAncho, offset + screenHeight - marcoAncho,
    offset + screenWidth - marcoAncho, offset + screenHeight - marcoAncho,
  ])
)
const leftMitra = computed(() =>
  getMitraMarco([
    offset, offset + screenHeight,
    offset, offset,
    offset + marcoAncho, offset + marcoAncho,
    offset + marcoAncho, offset + screenHeight - marcoAncho,
  ])
)

// Vidrio general
const glassConfig = computed(() => ({
  x: offset + marcoAncho,
  y: offset + marcoAncho,
  width: screenWidth - marcoAncho * 2,
  height: screenHeight - marcoAncho * 2,
  fill: 'rgba(135, 206, 235, 0.3)',
  stroke: 'rgba(0, 0, 0, 0.2)',
  strokeWidth: 1,
}))

// Marco hoja proyectante (mitras internas)
const hojaX = computed(() => glassConfig.value.x + hojaSeparacion * escala)
const hojaY = computed(() => glassConfig.value.y + hojaSeparacion * escala)
const hojaW = computed(() => glassConfig.value.width - hojaSeparacion * 2 * escala)
const hojaH = computed(() => glassConfig.value.height - hojaSeparacion * 2 * escala)
const hojaAncho = computed(() => marcoAncho * 0.8)

const hojaTopMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
  ], true)
)
const hojaRightMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
  ], true)
)
const hojaBottomMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ], true)
)
const hojaLeftMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ], true)
)

// Líneas de apertura en V (proyectante)
const lineaApertura1 = computed(() => ({
  points: [
    hojaX.value + 5,
    hojaY.value + 5,
    hojaX.value + hojaW.value / 2,
    hojaY.value + hojaH.value - 5,
  ],
  stroke: 'black',
  dash: [10, 5],
  strokeWidth: 2,
}))
const lineaApertura2 = computed(() => ({
  points: [
    hojaX.value + hojaW.value - 5,
    hojaY.value + 5,
    hojaX.value + hojaW.value / 2,
    hojaY.value + hojaH.value - 5,
  ],
  stroke: 'black',
  dash: [10, 5],
  strokeWidth: 2,
}))
</script>
