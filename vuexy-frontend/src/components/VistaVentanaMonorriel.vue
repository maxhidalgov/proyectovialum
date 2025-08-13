<template>
  <div>
    <v-stage ref="stageRef" :config="{ width: 400, height: 400 }">
      <v-layer>
        <!-- Marco -->
        <v-line v-bind="topMitra" />
        <v-line v-bind="rightMitra" />
        <v-line v-bind="bottomMitra" />
        <v-line v-bind="leftMitra" />

        <!-- Vidrio fijo a tope del marco (sin hoja) -->
        <v-rect v-bind="vidrioFijo" />

        <!-- Hoja móvil (única) -->
        <v-rect v-bind="vidrioMovil" />
        <v-line v-for="(m,i) in hojaMitras" :key="'hm-'+i" v-bind="m" />

        <!-- Manilla en hoja móvil -->
        <Manilla :x="manillaX" :y="manillaY" :escalaManilla="escala*4" />

        <!-- Flecha de dirección -->
        <v-text :x="arrowX" :y="arrowY" :text="arrowText" :fontSize="30" fontStyle="bold" fill="black" />

        <!-- Etiquetas -->
        <v-text v-bind="widthLabel" />
        <v-text v-bind="heightLabel" />
      </v-layer>
    </v-stage>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Manilla from '@/components/Manilla.vue'
import robleUrl from '@/assets/images/roble.png'
import nogalUrl from '@/assets/images/nogal.png'

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
  // 'derecha' = la hoja móvil se mueve hacia la derecha (está a la izquierda en cerrado)
  // 'izquierda' = la hoja móvil se mueve hacia la izquierda (está a la derecha en cerrado)
  ladoApertura: { type: String, default: 'derecha', validator: v => ['izquierda','derecha'].includes(v) },
})

/* Texturas y color */
const texturas = { roble: null, nogal: null }
onMounted(() => {
  const load = (url, key) => { const img = new Image(); img.src = url; img.onload = () => (texturas[key] = img) }
  load(robleUrl, 'roble'); load(nogalUrl, 'nogal')
})
const colorHexMap = { blanco:'#ffffff', negro:'#0a0a0a', gris:'#808080', grafito:'#2f2f2f', nogal:'#8b5a2b', roble:'#c9a36b' }
const colorMarcoHex = computed(() => colorHexMap[props.colorMarco?.toLowerCase?.()] || '#ffffff')
function getMitraProps(points) {
  const nombre = props.colorMarco?.toLowerCase?.()
  if (['roble','nogal'].includes(nombre) && texturas[nombre]) {
    return { points, closed:true, fillPatternImage:texturas[nombre], fillPatternRepeat:'repeat', fillPatternScale:{ x:0.2, y:0.2 }, stroke:'black' }
  }
  return { points, closed:true, fill: colorMarcoHex.value, stroke:'black' }
}

/* Geometría y escala */
const stageRef = ref(null)
const offset = 40
const maxCanvasSize = 300
const escala = computed(() => Math.min(maxCanvasSize/props.ancho, maxCanvasSize/props.alto, 1) * 0.9)

// Perfiles (mm -> px)
const marcoAnchoOriginal = 54     // marco
const hojaMarcoAnchoOriginal = 80 // hoja (más ancho que el marco)
const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const hojaMarcoAncho = computed(() => hojaMarcoAnchoOriginal * escala.value)

const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

const movEsIzq = computed(() => props.ladoApertura === 'derecha') // móvil a la izquierda en posición cerrada

/* Marco con mitras */
const topMitra = computed(() => getMitraProps([
  offset, offset,
  offset + screenWidth.value, offset,
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + marcoAncho.value,
]))
const rightMitra = computed(() => getMitraProps([
  offset + screenWidth.value, offset,
  offset + screenWidth.value, offset + screenHeight.value,
  offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
]))
const bottomMitra = computed(() => getMitraProps([
  offset + screenWidth.value, offset + screenHeight.value,
  offset, offset + screenHeight.value,
  offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
]))
const leftMitra = computed(() => getMitraProps([
  offset, offset + screenHeight.value,
  offset, offset,
  offset + marcoAncho.value, offset + marcoAncho.value,
  offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
]))

/* Luz interna */
const luzX = computed(() => offset + marcoAncho.value)
const luzY = computed(() => offset + marcoAncho.value)
const luzW = computed(() => Math.max(screenWidth.value - 2*marcoAncho.value, 5))
const luzH = computed(() => Math.max(screenHeight.value - 2*marcoAncho.value, 5))

/* División visual: mitad fija (vidrio a tope), mitad con hoja móvil */
const halfW = computed(() => Math.max(luzW.value / 2, 10))

// Cajas de fijo y móvil
const fijoBox = computed(() => {
  return movEsIzq.value
    ? { x: luzX.value + halfW.value, y: luzY.value, w: halfW.value, h: luzH.value } // móvil izquierda => fijo derecha
    : { x: luzX.value, y: luzY.value, w: halfW.value, h: luzH.value }               // móvil derecha => fijo izquierda
})
const movilBox = computed(() => {
  return movEsIzq.value
    ? { x: luzX.value, y: luzY.value, w: halfW.value, h: luzH.value }               // móvil izquierda
    : { x: luzX.value + halfW.value, y: luzY.value, w: halfW.value, h: luzH.value } // móvil derecha
})

// Vidrio fijo a tope (sin hoja)
const vidrioFijo = computed(() => ({
  x: fijoBox.value.x,
  y: fijoBox.value.y,
  width: fijoBox.value.w,
  height: fijoBox.value.h,
  fill: 'lightblue',
  stroke: 'black',
}))

// Hoja móvil: mitras + vidrio interior
function buildHojaMitras(x, y, w, h) {
  return [
    getMitraProps([ x, y, x + w, y, x + w - hojaMarcoAncho.value, y + hojaMarcoAncho.value, x + hojaMarcoAncho.value, y + hojaMarcoAncho.value ]),
    getMitraProps([ x + w, y, x + w, y + h, x + w - hojaMarcoAncho.value, y + h - hojaMarcoAncho.value, x + w - hojaMarcoAncho.value, y + hojaMarcoAncho.value ]),
    getMitraProps([ x + w, y + h, x, y + h, x + hojaMarcoAncho.value, y + h - hojaMarcoAncho.value, x + w - hojaMarcoAncho.value, y + h - hojaMarcoAncho.value ]),
    getMitraProps([ x, y + h, x, y, x + hojaMarcoAncho.value, y + hojaMarcoAncho.value, x + hojaMarcoAncho.value, y + h - hojaMarcoAncho.value ]),
  ]
}
const hojaMitras = computed(() => buildHojaMitras(movilBox.value.x, movilBox.value.y, movilBox.value.w, movilBox.value.h))
const vidrioMovil = computed(() => ({
  x: movilBox.value.x + hojaMarcoAncho.value,
  y: movilBox.value.y + hojaMarcoAncho.value,
  width: Math.max(movilBox.value.w - 2*hojaMarcoAncho.value, 5),
  height: Math.max(movilBox.value.h - 2*hojaMarcoAncho.value, 5),
  fill: 'lightblue',
  stroke: 'black',
}))

/* Manilla: sobre el perfil vertical pegado al marco (no en el encuentro) */
const manillaX = computed(() => {
  // centro del perfil vertical de la hoja móvil, lado pegado al marco
  const leftStileCenter  = movilBox.value.x + hojaMarcoAncho.value / 2
  const rightStileCenter = movilBox.value.x + movilBox.value.w - hojaMarcoAncho.value / 2
  return movEsIzq.value ? leftStileCenter : rightStileCenter
})
const manillaY = computed(() => movilBox.value.y + movilBox.value.h / 2)

/* Flecha de dirección: centrada dentro de la hoja móvil */
const arrowText = computed(() => (movEsIzq.value ? '→' : '←'))
const arrowX = computed(() => movilBox.value.x + movilBox.value.w / 2 - 10)
const arrowY = computed(() => movilBox.value.y + movilBox.value.h / 2 - 15)

/* Etiquetas */
const fontSize = computed(() => Math.min(Math.max((Math.max(props.ancho, props.alto) / 100), 20), 40))
const widthLabel = computed(() => ({ x: offset + screenWidth.value / 2 - 30, y: offset + screenHeight.value + 10, text: `${props.ancho}mm`, fontSize: fontSize.value, fill:'black' }))
const heightLabel = computed(() => ({ x: offset - 25, y: offset + screenHeight.value / 2, text: `${props.alto}mm`, fontSize: fontSize.value, fill:'black', rotation:-90, offsetX: fontSize.value / 2 }))

/* Exportar imagen */
const exportarImagen = () => {
  if (stageRef.value) {
    const stage = stageRef.value.getStage()
    stage.draw()
    return stage.toDataURL({ pixelRatio: 1, quality: 0.9 })
  }
  return null
}
defineExpose({ exportarImagen })
</script>