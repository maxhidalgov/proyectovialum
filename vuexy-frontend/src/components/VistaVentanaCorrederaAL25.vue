<template>
  <div>
    <div style="margin-bottom: 10px;">
      <v-switch
        v-model="hoja1Adelante"
        label="Hoja 1 adelante"
        density="compact"
      />
    </div>
    
    <v-stage ref="stageRef" :config="{ width: 400, height: 400 }">
      <v-layer>
        <!-- Marco principal (rectángulos sobrepuestos, sin mitras) -->
        <v-rect v-bind="marcoTop" />
        <v-rect v-bind="marcoRight" />
        <v-rect v-bind="marcoBottom" />
        <v-rect v-bind="marcoLeft" />

        <!-- Hojas según orden -->
        <template v-if="hoja1Adelante">
          <!-- Hoja 2 detrás -->
          <v-rect v-bind="vidrio2" />
          <v-rect v-bind="hoja2Top" />
          <v-rect v-bind="hoja2Right" />
          <v-rect v-bind="hoja2Bottom" />
          <v-rect v-bind="hoja2Left" />
          
          <v-text
            :x="hoja2X + hojaAncho / 2 - 10"
            :y="hoja2Y + hojaAlto / 2 - 20"
            text="2"
            fontSize="30"
            fontStyle="bold"
            fill="black"
          />

          <!-- Hoja 1 adelante -->
          <v-rect v-bind="vidrio1" />
          <v-rect v-bind="hoja1Top" />
          <v-rect v-bind="hoja1Right" />
          <v-rect v-bind="hoja1Bottom" />
          <v-rect v-bind="hoja1Left" />
          
          <v-text
            :x="hoja1X + hojaAncho / 2 - 10"
            :y="hoja1Y + hojaAlto / 2 - 20"
            text="1"
            fontSize="30"
            fontStyle="bold"
            fill="black"
          />
        </template>

        <template v-else>
          <!-- Hoja 1 detrás -->
          <v-rect v-bind="vidrio1" />
          <v-rect v-bind="hoja1Top" />
          <v-rect v-bind="hoja1Right" />
          <v-rect v-bind="hoja1Bottom" />
          <v-rect v-bind="hoja1Left" />
          
          <v-text
            :x="hoja1X + hojaAncho / 2 - 10"
            :y="hoja1Y + hojaAlto / 2 - 20"
            text="2"
            fontSize="30"
            fontStyle="bold"
            fill="black"
          />

          <!-- Hoja 2 adelante -->
          <v-rect v-bind="vidrio2" />
          <v-rect v-bind="hoja2Top" />
          <v-rect v-bind="hoja2Right" />
          <v-rect v-bind="hoja2Bottom" />
          <v-rect v-bind="hoja2Left" />
          
          <v-text
            :x="hoja2X + hojaAncho / 2 - 10"
            :y="hoja2Y + hojaAlto / 2 - 20"
            text="1"
            fontSize="30"
            fontStyle="bold"
            fill="black"
          />
        </template>

        <!-- Etiquetas -->
        <v-text v-bind="widthLabel" />
        <v-text v-bind="heightLabel" />
        <v-text v-bind="indicador1" />
        <v-text v-bind="indicador2" />
        
        <!-- Indicadores de hojas móviles/fijas -->
        <v-text v-bind="indicadorHoja1" />
        <v-text v-bind="indicadorHoja2" />
        
        <!-- Manillas -->
        <Manilla
          v-if="mostrarManilla1"
          :x="hoja1X + hojaMarcoAncho - escala*35"  
          :y="hoja1Y + hojaAlto / 2"
          :escalaManilla="escala*4"
        />

        <Manilla
          v-if="mostrarManilla2"
          :x="hoja2X + hojaAncho - hojaMarcoAncho + escala*35"
          :y="hoja2Y + hojaAlto / 2"
          :escalaManilla="escala*4"
        />
      </v-layer>
    </v-stage>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import Manilla from '@/components/Manilla.vue'

const stageRef = ref(null)

const exportarImagen = () => {
  if (stageRef.value) {
    try {
      const stage = stageRef.value.getStage()
      stage.draw()
      return stage.toDataURL({ pixelRatio: 1, quality: 0.9 })
    } catch (error) {
      console.error('❌ Error en exportarImagen:', error)
      return null
    }
  }
  return null
}

defineExpose({ exportarImagen })

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
  hoja1AlFrente: { type: Boolean, default: true },
  hojasMoviles: { type: Number, default: 2 },
  hojaMovilSeleccionada: { type: Number, default: 1 }
})

const offset = 40
const marcoAnchoOriginal = 54
const hojaMarcoAnchoOriginal = 80
const traslape = 20

const hoja1Adelante = ref(props.hoja1AlFrente)

// Computadas para saber si cada hoja es móvil
const hoja1EsMovil = computed(() =>
  props.hojasMoviles === 2 || props.hojaMovilSeleccionada === 1
)
const hoja2EsMovil = computed(() =>
  props.hojasMoviles === 2 || props.hojaMovilSeleccionada === 2
)

const mostrarManilla1 = computed(() => hoja1EsMovil.value)
const mostrarManilla2 = computed(() => hoja2EsMovil.value)

watch(() => props.hoja1AlFrente, (val) => {
  hoja1Adelante.value = val
})

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
  bronce: '#CD7F32',
  'negro mate': '#1A1A1A',
  inox: '#C0C0C0',
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

const traslapeEscalado = computed(() => traslape * escala.value)

// === MARCO PRINCIPAL (rectángulos sobrepuestos) ===
const marcoTop = computed(() => ({
  x: offset,
  y: offset,
  width: screenWidth.value,
  height: marcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const marcoRight = computed(() => ({
  x: offset + screenWidth.value - marcoAncho.value,
  y: offset,
  width: marcoAncho.value,
  height: screenHeight.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const marcoBottom = computed(() => ({
  x: offset,
  y: offset + screenHeight.value - marcoAncho.value,
  width: screenWidth.value,
  height: marcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const marcoLeft = computed(() => ({
  x: offset,
  y: offset,
  width: marcoAncho.value,
  height: screenHeight.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

// === VIDRIOS (HOJAS)
const hojaAncho = computed(() => (screenWidth.value - marcoAncho.value * 2 + traslape) / 2)
const hojaAlto = computed(() => screenHeight.value - marcoAncho.value * 2)

const hoja1X = computed(() => offset + marcoAncho.value)
const hoja1Y = computed(() => offset + marcoAncho.value)

const hoja2X = computed(() => {
  return offset + screenWidth.value - marcoAncho.value - hojaAncho.value + traslapeEscalado.value - hojaMarcoAncho.value * 0.22
})
const hoja2Y = hoja1Y

// === MARCOS DE HOJAS (rectángulos sobrepuestos) ===
const hoja1Top = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value,
  width: hojaAncho.value,
  height: hojaMarcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja1Right = computed(() => ({
  x: hoja1X.value + hojaAncho.value - hojaMarcoAncho.value,
  y: hoja1Y.value,
  width: hojaMarcoAncho.value,
  height: hojaAlto.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja1Bottom = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
  width: hojaAncho.value,
  height: hojaMarcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja1Left = computed(() => ({
  x: hoja1X.value,
  y: hoja1Y.value,
  width: hojaMarcoAncho.value,
  height: hojaAlto.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja2Top = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value,
  width: hojaAncho.value,
  height: hojaMarcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja2Right = computed(() => ({
  x: hoja2X.value + hojaAncho.value - hojaMarcoAncho.value,
  y: hoja2Y.value,
  width: hojaMarcoAncho.value,
  height: hojaAlto.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja2Bottom = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
  width: hojaAncho.value,
  height: hojaMarcoAncho.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

const hoja2Left = computed(() => ({
  x: hoja2X.value,
  y: hoja2Y.value,
  width: hojaMarcoAncho.value,
  height: hojaAlto.value,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 1,
}))

// Eliminamos las mitras antiguas (ya no se usan)

const vidrio1 = computed(() => {
  if (hoja1Adelante.value) {
    return {
      x: hoja1X.value + hojaMarcoAncho.value,
      y: hoja1Y.value + hojaMarcoAncho.value,
      width: hojaAncho.value - hojaMarcoAncho.value * 2,
      height: hojaAlto.value - hojaMarcoAncho.value * 2,
      fill: 'lightblue',
      stroke: 'black',
    }
  }

  const ajusteX1 = hojaMarcoAncho.value * 0.35 + escala.value * 50
  const ajusteWidth1 = hojaMarcoAncho.value * 1.3 + escala.value * 1
  return {
    x: hoja1X.value + ajusteX1,
    y: hoja1Y.value + hojaMarcoAncho.value,
    width: hojaAncho.value - ajusteWidth1 - 15,
    height: hojaAlto.value - hojaMarcoAncho.value * 2,
    fill: 'lightblue',
    stroke: 'black',
  }
})

const vidrio2 = computed(() => {
  if (hoja1Adelante.value) {
    const ajusteX = hojaMarcoAncho.value * 0.4 + escala.value * 10
    const ajusteWidth = hojaMarcoAncho.value * 1.4 + escala.value * 10

    return {
      x: hoja2X.value + ajusteX + 10,
      y: hoja2Y.value + hojaMarcoAncho.value,
      width: hojaAncho.value - ajusteWidth - 10,
      height: hojaAlto.value - hojaMarcoAncho.value * 2,
      fill: 'lightblue',
      stroke: 'black',
    }
  }

  return {
    x: hoja2X.value + hojaMarcoAncho.value,
    y: hoja2Y.value + hojaMarcoAncho.value,
    width: hojaAncho.value - hojaMarcoAncho.value * 2,
    height: hojaAlto.value - hojaMarcoAncho.value * 2,
    fill: 'lightblue',
    stroke: 'black',
  }
})

// Indicadores de hojas móviles/fijas
const textoIndicadorHoja1 = computed(() => {
  if (props.hojasMoviles === 2) return '→'
  if (props.hojasMoviles === 1 && props.hojaMovilSeleccionada === 1) return '→'
  return '+'
})

const textoIndicadorHoja2 = computed(() => {
  if (props.hojasMoviles === 2) return '←'
  if (props.hojasMoviles === 1 && props.hojaMovilSeleccionada === 2) return '←'
  return '+'
})

const indicadorHoja1 = computed(() => ({
  x: hoja1X.value + hojaAncho.value / 2 - 10,
  y: hoja1Y.value + hojaAlto.value / 2 + 10,
  text: textoIndicadorHoja1.value,
  fontSize: 30,
  fill: 'black',
}))

const indicadorHoja2 = computed(() => ({
  x: hoja2X.value + hojaAncho.value / 2 - 10,
  y: hoja2Y.value + hojaAlto.value / 2 + 10,
  text: textoIndicadorHoja2.value,
  fontSize: 30,
  fill: 'black',
}))

const indicador1 = computed(() => ({
  x: offset - 30,
  y: offset + screenHeight.value / 4 - 10,
  text: 'AL25',
  fontSize: 12,
  fill: '#666',
  fontStyle: 'bold'
}))

const indicador2 = computed(() => ({
  x: offset - 30,
  y: offset + screenHeight.value / 4 + 5,
  text: 'Corredera',
  fontSize: 10,
  fill: '#999'
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
