<template>
  <v-stage :config="{ width: 400, height: 400 }">
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

</template>

<script setup>
import { computed } from 'vue'

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
  // agrega más colores según los que uses en tu sistema
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

function getMitraMarco(points, usoHoja = false) {
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
