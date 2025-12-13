<template>
  <v-group :config="props.config">
    <!-- Vidrio -->
    <v-rect v-bind="vidrio" />
    
    <!-- Marco exterior con mitras -->
    <v-line v-bind="topMitra" />
    <v-line v-bind="rightMitra" />
    <v-line v-bind="bottomMitra" />
    <v-line v-bind="leftMitra" />
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
const marcoAncho = computed(() => marcoAnchoOriginal * props.escala)
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

const vidrio = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
  strokeWidth: 0.5,
}))

// Mitras del marco (polígonos trapezoidales)
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
</script>
