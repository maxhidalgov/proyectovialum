<template>
  <div>
    <v-stage ref="stageRef" :config="{ width: 420, height: 420 }">
      <v-layer>
        <!-- Marco exterior -->
        <v-line :config="topMitra" />
        <v-line :config="rightMitra" />
        <v-line :config="leftMitra" />
        <v-line v-if="bottomMitra" :config="bottomMitra" />

        <!-- Luz -->
        <v-rect :config="glassConfig" />

        <!-- Hoja izquierda -->
        <v-line :config="lTop" />
        <v-line :config="lRight" />
        <v-line :config="lBottom" />
        <v-line :config="lLeft" />
        <!-- Hoja derecha -->
        <v-line :config="rTop" />
        <v-line :config="rRight" />
        <v-line :config="rBottom" />
        <v-line :config="rLeft" />

        <!-- Manilla en la hoja activa -->
        <Manilla
          :x="manillaX"
          :y="manillaY"
          :width="6"
          :height="20"
          :rotation="0"
          :offsetX="3"
          :offsetY="10"
        />

        <!-- Triángulos de apertura en ambas hojas -->
        <v-line
          v-for="(l,i) in aperturaTrianguloLeft"
          :key="'apertura-left-'+i"
          :config="l"
        />
        <v-line
          v-for="(l,i) in aperturaTrianguloRight"
          :key="'apertura-right-'+i"
          :config="l"
        />

        <!-- Bisagras (interior) -->
        <v-rect v-for="(h,i) in hingesLeft" :key="'hl-'+i" :config="h" />
        <v-rect v-for="(h,i) in hingesRight" :key="'hr-'+i" :config="h" />

        <!-- Etiquetas -->
        <v-text :config="widthLabel" />
        <v-text :config="heightLabel" />
        <v-text :config="direccionLabel" />
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
  // derecha|izquierda: hoja que tiene manilla y triángulo
  hojaActiva: { type: String, default: 'derecha', validator: v => ['izquierda','derecha'].includes(v) },
  // interior|exterior: si es interior se ven bisagras
  direccionApertura: { type: String, default: 'interior', validator: v => ['interior','exterior'].includes(v) },
  // true = sin perfil inferior
  pasoLibre: { type: Boolean, default: false }
})

/* Colores / texturas */
const colorHexMap = { blanco:'#ffffff', negro:'#0a0a0a', gris:'#808080', grafito:'#2f2f2f', nogal:'#8b5a2b' }
const texturas = { roble:new Image(), nogal:new Image() }
texturas.roble.src = new URL('@/assets/images/roble.png', import.meta.url).href
texturas.nogal.src = new URL('@/assets/images/nogal.png', import.meta.url).href
const colorMarcoHex = computed(() => {
  const nombre = typeof props.colorMarco === 'object' ? props.colorMarco?.nombre?.toLowerCase() : props.colorMarco?.toLowerCase()
  return colorHexMap[nombre] || '#ffffff'
})
function getMitraMarco(points, usoHoja = false) {
  const nombre = typeof props.colorMarco === 'object' ? props.colorMarco?.nombre?.toLowerCase() : props.colorMarco?.toLowerCase()
  if (['roble','nogal'].includes(nombre) && texturas[nombre]) {
    return { points, closed:true, fillPatternImage:texturas[nombre], fillPatternRepeat:'repeat', fillPatternScale:{ x: usoHoja ? 0.15 : 0.2, y: usoHoja ? 0.15 : 0.2 }, stroke:'black' }
  }
  return { points, closed:true, fill: colorMarcoHex.value, stroke:'black' }
}

/* Escala y medidas */
const offset = 50
const marcoAnchoOriginal = 60
// ✅ ancho de perfil de hoja en mm (más grande que el marco)
const hojaAnchoOriginal = 80
const maxCanvasSize = 320
const escala = computed(() => Math.min(maxCanvasSize/props.ancho, maxCanvasSize/props.alto, 1) * 0.9)
const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

/* Marco exterior */
const topMitra = computed(() => getMitraMarco([
  offset, offset,
  offset + screenWidth.value, offset,
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + marcoAncho.value
]))
const rightMitra = computed(() => getMitraMarco([
  offset + screenWidth.value, offset,
  offset + screenWidth.value, offset + screenHeight.value,
  offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value),
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value
]))
const leftMitra = computed(() => getMitraMarco([
  offset, offset + screenHeight.value,
  offset, offset,
  offset + marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + screenHeight.value - (props.pasoLibre ? 0 : marcoAncho.value)
]))
const bottomMitra = computed(() => {
  if (props.pasoLibre) return null
  return getMitraMarco([
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value
  ])
})

/* Luz total */
const glassConfig = computed(() => {
  const w = Math.max(screenWidth.value - 2 * marcoAncho.value, 5)
  const h = Math.max(screenHeight.value - marcoAncho.value - (props.pasoLibre ? 0 : marcoAncho.value), 5)
  return {
    x: offset + marcoAncho.value,
    y: offset + marcoAncho.value,
    width: w,
    height: h,
    fill: '#b3e5fc',
    stroke: 'black'
  }
})

/* Dos hojas: bounding boxes */
const meetingGap = computed(() => Math.max(6 * escala.value, marcoAncho.value * 0.15)) // separación central

const leftBox = computed(() => {
  const w = (glassConfig.value.width - meetingGap.value) / 2
  return { x: glassConfig.value.x, y: glassConfig.value.y, w, h: glassConfig.value.height }
})
const rightBox = computed(() => {
  const w = (glassConfig.value.width - meetingGap.value) / 2
  const x = glassConfig.value.x + w + meetingGap.value
  return { x, y: glassConfig.value.y, w, h: glassConfig.value.height }
})

// ✅ hoja más ancha que el marco, con límites para no romper mitras
const hojaAncho = computed(() => {
  const basePx = hojaAnchoOriginal * escala.value           // 80 mm → px
  const minPx = marcoAncho.value * 1.15                     // siempre > marco
  const maxPx = Math.min(leftBox.value.w, rightBox.value.w) * 0.45 // evitar invertir polígonos
  return Math.min(Math.max(basePx, minPx), maxPx)
})

/* Mitras hoja izquierda */
const lTop = computed(() => getMitraMarco([
  leftBox.value.x, leftBox.value.y,
  leftBox.value.x + leftBox.value.w, leftBox.value.y,
  leftBox.value.x + leftBox.value.w - hojaAncho.value, leftBox.value.y + hojaAncho.value,
  leftBox.value.x + hojaAncho.value, leftBox.value.y + hojaAncho.value
], true))
const lRight = computed(() => getMitraMarco([
  leftBox.value.x + leftBox.value.w, leftBox.value.y,
  leftBox.value.x + leftBox.value.w, leftBox.value.y + leftBox.value.h,
  leftBox.value.x + leftBox.value.w - hojaAncho.value, leftBox.value.y + leftBox.value.h - hojaAncho.value,
  leftBox.value.x + leftBox.value.w - hojaAncho.value, leftBox.value.y + hojaAncho.value
], true))
const lBottom = computed(() => getMitraMarco([
  leftBox.value.x + leftBox.value.w, leftBox.value.y + leftBox.value.h,
  leftBox.value.x, leftBox.value.y + leftBox.value.h,
  leftBox.value.x + hojaAncho.value, leftBox.value.y + leftBox.value.h - hojaAncho.value,
  leftBox.value.x + leftBox.value.w - hojaAncho.value, leftBox.value.y + leftBox.value.h - hojaAncho.value
], true))
const lLeft = computed(() => getMitraMarco([
  leftBox.value.x, leftBox.value.y + leftBox.value.h,
  leftBox.value.x, leftBox.value.y,
  leftBox.value.x + hojaAncho.value, leftBox.value.y + hojaAncho.value,
  leftBox.value.x + hojaAncho.value, leftBox.value.y + leftBox.value.h - hojaAncho.value
], true))

/* Mitras hoja derecha */
const rTop = computed(() => getMitraMarco([
  rightBox.value.x, rightBox.value.y,
  rightBox.value.x + rightBox.value.w, rightBox.value.y,
  rightBox.value.x + rightBox.value.w - hojaAncho.value, rightBox.value.y + hojaAncho.value,
  rightBox.value.x + hojaAncho.value, rightBox.value.y + hojaAncho.value
], true))
const rRight = computed(() => getMitraMarco([
  rightBox.value.x + rightBox.value.w, rightBox.value.y,
  rightBox.value.x + rightBox.value.w, rightBox.value.y + rightBox.value.h,
  rightBox.value.x + rightBox.value.w - hojaAncho.value, rightBox.value.y + rightBox.value.h - hojaAncho.value,
  rightBox.value.x + rightBox.value.w - hojaAncho.value, rightBox.value.y + hojaAncho.value
], true))
const rBottom = computed(() => getMitraMarco([
  rightBox.value.x + rightBox.value.w, rightBox.value.y + rightBox.value.h,
  rightBox.value.x, rightBox.value.y + rightBox.value.h,
  rightBox.value.x + hojaAncho.value, rightBox.value.y + rightBox.value.h - hojaAncho.value,
  rightBox.value.x + rightBox.value.w - hojaAncho.value, rightBox.value.y + rightBox.value.h - hojaAncho.value
], true))
const rLeft = computed(() => getMitraMarco([
  rightBox.value.x, rightBox.value.y + rightBox.value.h,
  rightBox.value.x, rightBox.value.y,
  rightBox.value.x + hojaAncho.value, rightBox.value.y + hojaAncho.value,
  rightBox.value.x + hojaAncho.value, rightBox.value.y + rightBox.value.h - hojaAncho.value
], true))

/* Manilla en hoja activa (en el encuentro) */
const manillaX = computed(() => {
  return props.hojaActiva === 'izquierda'
    ? leftBox.value.x + leftBox.value.w - 12
    : rightBox.value.x + 12
})
const manillaY = computed(() => glassConfig.value.y + glassConfig.value.height / 2)

/* Apertura: triángulos en ambas hojas */
function aperturaOutOffsetFor(box) {
  if (props.direccionApertura === 'interior') return 0
  // desplazamiento hacia afuera (8–25 px escalados o 10% del ancho de hoja)
  return Math.min(Math.max(box.w * 0.1, 8 * escala.value), 25 * escala.value)
}

function buildAperturaTriangulo(box, side) {
  const left = side === 'left'
  const outward = aperturaOutOffsetFor(box) * (left ? -1 : 1)
  const pivotX = left ? box.x : box.x + box.w
  const px = pivotX + outward
  const centerY = box.y + box.h / 2
  const topY = box.y + 8
  const botY = box.y + box.h - 8
  const leafEdgeX = left ? box.x + box.w - 8 : box.x + 8

  return [
    { points: [px, topY, leafEdgeX, centerY], stroke: 'black', dash: [10, 5], strokeWidth: 2 },
    { points: [px, botY, leafEdgeX, centerY], stroke: 'black', dash: [10, 5], strokeWidth: 2 },
  ]
}

const aperturaTrianguloLeft = computed(() => buildAperturaTriangulo(leftBox.value, 'left'))
const aperturaTrianguloRight = computed(() => buildAperturaTriangulo(rightBox.value, 'right'))

/* Bisagras (solo interior), en ambas hojas. Mínimo 3. */
const hingesVisible = computed(() => props.direccionApertura === 'interior')
const hingeWidth = computed(() => Math.max(28 * escala.value, marcoAncho.value * 0.45))
const hingeHeightBase = computed(() => Math.min(Math.max(140 * escala.value, 30 * escala.value), Math.min(leftBox.value.h, rightBox.value.h) * 0.4))
const hingeInset = computed(() => 2 * escala.value)

function buildHinges(box, side) {
  const arr = []
  let count = box.h > 2200 * escala.value ? 4 : 3
  const usable = Math.max(box.h - 16 * escala.value, 20)
  let hUsed = hingeHeightBase.value
  if (hUsed * count > usable * 0.85) hUsed = (usable * 0.85) / count
  const gap = (usable - hUsed * count) / (count + 1)
  for (let i = 0; i < count; i++) {
    const y = box.y + 8 * escala.value + gap * (i + 1) + hUsed * i
    const x = side === 'left'
      ? box.x - hingeWidth.value / 2 + hingeInset.value
      : box.x + box.w - hingeWidth.value / 2 - hingeInset.value
    arr.push({ x, y, width: hingeWidth.value, height: hUsed, fill:'#888', stroke:'black', cornerRadius:2 })
  }
  return arr
}

const hingesLeft = computed(() => hingesVisible.value ? buildHinges(leftBox.value, 'left') : [])
const hingesRight = computed(() => hingesVisible.value ? buildHinges(rightBox.value, 'right') : [])

/* Etiquetas */
const fontSize = computed(() => Math.min(Math.max(props.ancho / 100, 20), 40))
const widthLabel = computed(() => ({ x: offset + screenWidth.value / 2 - 30, y: offset + screenHeight.value + 10, text: `${props.ancho}mm`, fontSize: fontSize.value, fill:'black' }))
const heightLabel = computed(() => ({ x: offset - 25, y: offset + screenHeight.value / 2, text: `${props.alto}mm`, fontSize: fontSize.value, fill:'black', rotation:-90, offsetX: fontSize.value / 2 }))
const direccionLabel = computed(() => ({
  x: widthLabel.value.x,
  y: widthLabel.value.y + fontSize.value + 5,
  text: `Apertura ${props.direccionApertura.charAt(0).toUpperCase()}${props.direccionApertura.slice(1)}`,
  fontSize: Math.min(18, fontSize.value * 0.8),
  fill: 'black'
}))

/* Export */
const stageRef = ref(null)
const exportarImagen = () => stageRef.value ? stageRef.value.getStage().toDataURL({ pixelRatio:1, quality:0.9 }) : null
defineExpose({ exportarImagen })
</script>