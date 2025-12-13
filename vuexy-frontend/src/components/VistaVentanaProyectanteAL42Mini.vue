<template>
  <v-group :config="props.config">
    <!-- Vidrio general -->
    <v-rect v-bind="vidrioGeneral" />
    
    <!-- Marco exterior con mitras -->
    <v-line v-bind="topMitra" />
    <v-line v-bind="rightMitra" />
    <v-line v-bind="bottomMitra" />
    <v-line v-bind="leftMitra" />
    
    <!-- Marco de hoja móvil con mitras -->
    <v-line v-bind="hojaTopMitra" />
    <v-line v-bind="hojaRightMitra" />
    <v-line v-bind="hojaBottomMitra" />
    <v-line v-bind="hojaLeftMitra" />
    
    <!-- Líneas de apertura -->
    <v-line v-bind="lineaApertura1" />
    <v-line v-bind="lineaApertura2" />
  </v-group>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
  config: { type: Object, default: () => ({ x: 0, y: 0 }) },
  escala: { type: Number, required: true },
})

const marcoAnchoOriginal = 54
const offset = 0
const hojaSeparacion = 3
const marcoAncho = computed(() => marcoAnchoOriginal * props.escala)
const hojaAncho = computed(() => marcoAncho.value * 0.65)
const screenWidth = computed(() => props.ancho * props.escala)
const screenHeight = computed(() => props.alto * props.escala)

const colorMarcoHex = computed(() => {
  const colorMap = {
    blanco: '#D8D8D8',
    negro: '#2C2C2C',
    bronce: '#CD7F32',
    'bronce oscuro': '#5C4033',
    champagne: '#F7E7CE',
    plata: '#C0C0C0',
    gris: '#808080',
    grafito: '#4A4A4A',
  }
  return colorMap[props.colorMarco?.toLowerCase()] || '#D8D8D8'
})

const vidrioGeneral = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
  strokeWidth: 0.5,
}))

// Mitras del marco exterior
const topMitra = computed(() => ({
  points: [
    offset, offset,
    offset + screenWidth.value, offset,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const rightMitra = computed(() => ({
  points: [
    offset + screenWidth.value, offset,
    offset + screenWidth.value, offset + screenHeight.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const bottomMitra = computed(() => ({
  points: [
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const leftMitra = computed(() => ({
  points: [
    offset, offset + screenHeight.value,
    offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

// Área de hoja
const hojaX = computed(() => offset + marcoAncho.value + hojaSeparacion * props.escala)
const hojaY = computed(() => offset + marcoAncho.value + hojaSeparacion * props.escala)
const hojaW = computed(() => screenWidth.value - marcoAncho.value * 2 - hojaSeparacion * 2 * props.escala)
const hojaH = computed(() => screenHeight.value - marcoAncho.value * 2 - hojaSeparacion * 2 * props.escala)

// Mitras de la hoja móvil
const hojaTopMitra = computed(() => ({
  points: [
    hojaX.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const hojaRightMitra = computed(() => ({
  points: [
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const hojaBottomMitra = computed(() => ({
  points: [
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

const hojaLeftMitra = computed(() => ({
  points: [
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.5,
}))

// Líneas de apertura en V
const lineaApertura1 = computed(() => ({
  points: [
    hojaX.value + hojaAncho.value + 3,
    hojaY.value + hojaAncho.value + 3,
    hojaX.value + hojaW.value - hojaAncho.value - 3,
    hojaY.value + hojaH.value / 2,
  ],
  stroke: 'black',
  strokeWidth: 1,
  dash: [4, 2],
}))

const lineaApertura2 = computed(() => ({
  points: [
    hojaX.value + hojaAncho.value + 3,
    hojaY.value + hojaH.value - hojaAncho.value - 3,
    hojaX.value + hojaW.value - hojaAncho.value - 3,
    hojaY.value + hojaH.value / 2,
  ],
  stroke: 'black',
  strokeWidth: 1,
  dash: [4, 2],
}))
</script>
