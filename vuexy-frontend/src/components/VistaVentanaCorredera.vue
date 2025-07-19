<template>
  <v-stage :config="stageConfig">
    <v-layer>
      <!-- Marco con mitras -->
      <v-line v-bind="topMitra" />
      <v-line v-bind="rightMitra" />
      <v-line v-bind="bottomMitra" />
      <v-line v-bind="leftMitra" />

      <!-- Hojas: orden dinámico -->
      <template v-if="props.ordenHoja1AlFrente">
        <v-rect v-bind="vidrio2" />
        <v-rect v-bind="vidrio1" />
        <v-text v-bind="indicador2" />
        <v-text v-bind="indicador1" />
      </template>
      <template v-else>
        <v-rect v-bind="vidrio1" />
        <v-rect v-bind="vidrio2" />
        <v-text v-bind="indicador1" />
        <v-text v-bind="indicador2" />
      </template>

      <!-- Mitras hoja 1 -->
      <v-line
        v-for="(mitra, i) in hoja1Mitras"
        :key="'mitra-h1-' + i"
        :points="mitra.points"
        closed
        :fill="colorMarcoHex"
        stroke="black"
      />
      <!-- Mitras hoja 2 -->
      <v-line
        v-for="(mitra, i) in hoja2Mitras"
        :key="'mitra-h2-' + i"
        :points="mitra.points"
        closed
        :fill="colorMarcoHex"
        stroke="black"
      />
      <!-- Números dentro de las hojas -->
      <v-text
        :x="hoja1X + hojaAncho / 2 - 10"
        :y="hoja1Y + hojaAlto / 2 - 20"
        text="1"
        fontSize="30"
        fontStyle="bold"
        fill="white"
        v-if="hoja1Adelante"
      />

      <v-text
        :x="hoja2X + hojaAncho / 2 - 10"
        :y="hoja2Y + hojaAlto / 2 - 20"
        text="2"
        fontSize="30"
        fontStyle="bold"
        fill="black"
        v-if="hoja1Adelante"
      />

      <v-text
        :x="hoja1X + hojaAncho / 2 - 10"
        :y="hoja1Y + hojaAlto / 2 - 20"
        text="2"
        fontSize="30"
        fontStyle="bold"
        fill="black"
        v-if="!hoja1Adelante"
      />

      <v-text
        :x="hoja2X + hojaAncho / 2 - 10"
        :y="hoja2Y + hojaAlto / 2 - 20"
        text="1"
        fontSize="30"
        fontStyle="bold"
        fill="white"
        v-if="!hoja1Adelante"
      />


      <!-- Etiquetas -->
      <v-text v-bind="widthLabel" />
      <v-text v-bind="heightLabel" />
    </v-layer>
  </v-stage>
</template>


<script setup>
import { computed } from 'vue'
import { watchEffect } from 'vue'


const props = defineProps({
  ancho: Number,
  alto: Number,
  colorMarco: { type: String, default: 'blanco' },
  hojasMoviles: { type: Number, default: '2' },     // cuántas hojas se mueven
  ordenHoja1AlFrente: Boolean, // si true, hoja1 adelante, sino hoja2 adelante
})

const offset = 40
const marcoAnchoOriginal = 54
const hojaMarcoAnchoOriginal = 80
const traslape = 20 // cuánto traslapan las hojas

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#000000',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
}

const colorMarcoHex = computed(() => {
  const c = props.colorMarco?.toLowerCase?.()
  return colorHexMap[c] || '#ffffff'
})

const maxCanvasSize = 300
const escala = computed(() => {
  const escalaAncho = maxCanvasSize / props.ancho
  const escalaAlto = maxCanvasSize / props.alto
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9
})

const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const hojaMarcoAncho = computed(() => hojaMarcoAnchoOriginal * escala.value)

const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

const stageConfig = {
  width: 400,
  height: 400,
}

const ajusteVidrio = computed(() => {
  return hojaMarcoAnchoOriginal * 0.1 * escala.value
})

const traslapeEscalado = computed(() => traslape * escala.value)


// === MARCO MITRAS ===
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

// === VIDRIOS (HOJAS)
const hojaAncho = computed(() => (screenWidth.value - marcoAncho.value * 2 + traslape) / 2)
const hojaAlto = computed(() => screenHeight.value - marcoAncho.value * 2)

// H1
const hoja1X = computed(() => offset + marcoAncho.value)
const hoja1Y = computed(() => offset + marcoAncho.value)

// H2 (traslapada a izquierda)
const hoja2X = computed(() => {
  return offset + screenWidth.value - marcoAncho.value - hojaAncho.value + traslapeEscalado.value - hojaMarcoAncho.value * 0.22
})

const hoja2Y = hoja1Y

// === MITRAS HOJAS
const hoja1Mitras = computed(() => [
  {
    points: [
      hoja1X.value, hoja1Y.value,
      hoja1X.value + hojaAncho.value, hoja1Y.value,
      hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
      hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
    ],
  },
  {
    points: [
      hoja1X.value + hojaAncho.value, hoja1Y.value,
      hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
      hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
      hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
    ],
  },
  {
    points: [
      hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
      hoja1X.value, hoja1Y.value + hojaAlto.value,
      hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
      hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
    ],
  },
  {
    points: [
      hoja1X.value, hoja1Y.value + hojaAlto.value,
      hoja1X.value, hoja1Y.value,
      hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
      hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
    ],
  },
])

const hoja2Mitras = computed(() => [
  // Top
  {
    points: [
      hoja2X.value, hoja2Y.value,
      hoja2X.value + hojaAncho.value, hoja2Y.value,
      hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
     // hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
    ],
  },
  // Right
  {
    points: [
      hoja2X.value + hojaAncho.value, hoja2Y.value,
      hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
      hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
      hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
    ],
  },
  // Bottom
  {
    points: [
      hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
      hoja2X.value, hoja2Y.value + hojaAlto.value,
     // hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
      //hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
    ],
  },
  // OMITIMOS LEFT (lado traslapado)
])

const vidrio1 = computed(() => ({
  x: hoja1X.value + hojaMarcoAncho.value,
  y: hoja1Y.value + hojaMarcoAncho.value,
  width: hojaAncho.value - hojaMarcoAncho.value * 2,
  height: hojaAlto.value - hojaMarcoAncho.value * 2,
  fill: 'lightblue',
  stroke: 'black',
}))

const vidrio2 = computed(() => {
  const ajusteX = hojaMarcoAncho.value * 0.4 + escala.value * 10
  const ajusteWidth = hojaMarcoAncho.value * 1.4 + escala.value * 10

  return {
    x: hoja2X.value + ajusteX+15,
    y: hoja2Y.value + hojaMarcoAncho.value,
    width: hojaAncho.value - ajusteWidth-15,
    height: hojaAlto.value - hojaMarcoAncho.value * 2,
    fill: 'lightblue',
    stroke: 'black',
  }
})

// watchEffect(() => {
//   console.log('VIDRIO 2 x:', vidrio2.value.x.toFixed(2))
//   console.log('HOJA 2 X:', hoja2X.value.toFixed(2))
//   console.log('Marco hoja ancho:', hojaMarcoAncho.value.toFixed(2))
//   console.log('Ancho hoja:', hojaAncho.value.toFixed(2))
//   console.log('Vidrio2 width:', vidrio2.value.width.toFixed(2))
//   console.log('Escala:', escala.value.toFixed(2))

// })

const hojasMoviles = toRef(props, 'hojasMoviles')

const textoIndicador1 = computed(() => hojasMoviles.value >= 1 ? '→' : '+')
const textoIndicador2 = computed(() => hojasMoviles.value >= 2 ? '→' : '+')

const indicador1 = computed(() => ({
  x: hoja1X.value + hojaAncho.value / 2,
  y: hoja1Y.value + hojaAlto.value / 2,
  text: textoIndicador1.value,
  fontSize: 24,
  fill: 'black',
}))

const indicador2 = computed(() => ({
  x: hoja2X.value + hojaAncho.value / 2,
  y: hoja2Y.value + hojaAlto.value / 2,
  text: textoIndicador2.value,
  fontSize: 24,
  fill: 'black',
}))


// === ETIQUETAS
const fontSize = computed(() => {
  const base = Math.max(props.ancho, props.alto)
  return Math.min(Math.max((base / 100), 20), 40)
})

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
