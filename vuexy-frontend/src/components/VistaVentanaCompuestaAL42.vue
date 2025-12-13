<template>
  <v-stage ref="stageRef" :config="{ width: 400, height: 400 }" :key="forceRenderKey">
    <v-layer>
      <!-- Marco exterior AL42 -->
      <v-line v-bind="marcoTopMitra" />
      <v-line v-bind="marcoRightMitra" />
      <v-line v-bind="marcoBottomMitra" />
      <v-line v-bind="marcoLeftMitra" />

      <!-- Palillos divisores -->
      <template v-for="(palillo, index) in palillosHorizontales" :key="'ph-' + index">
        <v-rect v-bind="palillo" />
      </template>
      
      <template v-for="(palillo, index) in palillosVerticales" :key="'pv-' + index">
        <v-rect v-bind="palillo" />
      </template>

      <!-- Renderizar cada sección directamente -->
      <template v-for="seccion in seccionesRenderizadas" :key="seccion.key">
        <!-- Vidrio de la sección -->
        <v-rect v-bind="seccion.vidrio" />
        
        <!-- Si es proyectante, agregar hoja móvil -->
        <template v-if="seccion.esProyectante">
          <v-line v-bind="seccion.hojaTop" />
          <v-line v-bind="seccion.hojaRight" />
          <v-line v-bind="seccion.hojaBottom" />
          <v-line v-bind="seccion.hojaLeft" />
          <v-line v-bind="seccion.lineaApertura1" />
          <v-line v-bind="seccion.lineaApertura2" />
        </template>
      </template>

      <!-- Dimensiones exteriores -->
      <v-text v-bind="labelAncho" />
      <v-text v-bind="labelAlto" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue'

const stageRef = ref(null)
const forceRenderKey = ref(0)

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
  filas: { type: Number, default: 1 },
  columnas: { type: Number, default: 1 },
  altosFilas: { type: Array, default: () => [] },
  anchosColumnas: { type: Array, default: () => [] },
  secciones: { type: Array, default: () => [[{ tipo: 1 }]] },
})

// Watcher para forzar re-render cuando cambien dimensiones
watch(() => [props.ancho, props.alto, props.filas, props.columnas], ([newAncho, newAlto, newFilas, newCols]) => {
  console.log('🔄 Props cambiaron:', { ancho: newAncho, alto: newAlto, filas: newFilas, columnas: newCols })
  // Incrementar key para forzar re-render completo
  forceRenderKey.value++
  console.log('🔑 Nuevo render key:', forceRenderKey.value)
})

const offset = 40
const marcoAnchoOriginal = 54
const palilloAnchoOriginal = 54

const escala = computed(() => {
  const maxDim = Math.max(props.ancho, props.alto)
  const espacioDisponible = 320
  return espacioDisponible / maxDim
})

const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)
const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const palilloAncho = computed(() => palilloAnchoOriginal * escala.value)

// Key para forzar re-render cuando cambien las dimensiones o configuración
const renderKey = computed(() => {
  return `${props.ancho}-${props.alto}-${props.filas}-${props.columnas}-${JSON.stringify(props.altosFilas)}-${JSON.stringify(props.anchosColumnas)}-${JSON.stringify(props.secciones)}`
})

// Computed que genera todas las secciones con sus configuraciones
const seccionesRenderizadas = computed(() => {
  const secciones = []
  
  // Calcular espacios interiores
  const anchoInteriorTotal = screenWidth.value - marcoAncho.value * 2 - palilloAncho.value * (props.columnas - 1)
  const altoInteriorTotal = screenHeight.value - marcoAncho.value * 2 - palilloAncho.value * (props.filas - 1)
  
  for (let fila = 0; fila < props.filas; fila++) {
    for (let col = 0; col < props.columnas; col++) {
      // Calcular posición de esta sección
      let xPos = offset + marcoAncho.value
      for (let c = 0; c < col; c++) {
        const anchoMM = props.anchosColumnas[c] || (props.ancho / props.columnas)
        const proporcion = anchoMM / props.ancho
        xPos += anchoInteriorTotal * proporcion + palilloAncho.value
      }
      
      let yPos = offset + marcoAncho.value
      for (let f = 0; f < fila; f++) {
        const altoMM = props.altosFilas[f] || (props.alto / props.filas)
        const proporcion = altoMM / props.alto
        yPos += altoInteriorTotal * proporcion + palilloAncho.value
      }
      
      // Calcular dimensiones de esta sección
      const anchoMM = props.anchosColumnas[col] || (props.ancho / props.columnas)
      const proporcionAncho = anchoMM / props.ancho
      const anchoSeccion = anchoInteriorTotal * proporcionAncho
      
      const altoMM = props.altosFilas[fila] || (props.alto / props.filas)
      const proporcionAlto = altoMM / props.alto
      const altoSeccion = altoInteriorTotal * proporcionAlto
      
      // Configuración del vidrio
      const vidrio = {
        x: xPos,
        y: yPos,
        width: anchoSeccion,
        height: altoSeccion,
        fill: '#b3e5fc',
        stroke: 'black',
        strokeWidth: 0.5,
      }
      
      const esProyectante = (props.secciones?.[fila]?.[col]?.tipo || 1) === 56
      
      const seccion = {
        key: `f${fila}-c${col}`,
        vidrio,
        esProyectante,
      }
      
      if (esProyectante) {
        const hojaAncho = palilloAncho.value * 0.35
        const hojaX = vidrio.x
        const hojaY = vidrio.y
        const hojaW = vidrio.width
        const hojaH = vidrio.height
        
        seccion.hojaTop = {
          points: [
            hojaX, hojaY,
            hojaX + hojaW, hojaY,
            hojaX + hojaW - hojaAncho, hojaY + hojaAncho,
            hojaX + hojaAncho, hojaY + hojaAncho,
          ],
          closed: true,
          fill: colorMarcoHex.value,
          stroke: 'black',
          strokeWidth: 0.4,
        }
        
        seccion.hojaRight = {
          points: [
            hojaX + hojaW, hojaY,
            hojaX + hojaW, hojaY + hojaH,
            hojaX + hojaW - hojaAncho, hojaY + hojaH - hojaAncho,
            hojaX + hojaW - hojaAncho, hojaY + hojaAncho,
          ],
          closed: true,
          fill: colorMarcoHex.value,
          stroke: 'black',
          strokeWidth: 0.4,
        }
        
        seccion.hojaBottom = {
          points: [
            hojaX + hojaW, hojaY + hojaH,
            hojaX, hojaY + hojaH,
            hojaX + hojaAncho, hojaY + hojaH - hojaAncho,
            hojaX + hojaW - hojaAncho, hojaY + hojaH - hojaAncho,
          ],
          closed: true,
          fill: colorMarcoHex.value,
          stroke: 'black',
          strokeWidth: 0.4,
        }
        
        seccion.hojaLeft = {
          points: [
            hojaX, hojaY + hojaH,
            hojaX, hojaY,
            hojaX + hojaAncho, hojaY + hojaAncho,
            hojaX + hojaAncho, hojaY + hojaH - hojaAncho,
          ],
          closed: true,
          fill: colorMarcoHex.value,
          stroke: 'black',
          strokeWidth: 0.4,
        }
        
        seccion.lineaApertura1 = {
          points: [
            hojaX + 5,
            hojaY + 5,
            hojaX + hojaW / 2,
            hojaY + hojaH - 5,
          ],
          stroke: 'black',
          strokeWidth: 0.8,
          dash: [3, 2],
        }
        
        seccion.lineaApertura2 = {
          points: [
            hojaX + hojaW - 5,
            hojaY + 5,
            hojaX + hojaW / 2,
            hojaY + hojaH - 5,
          ],
          stroke: 'black',
          strokeWidth: 0.8,
          dash: [3, 2],
        }
      }
      
      secciones.push(seccion)
    }
  }
  
  return secciones
})

const colorMarcoHex = computed(() => {
  const colores = {
    blanco: '#FFFFFF',
    mate: '#D8D8D8',
    negro: '#2C2C2C',
    bronce: '#CD7F32',
    champagne: '#F7E7CE',
    grafito: '#4A4A4A',
    cafe: '#8B4513',
    café: '#8B4513',
  }
  // Soportar objeto color o string
  const nombreColor = typeof props.colorMarco === 'object' 
    ? props.colorMarco?.nombre?.toLowerCase() 
    : props.colorMarco?.toLowerCase()
  return colores[nombreColor] || colores.blanco
})

// Marco exterior con mitras
const marcoTopMitra = computed(() => ({
  points: [
    offset, offset,
    offset + screenWidth.value, offset,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.8,
}))

const marcoRightMitra = computed(() => ({
  points: [
    offset + screenWidth.value, offset,
    offset + screenWidth.value, offset + screenHeight.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.8,
}))

const marcoBottomMitra = computed(() => ({
  points: [
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.8,
}))

const marcoLeftMitra = computed(() => ({
  points: [
    offset, offset + screenHeight.value,
    offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ],
  closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
  strokeWidth: 0.8,
}))

// Palillos divisores
const palillosHorizontales = computed(() => {
  const palillos = []
  
  for (let fila = 0; fila < props.filas - 1; fila++) {
    // Posición Y: marco + suma de secciones anteriores + palillos anteriores
    let yPos = offset + marcoAncho.value
    for (let f = 0; f <= fila; f++) {
      const altoMM = props.altosFilas[f] || (props.alto / props.filas)
      const proporcion = altoMM / props.alto
      const altoSeccion = getAltoInteriorTotal() * proporcion
      yPos += altoSeccion
      if (f < fila) yPos += palilloAncho.value
    }
    
    palillos.push({
      x: offset + marcoAncho.value,
      y: yPos,
      width: screenWidth.value - marcoAncho.value * 2,
      height: palilloAncho.value,
      fill: colorMarcoHex.value,
      stroke: 'black',
      strokeWidth: 0.8,
    })
  }
  
  return palillos
})

const palillosVerticales = computed(() => {
  const palillos = []
  
  for (let col = 0; col < props.columnas - 1; col++) {
    // Posición X: marco + suma de secciones anteriores + palillos anteriores
    let xPos = offset + marcoAncho.value
    for (let c = 0; c <= col; c++) {
      const anchoMM = props.anchosColumnas[c] || (props.ancho / props.columnas)
      const proporcion = anchoMM / props.ancho
      const anchoSeccion = getAnchoInteriorTotal() * proporcion
      xPos += anchoSeccion
      if (c < col) xPos += palilloAncho.value
    }
    
    palillos.push({
      x: xPos,
      y: offset + marcoAncho.value,
      width: palilloAncho.value,
      height: screenHeight.value - marcoAncho.value * 2,
      fill: colorMarcoHex.value,
      stroke: 'black',
      strokeWidth: 0.8,
    })
  }
  
  return palillos
})

// Helper functions - necesitan ser funciones para poder llamarlas desde seccionesRenderizadas
function getSeccionTipo(fila, col) {
  return props.secciones?.[fila]?.[col]?.tipo || 1
}

// Calcular el ancho disponible para las secciones (sin marco ni palillos)
function getAnchoInteriorTotal() {
  const anchoTotal = screenWidth.value
  const marcoTotal = marcoAncho.value * 2
  const palillosTotal = palilloAncho.value * (props.columnas - 1)
  return anchoTotal - marcoTotal - palillosTotal
}

function getAltoInteriorTotal() {
  const altoTotal = screenHeight.value
  const marcoTotal = marcoAncho.value * 2
  const palillosTotal = palilloAncho.value * (props.filas - 1)
  return altoTotal - marcoTotal - palillosTotal
}

function getSeccionAnchoMM(col) {
  return props.anchosColumnas[col] || (props.ancho / props.columnas)
}

function getSeccionAltoMM(fila) {
  return props.altosFilas[fila] || (props.alto / props.filas)
}

// Calcular el ancho de pantalla de cada sección proporcional al espacio interior
function getSeccionAnchoScreen(col) {
  const anchoMM = getSeccionAnchoMM(col)
  const proporcion = anchoMM / props.ancho
  return getAnchoInteriorTotal() * proporcion
}

function getSeccionAltoScreen(fila) {
  const altoMM = getSeccionAltoMM(fila)
  const proporcion = altoMM / props.alto
  return getAltoInteriorTotal() * proporcion
}

function getSeccionPosicion(fila, col) {
  // Posición X: marco + suma de secciones anteriores + palillos anteriores
  let xAcum = offset + marcoAncho.value
  for (let c = 0; c < col; c++) {
    xAcum += getSeccionAnchoScreen(c) + palilloAncho.value
  }
  
  // Posición Y: marco + suma de secciones anteriores + palillos anteriores
  let yAcum = offset + marcoAncho.value
  for (let f = 0; f < fila; f++) {
    yAcum += getSeccionAltoScreen(f) + palilloAncho.value
  }
  
  return { 
    x: xAcum, 
    y: yAcum, 
    width: getSeccionAnchoScreen(col), 
    height: getSeccionAltoScreen(fila)
  }
}

// Sección: Vidrio
function getSeccionVidrio(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  // El vidrio ocupa todo el espacio de la sección (ya calculado sin palillos ni marco)
  return {
    x: pos.x,
    y: pos.y,
    width: pos.width,
    height: pos.height,
    fill: '#b3e5fc',
    stroke: 'black',
    strokeWidth: 0.5,
  }
}

// Sección: Marco con mitras
function getSeccionMarcoTop(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const marcoInterno = palilloAncho.value / 2
  return {
    points: [
      pos.x, pos.y,
      pos.x + pos.width, pos.y,
      pos.x + pos.width - marcoInterno, pos.y + marcoInterno,
      pos.x + marcoInterno, pos.y + marcoInterno,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.3,
  }
}

function getSeccionMarcoRight(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const marcoInterno = palilloAncho.value / 2
  return {
    points: [
      pos.x + pos.width, pos.y,
      pos.x + pos.width, pos.y + pos.height,
      pos.x + pos.width - marcoInterno, pos.y + pos.height - marcoInterno,
      pos.x + pos.width - marcoInterno, pos.y + marcoInterno,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.3,
  }
}

function getSeccionMarcoBottom(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const marcoInterno = palilloAncho.value / 2
  return {
    points: [
      pos.x + pos.width, pos.y + pos.height,
      pos.x, pos.y + pos.height,
      pos.x + marcoInterno, pos.y + pos.height - marcoInterno,
      pos.x + pos.width - marcoInterno, pos.y + pos.height - marcoInterno,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.3,
  }
}

function getSeccionMarcoLeft(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const marcoInterno = palilloAncho.value / 2
  return {
    points: [
      pos.x, pos.y + pos.height,
      pos.x, pos.y,
      pos.x + marcoInterno, pos.y + marcoInterno,
      pos.x + marcoInterno, pos.y + pos.height - marcoInterno,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.3,
  }
}

// Hoja móvil para proyectante
function getSeccionHojaTop(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const vidrio = getSeccionVidrio(fila, col)
  const hojaAncho = palilloAncho.value * 0.35
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaW = vidrio.width
  return {
    points: [
      hojaX, hojaY,
      hojaX + hojaW, hojaY,
      hojaX + hojaW - hojaAncho, hojaY + hojaAncho,
      hojaX + hojaAncho, hojaY + hojaAncho,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.4,
  }
}

function getSeccionHojaRight(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const vidrio = getSeccionVidrio(fila, col)
  const hojaAncho = palilloAncho.value * 0.35
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaW = vidrio.width
  const hojaH = vidrio.height
  return {
    points: [
      hojaX + hojaW, hojaY,
      hojaX + hojaW, hojaY + hojaH,
      hojaX + hojaW - hojaAncho, hojaY + hojaH - hojaAncho,
      hojaX + hojaW - hojaAncho, hojaY + hojaAncho,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.4,
  }
}

function getSeccionHojaBottom(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const vidrio = getSeccionVidrio(fila, col)
  const hojaAncho = palilloAncho.value * 0.35
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaW = vidrio.width
  const hojaH = vidrio.height
  return {
    points: [
      hojaX + hojaW, hojaY + hojaH,
      hojaX, hojaY + hojaH,
      hojaX + hojaAncho, hojaY + hojaH - hojaAncho,
      hojaX + hojaW - hojaAncho, hojaY + hojaH - hojaAncho,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.4,
  }
}

function getSeccionHojaLeft(fila, col) {
  const pos = getSeccionPosicion(fila, col)
  const vidrio = getSeccionVidrio(fila, col)
  const hojaAncho = palilloAncho.value * 0.35
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaH = vidrio.height
  return {
    points: [
      hojaX, hojaY + hojaH,
      hojaX, hojaY,
      hojaX + hojaAncho, hojaY + hojaAncho,
      hojaX + hojaAncho, hojaY + hojaH - hojaAncho,
    ],
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
    strokeWidth: 0.4,
  }
}

function getSeccionLineaApertura1(fila, col) {
  const vidrio = getSeccionVidrio(fila, col)
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaW = vidrio.width
  const hojaH = vidrio.height
  return {
    points: [
      hojaX + 5,
      hojaY + 5,
      hojaX + hojaW / 2,
      hojaY + hojaH - 5,
    ],
    stroke: 'black',
    strokeWidth: 0.8,
    dash: [3, 2],
  }
}

function getSeccionLineaApertura2(fila, col) {
  const vidrio = getSeccionVidrio(fila, col)
  const hojaX = vidrio.x
  const hojaY = vidrio.y
  const hojaW = vidrio.width
  const hojaH = vidrio.height
  return {
    points: [
      hojaX + hojaW - 5,
      hojaY + 5,
      hojaX + hojaW / 2,
      hojaY + hojaH - 5,
    ],
    stroke: 'black',
    strokeWidth: 0.8,
    dash: [3, 2],
  }
}

// Labels
const labelAncho = computed(() => ({
  x: offset + screenWidth.value / 2 - 30,
  y: offset + screenHeight.value + 15,
  text: `${props.ancho}mm`,
  fontSize: 14,
  fill: 'black',
}))

const labelAlto = computed(() => ({
  x: offset - 20,
  y: offset + screenHeight.value / 2,
  text: `${props.alto}mm`,
  fontSize: 14,
  fill: 'black',
  rotation: -90,
}))

defineExpose({
  getStage: () => stageRef.value?.getStage(),
  exportarImagen: () => {
    if (stageRef.value) {
      try {
        const stage = stageRef.value.getStage()
        return stage.toDataURL({ pixelRatio: 2 })
      } catch (error) {
        console.error('❌ Error en exportarImagen:', error)
        return null
      }
    }
    return null
  }
})
</script>
