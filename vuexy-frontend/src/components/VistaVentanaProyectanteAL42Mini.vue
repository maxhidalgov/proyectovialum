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
import { computed, onMounted, reactive } from 'vue'

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
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

const texturas = reactive({ roble: null, nogal: null })
onMounted(() => {
  const cargar = (key, url) => {
    const img = new Image()
    img.src = url
    img.onload = () => { texturas[key] = img }
  }
  cargar('roble', new URL('@/assets/images/roble.png', import.meta.url).href)
  cargar('nogal', new URL('@/assets/images/nogal.png', import.meta.url).href)
})

const colorHexMap = {
  blanco: '#D8D8D8',
  negro: '#2C2C2C',
  bronce: '#CD7F32',
  'bronce oscuro': '#5C4033',
  champagne: '#F7E7CE',
  plata: '#C0C0C0',
  gris: '#808080',
  grafito: '#4A4A4A',
  nogal: '#8b5a2b',
  mate: '#c0beba',
  titanio: '#998F77',
}

const colorNombre = computed(() => {
  const c = props.colorMarco
  if (typeof c === 'string') return c.toLowerCase()
  if (c?.nombre) return String(c.nombre).toLowerCase()
  return 'blanco'
})

const colorMarcoHex = computed(() => colorHexMap[colorNombre.value] || '#D8D8D8')

function getMitra(points) {
  const nombre = colorNombre.value
  if (['roble', 'nogal'].includes(nombre) && texturas[nombre]) {
    return {
      points, closed: true,
      fill: null,
      fillPatternImage: texturas[nombre],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: 0.2, y: 0.2 },
      stroke: 'black', strokeWidth: 0.5,
    }
  }
  return { points, closed: true, fill: colorMarcoHex.value, fillPatternImage: null, stroke: 'black', strokeWidth: 0.5 }
}

const vidrioGeneral = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
  strokeWidth: 0.5,
}))

const topMitra = computed(() => getMitra([
  offset, offset,
  offset + screenWidth.value, offset,
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + marcoAncho.value,
]))
const rightMitra = computed(() => getMitra([
  offset + screenWidth.value, offset,
  offset + screenWidth.value, offset + screenHeight.value,
  offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
]))
const bottomMitra = computed(() => getMitra([
  offset + screenWidth.value, offset + screenHeight.value,
  offset, offset + screenHeight.value,
  offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
]))
const leftMitra = computed(() => getMitra([
  offset, offset + screenHeight.value,
  offset, offset,
  offset + marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
]))

const hojaX = computed(() => offset + marcoAncho.value + hojaSeparacion * props.escala)
const hojaY = computed(() => offset + marcoAncho.value + hojaSeparacion * props.escala)
const hojaW = computed(() => screenWidth.value - marcoAncho.value * 2 - hojaSeparacion * 2 * props.escala)
const hojaH = computed(() => screenHeight.value - marcoAncho.value * 2 - hojaSeparacion * 2 * props.escala)

const hojaTopMitra = computed(() => getMitra([
  hojaX.value, hojaY.value,
  hojaX.value + hojaW.value, hojaY.value,
  hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
  hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
]))
const hojaRightMitra = computed(() => getMitra([
  hojaX.value + hojaW.value, hojaY.value,
  hojaX.value + hojaW.value, hojaY.value + hojaH.value,
  hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
]))
const hojaBottomMitra = computed(() => getMitra([
  hojaX.value + hojaW.value, hojaY.value + hojaH.value,
  hojaX.value, hojaY.value + hojaH.value,
  hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
]))
const hojaLeftMitra = computed(() => getMitra([
  hojaX.value, hojaY.value + hojaH.value,
  hojaX.value, hojaY.value,
  hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
  hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
]))

const lineaApertura1 = computed(() => ({
  points: [
    hojaX.value + hojaAncho.value + 3, hojaY.value + hojaAncho.value + 3,
    hojaX.value + hojaW.value - hojaAncho.value - 3, hojaY.value + hojaH.value / 2,
  ],
  stroke: 'black', strokeWidth: 1, dash: [4, 2],
}))
const lineaApertura2 = computed(() => ({
  points: [
    hojaX.value + hojaAncho.value + 3, hojaY.value + hojaH.value - hojaAncho.value - 3,
    hojaX.value + hojaW.value - hojaAncho.value - 3, hojaY.value + hojaH.value / 2,
  ],
  stroke: 'black', strokeWidth: 1, dash: [4, 2],
}))
</script>
