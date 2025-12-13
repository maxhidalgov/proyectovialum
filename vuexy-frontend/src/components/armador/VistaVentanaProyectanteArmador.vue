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

      <!-- Manilla -->
      <Manilla
        :x="hojaX + hojaW / 2"
        :y="hojaY + hojaH + 2"
        :width="20"
        :height="6"
        :rotation="90"
        :offsetX="10"
        :offsetY="2.5"
      />

      <!-- Líneas de apertura en V -->
      <v-line :config="lineaApertura1" />
      <v-line :config="lineaApertura2" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed } from 'vue'
import Manilla from '@/components/Manilla.vue'

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

// SIN escala - usar dimensiones reales 1:1
const marcoAnchoMM = 60
const hojaSeparacion = 0

const screenWidth = computed(() => props.ancho)
const screenHeight = computed(() => props.alto)

const stageConfig = computed(() => ({
  width: props.ancho,
  height: props.alto,
}))

// Marco exterior (sin offset)
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

// Vidrio general
const glassConfig = computed(() => ({
  x: marcoAnchoMM,
  y: marcoAnchoMM,
  width: screenWidth.value - marcoAnchoMM * 2,
  height: screenHeight.value - marcoAnchoMM * 2,
  fill: '#b3e5fc',
  stroke: 'black',
  strokeWidth: 0.5,
}))

// Marco hoja proyectante (mitras internas)
const hojaX = computed(() => glassConfig.value.x + hojaSeparacion)
const hojaY = computed(() => glassConfig.value.y + hojaSeparacion)
const hojaW = computed(() => glassConfig.value.width - hojaSeparacion * 2)
const hojaH = computed(() => glassConfig.value.height - hojaSeparacion * 2)
const hojaAnchoMM = marcoAnchoMM * 0.8

const hojaTopMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value - hojaAnchoMM, hojaY.value + hojaAnchoMM,
    hojaX.value + hojaAnchoMM, hojaY.value + hojaAnchoMM,
  ], true)
)
const hojaRightMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value + hojaW.value - hojaAnchoMM, hojaY.value + hojaH.value - hojaAnchoMM,
    hojaX.value + hojaW.value - hojaAnchoMM, hojaY.value + hojaAnchoMM,
  ], true)
)
const hojaBottomMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value + hojaAnchoMM, hojaY.value + hojaH.value - hojaAnchoMM,
    hojaX.value + hojaW.value - hojaAnchoMM, hojaY.value + hojaH.value - hojaAnchoMM,
  ], true)
)
const hojaLeftMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value,
    hojaX.value + hojaAnchoMM, hojaY.value + hojaAnchoMM,
    hojaX.value + hojaAnchoMM, hojaY.value + hojaH.value - hojaAnchoMM,
  ], true)
)

// Líneas de apertura en V
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
