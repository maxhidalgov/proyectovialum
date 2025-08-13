<template>
  <v-group :x="props.config?.x || 0" :y="props.config?.y || 0">
    <!-- Marco exterior con mitras (inferior opcional) -->
    <v-line :config="topMitra" />
    <v-line :config="rightMitra" />
    <v-line v-if="bottomMitra" :config="bottomMitra" />
    <v-line :config="leftMitra" />

    <!-- Vidrio (ajusta altura si pasoLibre) -->
    <v-rect :config="glassConfig" />

    <!-- Mitras de hoja interior -->
    <v-line :config="hojaTopMitra" />
    <v-line :config="hojaRightMitra" />
    <v-line :config="hojaBottomMitra" />
    <v-line :config="hojaLeftMitra" />

    <!-- Manilla -->
    <Manilla
      :x="manillaX"
      :y="manillaY"
      :width="6"
      :height="20"
      :rotation="0"
      :offsetX="3"
      :offsetY="10"
    />

    <!-- Líneas de apertura (mismo cálculo que Abatir mini) -->
    <v-line :config="lineaApertura1" />
    <v-line :config="lineaApertura2" />

    <!-- Etiquetas (como Abatir mini) -->
    <v-text :config="widthLabel" />
    <v-text :config="heightLabel" />
    <v-text :config="direccionLabel" />
  </v-group>
</template>

<script setup>
import { computed } from 'vue'
import Manilla from '@/components/Manilla.vue'

const props = defineProps({
  ancho: Number,
  alto: Number,
  escala: { type: Number, default: 1 },
  colorMarco: [String, Object],
  config: { type: Object, default: () => ({ x: 0, y: 0 }) },
  ladoApertura: { type: String, default: 'izquierda' },
  direccionApertura: { type: String, default: 'interior' },
  pasoLibre: { type: Boolean, default: false }
})

// Colores/texturas igual que Abatir mini
const colorHexMap = { blanco: '#ffffff', negro: '#0a0a0a', gris: '#808080', grafito: '#2f2f2f', nogal: '#8b5a2b' }
const texturas = { roble: new Image(), nogal: new Image() }
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
      points, closed: true,
      fillPatternImage: texturas[nombre],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: usoHoja ? 0.15 : 0.2, y: usoHoja ? 0.15 : 0.2 },
      stroke: 'black'
    }
  }
  return { points, closed: true, fill: colorMarcoHex.value, stroke: 'black' }
}

// Medidas y escala (como Abatir mini)
const offset = 0
const marcoAnchoOriginal = 60
const hojaSeparacion = 0

const marcoAncho = computed(() => marcoAnchoOriginal * props.escala)
const screenWidth = computed(() => props.ancho * props.escala)
const screenHeight = computed(() => props.alto * props.escala)

// Marco exterior (igual que Abatir; inferior opcional)
const topMitra = computed(() =>
  getMitraMarco([ offset, offset, offset + screenWidth.value, offset,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + marcoAncho.value ])
)
const rightMitra = computed(() =>
  getMitraMarco([ offset + screenWidth.value, offset,
    offset + screenWidth.value, offset + screenHeight.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value),
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value ])
)
const bottomMitra = computed(() => {
  if (props.pasoLibre) return null
  return getMitraMarco([ offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value ])
})
const leftMitra = computed(() =>
  getMitraMarco([ offset, offset + screenHeight.value, offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value) ])
)

// Vidrio
const glassConfig = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value - (props.pasoLibre ? 0 : marcoAncho.value),
  fill: '#b3e5fc',
  stroke: 'black'
}))

// Marco hoja interior (mismas fórmulas que Abatir)
const hojaX = computed(() => glassConfig.value.x + hojaSeparacion * props.escala)
const hojaY = computed(() => glassConfig.value.y + hojaSeparacion * props.escala)
const hojaW = computed(() => glassConfig.value.width - hojaSeparacion * 2 * props.escala)
const hojaH = computed(() => glassConfig.value.height - hojaSeparacion * 2 * props.escala)
const hojaAncho = computed(() => marcoAncho.value * 0.8)

const hojaTopMitra = computed(() =>
  getMitraMarco([ hojaX.value, hojaY.value, hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value ], true)
)
const hojaRightMitra = computed(() =>
  getMitraMarco([ hojaX.value + hojaW.value, hojaY.value, hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value ], true)
)
const hojaBottomMitra = computed(() =>
  getMitraMarco([ hojaX.value + hojaW.value, hojaY.value + hojaH.value, hojaX.value, hojaY.value + hojaH.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value ], true)
)
const hojaLeftMitra = computed(() =>
  getMitraMarco([ hojaX.value, hojaY.value + hojaH.value, hojaX.value, hojaY.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value ], true)
)

// Manilla (idéntica a Abatir)
const manillaX = computed(() => (props.ladoApertura === 'izquierda' ? hojaX.value + hojaW.value - 15 : hojaX.value + 15))
const manillaY = computed(() => hojaY.value + hojaH.value / 2)

// Offset hacia afuera si es exterior (idéntico a Abatir)
const aperturaOutOffset = computed(() => {
  if (props.direccionApertura === 'interior') return 0
  return Math.min(Math.max(hojaW.value * 0.1, 8 * props.escala), 25 * props.escala)
})

// Líneas de apertura (mismas que Abatir mini)
const lineaApertura1 = computed(() => {
  if (props.ladoApertura === 'izquierda') {
    const startX = hojaX.value + 5 - aperturaOutOffset.value
    return { points: [startX, hojaY.value + 5, hojaX.value + hojaW.value - 5, hojaY.value + hojaH.value / 2],
      stroke: 'black', dash: [10, 5], strokeWidth: 2 }
  } else {
    const startX = hojaX.value + hojaW.value - 5 + aperturaOutOffset.value
    return { points: [startX, hojaY.value + 5, hojaX.value + 5, hojaY.value + hojaH.value / 2],
      stroke: 'black', dash: [10, 5], strokeWidth: 2 }
  }
})
const lineaApertura2 = computed(() => {
  if (props.ladoApertura === 'izquierda') {
    const startX = hojaX.value + 5 - aperturaOutOffset.value
    return { points: [startX, hojaY.value + hojaH.value - 5, hojaX.value + hojaW.value - 5, hojaY.value + hojaH.value / 2],
      stroke: 'black', dash: [10, 5], strokeWidth: 2 }
  } else {
    const startX = hojaX.value + hojaW.value - 5 + aperturaOutOffset.value
    return { points: [startX, hojaY.value + hojaH.value - 5, hojaX.value + 5, hojaY.value + hojaH.value / 2],
      stroke: 'black', dash: [10, 5], strokeWidth: 2 }
  }
})

// Etiquetas (iguales que Abatir)
const fontSize = computed(() => Math.min(Math.max(props.ancho / 100, 20), 40))
const widthLabel = computed(() => ({ x: offset + screenWidth.value / 2 - 30, y: offset + screenHeight.value + 10,
  text: `${props.ancho}mm`, fontSize: fontSize.value, fill: 'black' }))
const heightLabel = computed(() => ({ x: offset - 25, y: offset + screenHeight.value / 2, text: `${props.alto}mm`,
  fontSize: fontSize.value, fill: 'black', rotation: -90, offsetX: fontSize.value / 2 }))
const direccionLabel = computed(() => ({ x: widthLabel.value.x, y: widthLabel.value.y + fontSize.value + 5,
  text: `Apertura ${props.direccionApertura.charAt(0).toUpperCase()}${props.direccionApertura.slice(1)}`,
  fontSize: Math.min(18, fontSize.value * 0.8), fill: 'black' }))
</script>