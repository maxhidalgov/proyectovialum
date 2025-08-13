<template>
  <div>
    <v-stage ref="stageRef" :config="{ width: 400, height: 400 }">
      <v-layer>
        <v-line :config="topMitra" />
        <v-line :config="rightMitra" />
        <v-line :config="leftMitra" />
        <v-line v-if="bottomMitra" :config="bottomMitra" />

        <v-rect :config="glassConfig" />

        <v-line :config="hojaTopMitra" />
        <v-line :config="hojaRightMitra" />
        <v-line :config="hojaBottomMitra" />
        <v-line :config="hojaLeftMitra" />

        <Manilla
          :x="manillaX"
          :y="manillaY"
          :width="6"
          :height="20"
          :rotation="0"
          :offsetX="3"
          :offsetY="10"
        />

        <!-- (Arco removido) -->

        <v-line
          v-for="(l,i) in aperturaTriangulo"
          :key="'apertura-'+i"
          :config="l"
        />

        <v-rect
          v-for="(h,i) in hinges"
          :key="'hinge-'+i"
          :config="h"
        />

        <v-text :config="widthLabel" />
        <v-text :config="heightLabel" />
        <v-text :config="direccionLabel" /> <!-- Nueva etiqueta -->
      </v-layer>
    </v-stage>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Manilla from '@/components/Manilla.vue'

const props = defineProps({
  ancho: Number,
  alto: Number,
  colorMarco: [String, Object],
  ladoApertura: { type: String, default: 'izquierda', validator: v => ['izquierda','derecha'].includes(v) },
  direccionApertura: { type: String, default: 'interior', validator: v => ['interior','exterior'].includes(v) },
  pasoLibre: { type: Boolean, default: false }
})

// Estado reactivo
const ladoApertura = ref(props.ladoApertura)

// Colores y texturas
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
  const nombre = typeof props.colorMarco === 'object' ? props.colorMarco?.nombre?.toLowerCase() : props.colorMarco.toLowerCase()

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

// Escala y medidas (igual que proyectante)
const offset = 50
const marcoAnchoOriginal = 60
// ✅ ancho de perfil de hoja en mm (más grande que el marco)
const hojaAnchoOriginal = 80
const maxCanvasSize = 300

const escala = computed(() => {
  const ea = maxCanvasSize / props.ancho
  const eh = maxCanvasSize / props.alto
  return Math.min(ea, eh, 1) * 0.9
})
const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

/* ---------- Marco exterior (condicional en inferior) ------------- */
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
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value),
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  ])
)

const leftMitra = computed(() =>
  getMitraMarco([
    offset, offset + screenHeight.value,
    offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value),
  ])
)

const bottomMitra = computed(() => {
  if (props.pasoLibre) return null
  return getMitraMarco([
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ])
})

/* ---------- Vidrio y hoja (ajustan altura si paso libre) ---------- */
const glassConfig = computed(() => {
  // altura segura (evita negativa si la puerta es muy baja comparada al marco)
  const rawHeight = screenHeight.value - marcoAncho.value - (props.pasoLibre ? 0 : marcoAncho.value)
  const safeHeight = Math.max(rawHeight, 5)
  const safeWidth = Math.max(screenWidth.value - marcoAncho.value * 2, 5)

  return {
    x: offset + marcoAncho.value,
    y: offset + marcoAncho.value,
    width: safeWidth,
    height: safeHeight,
    fill: '#b3e5fc',
    stroke: 'black',
  }
})

const hojaX = computed(() => glassConfig.value.x)
const hojaY = computed(() => glassConfig.value.y)
const hojaW = computed(() => glassConfig.value.width)
const hojaH = computed(() => glassConfig.value.height)
// ❌ antes:
// const hojaAncho = computed(() => marcoAncho.value * 0.8)
// ✅ ahora: hoja más ancha que el marco, con límites
const hojaAncho = computed(() => {
  const basePx = hojaAnchoOriginal * escala.value      // 80 mm → px
  const minPx = marcoAncho.value * 1.15                // siempre > marco
  const maxPx = hojaW.value * 0.45                     // evita invertir mitras
  return Math.min(Math.max(basePx, minPx), maxPx)
})

/* Mitras internas hoja (igual, adaptan altura real) */
const hojaTopMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
  ], true)
)
const hojaRightMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value,
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaAncho.value,
  ], true)
)
const hojaBottomMitra = computed(() =>
  getMitraMarco([
    hojaX.value + hojaW.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
    hojaX.value + hojaW.value - hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ], true)
)
const hojaLeftMitra = computed(() =>
  getMitraMarco([
    hojaX.value, hojaY.value + hojaH.value,
    hojaX.value, hojaY.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaAncho.value,
    hojaX.value + hojaAncho.value, hojaY.value + hojaH.value - hojaAncho.value,
  ], true)
)

/* ---------------- Manilla ---------------- */
const manillaX = computed(() => {
  return props.ladoApertura === 'izquierda'
    ? hojaX.value + hojaW.value - 15
    : hojaX.value + 15
})
const manillaY = computed(() => hojaY.value + hojaH.value / 2)

/* ----------- Líneas de apertura (interior vs exterior) ------------ 
   Sustituimos lineaApertura1 / lineaApertura2 por aperturaTriangulo
*/
const aperturaOutOffset = computed(() => {
  if (props.direccionApertura === 'interior') return 0
  // desplazamiento hacia afuera limitado (10% del ancho hoja entre 8 y 25 px escalados)
  return Math.min(Math.max(hojaW.value * 0.1, 8 * escala.value), 25 * escala.value)
})

const aperturaTriangulo = computed(() => {
  const left = props.ladoApertura === 'izquierda'
  const outward = aperturaOutOffset.value * (left ? -1 : 1)
  const pivotX = left ? hojaX.value : hojaX.value + hojaW.value
  const pivotYTop = hojaY.value + 8
  const pivotYBottom = hojaY.value + hojaH.value - 8
  const centerY = hojaY.value + hojaH.value / 2
  const leafEdgeX = left ? hojaX.value + hojaW.value - 8 : hojaX.value + 8

  const px = pivotX + outward

  return [
    {
      points: [px, pivotYTop, leafEdgeX, centerY],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    },
    {
      points: [px, pivotYBottom, leafEdgeX, centerY],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    },
  ]
})

/* ---------------- Etiquetas (sin cambios) ---------------- */
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

// Nueva etiqueta dirección/apertura
const direccionLabel = computed(() => ({
  x: widthLabel.value.x,
  y: widthLabel.value.y + fontSize.value + 5,
  text: `Apertura ${props.direccionApertura.charAt(0).toUpperCase()}${props.direccionApertura.slice(1)}`,
  fontSize: Math.min(18, fontSize.value * 0.8),
  fill: 'black',
}))

/* ---------------- Exportar imagen ---------------- */
const stageRef = ref(null)
const exportarImagen = () => {
  if (stageRef.value)
    return stageRef.value.getStage().toDataURL({ pixelRatio: 1, quality: 0.9 })
  return null
}
defineExpose({ exportarImagen })

/* ----------- Parámetros visuales adicionales ------------ */
const hingeWidth = computed(() => Math.max(28 * escala.value, marcoAncho.value * 0.45))
const hingeHeight = computed(() => {
  // asegurar que quepa dentro de la hoja (mín 10, máx 40% de hoja)
  return Math.min(Math.max(140 * escala.value, 30 * escala.value), hojaH.value * 0.4)
})
const hingeInset = computed(() => 2 * escala.value)      // cuánto entra en la hoja
const hingesVisible = computed(() => props.direccionApertura === 'interior')

/* Bisagras (solo interior) */
const hinges = computed(() => {
  if (!hingesVisible.value) return []

  let count = hojaH.value > 2200 ? 4 : 3 // mínimo 3
  const usable = Math.max(hojaH.value - 16 * escala.value, 20)
  let hUsed = hingeHeight.value

  if (hUsed * count > usable * 0.85) {
    hUsed = (usable * 0.85) / count
  }

  const gap = (usable - hUsed * count) / (count + 1)
  const arr = []
  for (let i = 0; i < count; i++) {
    const y = hojaY.value + 8 * escala.value + gap * (i + 1) + hUsed * i
    const x = props.ladoApertura === 'izquierda'
      ? hojaX.value - hingeWidth.value / 2 + 2 * escala.value
      : hojaX.value + hojaW.value - hingeWidth.value / 2 - 2 * escala.value
    arr.push({
      x,
      y,
      width: hingeWidth.value,
      height: hUsed,
      fill: '#888',
      stroke: 'black',
      cornerRadius: 2,
    })
  }
  return arr
})

</script>