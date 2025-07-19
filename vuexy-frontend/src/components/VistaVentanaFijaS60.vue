<template>
  <div>
    <v-sheet height="400" class="pa-4" color="#f9f8fa">
      <v-stage :config="stageConfig">
        <v-layer>
          <!-- Marco exterior con biseles -->
          <v-line :config="topMitra" />
          <v-line :config="rightMitra" />
          <v-line :config="bottomMitra" />
          <v-line :config="leftMitra" />

          <!-- Vidrio -->
          <v-rect :config="glassConfig" />

          <!-- Etiquetas -->
          <v-text :config="widthLabel" />
          <v-text :config="heightLabel" />
        </v-layer>
      </v-stage>
    </v-sheet>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#000000',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
  // agrega más colores según los que uses en tu sistema
}

const colorMarcoHex = computed(() => {
  if (typeof props.colorMarco === 'string') {
    return colorHexMap[props.colorMarco.toLowerCase()] || '#ffffff'
  }
  if (typeof props.colorMarco === 'object' && props.colorMarco?.nombre) {
    return colorHexMap[props.colorMarco.nombre.toLowerCase()] || '#ffffff'
  }
  return '#ffffff'
})

const props = defineProps({
  ancho: {
    type: Number,
    required: true
  },
  alto: {
    type: Number,
    required: true
  },
  colorMarco: { type: String, default: '#000000' }, // HEX o nombre de color
})

const offset = 50
const marcoAnchoOriginal = 60

// Escala dinámica para que todo quepa dentro del stage
const maxCanvasSize = 300
const escala = computed(() => {
  const escalaAncho = maxCanvasSize / props.ancho
  const escalaAlto = maxCanvasSize / props.alto
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9 // Reducimos un poco para márgenes
})

const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)

const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

const stageConfig = {
  width: 400,
  height: 400,
}

// Cálculo de vértices para el marco con mitras
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
}))

const glassConfig = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
}))

// Etiquetas con tamaño de fuente dinámico
const fontSize = computed(() => {
  const base = Math.max(props.ancho, props.alto)
  return Math.min(Math.max((base / 100), 20), 40) // mínimo 10, máximo 40
})

const widthLabel = computed(() => ({
  x: offset + screenWidth.value / 2 - 30,
  y: offset + screenHeight.value + 10,
  text: `${props.ancho}mm`,
  fontSize: fontSize.value,
  fill: 'black',
}))

const heightLabel = computed(() => ({
  x: offset - 25, // distancia desde el marco
  y: offset + screenHeight.value / 2,
  text: `${props.alto}mm`,
  fontSize: fontSize.value,
  fill: 'black',
  rotation: -90,
  offsetX: fontSize.value / 2, // clave para rotación
}))
</script>
