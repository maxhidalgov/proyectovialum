<template>
  <v-group :x="props.config?.x || 0" :y="props.config?.y || 0">
    <!-- Mitras -->
    <v-line v-bind="topMitra" />
    <v-line v-bind="rightMitra" />
    <v-line v-bind="bottomMitra" />
    <v-line v-bind="leftMitra" />

    <!-- Vidrio -->
    <v-rect v-bind="glassConfig" />

    <!-- Etiquetas de medida -->
    <v-text v-bind="widthLabel" />
    <v-text v-bind="heightLabel" />
  </v-group>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  config: { type: Object, default: () => ({ x: 0, y: 0 }) },
  escala: { type: Number, default: null } // si no se envía, se calcula
})

// Colores
const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
}

// Texturas
const texturas = {
  roble: new Image(),
  nogal: new Image(),
}
texturas.roble.src = new URL('@/assets/images/roble.png', import.meta.url).href
texturas.nogal.src = new URL('@/assets/images/nogal.png', import.meta.url).href

// Escala automática
const maxCanvasSize = 300
const escalaCalculada = computed(() => {
  if (props.escala) return props.escala
  const escalaAncho = maxCanvasSize / props.ancho
  const escalaAlto = maxCanvasSize / props.alto
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9
})

// Medidas
const marcoAnchoOriginal = 60
const marcoAncho = computed(() => marcoAnchoOriginal * escalaCalculada.value)
const screenWidth = computed(() => props.ancho * escalaCalculada.value)
const screenHeight = computed(() => props.alto * escalaCalculada.value)
const offset = 0

// Color marco
const colorMarcoHex = computed(() => {
  if (typeof props.colorMarco === 'string') {
    return colorHexMap[props.colorMarco.toLowerCase()] || '#ffffff'
  }
  if (props.colorMarco?.nombre) {
    return colorHexMap[props.colorMarco.nombre.toLowerCase()] || '#ffffff'
  }
  return '#ffffff'
})

// Función para mitras
function getMitraMarco(points) {
  const nombre = typeof props.colorMarco === 'object'
    ? props.colorMarco?.nombre?.toLowerCase()
    : props.colorMarco?.toLowerCase()

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

// Mitras
const topMitra = computed(() =>
  getMitraMarco([
    offset, offset,
    offset + screenWidth.value, offset,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + marcoAncho.value,
  ])
)
const rightMitra = computed(() =>
  getMitraMarco([
    offset + screenWidth.value, offset,
    offset + screenWidth.value, offset + screenHeight.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  ])
)
const bottomMitra = computed(() =>
  getMitraMarco([
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ])
)
const leftMitra = computed(() =>
  getMitraMarco([
    offset, offset + screenHeight.value,
    offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ])
)

// Vidrio
const glassConfig = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
}))

// Etiquetas
const fontSize = computed(() => Math.min(Math.max(props.ancho / 100, 20), 40))
const widthLabel = computed(() => ({
  x: offset + screenWidth.value / 2 - 30,
  y: offset + screenHeight.value + 10,
  text: `${props.ancho}mm`,
  fontSize: fontSize.value,
  fill: 'black',
}))
const heightLabel = computed(() => ({
  x: offset - 25,
  y: offset + screenHeight.value / 2,
  text: `${props.alto}mm`,
  fontSize: fontSize.value,
  fill: 'black',
  rotation: -90,
  offsetX: fontSize.value / 2,
}))
</script>
