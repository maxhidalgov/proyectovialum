<template>
  <div>
    <!-- ✅ SELECTORES FUERA DEL STAGE -->
    <div style="margin-bottom: 10px;">
      <v-radio-group 
        v-model="ladoApertura" 
        row 
        density="compact"
        label="Lado de apertura:"
      >
        <v-radio label="Izquierda" value="izquierda" />
        <v-radio label="Derecha" value="derecha" />
      </v-radio-group>

      <!-- ✅ NUEVO: Interior/Exterior -->
      <v-radio-group
        v-model="direccionApertura"
        row
        density="compact"
        label="Dirección de apertura:"
      >
        <v-radio label="Interior" value="interior" />
        <v-radio label="Exterior" value="exterior" />
      </v-radio-group>
    </div>

    <!-- ✅ STAGE SOLO CON CONTENIDO KONVA -->
    <v-stage ref="stageRef" :config="stageConfig">
      <v-layer>
        <!-- Marco exterior con mitras -->
        <v-line :config="topMitra" />
        <v-line :config="rightMitra" />
        <v-line :config="bottomMitra" />
        <v-line :config="leftMitra" />

        <!-- Vidrio general -->
        <v-rect :config="glassConfig" />

        <!-- Mitras de hoja interior -->
        <v-line :config="hojaTopMitra" />
        <v-line :config="hojaRightMitra" />
        <v-line :config="hojaBottomMitra" />
        <v-line :config="hojaLeftMitra" />

        <!-- Manilla (cambia según el lado) -->
        <Manilla
          :x="manillaX"
          :y="manillaY"
          :width="6"
          :height="20"
          :rotation="0"
          :offsetX="3"
          :offsetY="10"
        />

        <!-- Líneas de apertura laterales -->
        <v-line :config="lineaApertura1" />
        <v-line :config="lineaApertura2" />

        <!-- Etiquetas -->
        <v-text :config="widthLabel" />
        <v-text :config="heightLabel" />
        <!-- ✅ NUEVA ETIQUETA -->
        <v-text :config="direccionLabel" />
      </v-layer>
    </v-stage>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import Manilla from '@/components/Manilla.vue'

// Props
const props = defineProps({
  ancho: Number,
  alto: Number,
  colorMarco: [String, Object],
  material: [String, Number],
  tipoVidrio: [String, Number],
  productoVidrioProveedor: [String, Number],
  ladoInicial: {
    type: String,
    default: 'izquierda',
    validator: value => ['izquierda', 'derecha'].includes(value)
  },
  // ✅ NUEVO: dirección por defecto
  direccionApertura: {
    type: String,
    default: 'interior',
    validator: v => ['interior', 'exterior'].includes(v)
  }
})

// ✅ AGREGAR: Definir emisiones (estaba faltando esta línea)
const emit = defineEmits(['update:ladoApertura', 'update:direccionApertura'])
// Estado reactivo
const ladoApertura = ref(props.ladoInicial)
// ✅ NUEVO estado
const direccionApertura = ref(props.direccionApertura)

// ✅ AGREGAR: Watchers para emitir cambios al padre
watch(ladoApertura, (newValue) => {
  console.log('🔄 Lado apertura cambió a:', newValue)
  emit('update:ladoApertura', newValue)
})

watch(direccionApertura, (newValue) => {
  console.log('🔄 Dirección apertura cambió a:', newValue)
  emit('update:direccionApertura', newValue)
})


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
const hojaSeparacion = 0
const maxCanvasSize = 300

const escala = computed(() => {
  const escalaAncho = maxCanvasSize / props.ancho
  const escalaAlto = maxCanvasSize / props.alto
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9
})
const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

const stageConfig = { width: 400, height: 400 }

// Marco exterior (igual que proyectante)
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

// Vidrio general (igual que proyectante)
const glassConfig = computed(() => ({
  x: offset + marcoAncho.value,
  y: offset + marcoAncho.value,
  width: screenWidth.value - marcoAncho.value * 2,
  height: screenHeight.value - marcoAncho.value * 2,
  fill: '#b3e5fc',
  stroke: 'black',
}))

// Marco hoja abatible (igual que proyectante)
const hojaX = computed(() => glassConfig.value.x + hojaSeparacion * escala.value)
const hojaY = computed(() => glassConfig.value.y + hojaSeparacion * escala.value)
const hojaW = computed(() => glassConfig.value.width - hojaSeparacion * 2 * escala.value)
const hojaH = computed(() => glassConfig.value.height - hojaSeparacion * 2 * escala.value)
const hojaAncho = computed(() => marcoAncho.value * 0.8)

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

// ✅ MANILLA CAMBIA SEGÚN EL LADO DE APERTURA
const manillaX = computed(() => {
  if (ladoApertura.value === 'izquierda') {
    // Si abre hacia la izquierda, manilla va en el lado derecho
    return hojaX.value + hojaW.value - 15
  } else {
    // Si abre hacia la derecha, manilla va en el lado izquierdo
    return hojaX.value + 15
  }
})

const manillaY = computed(() => hojaY.value + hojaH.value / 2)

// ✅ LÍNEAS DE APERTURA LATERALES (CAMBIAN SEGÚN EL LADO)
const lineaApertura1 = computed(() => {
  if (ladoApertura.value === 'izquierda') {
    // Apertura hacia la izquierda: líneas desde el lado izquierdo
    return {
      points: [
        hojaX.value + 5,
        hojaY.value + 5,
        hojaX.value + hojaW.value - 5,
        hojaY.value + hojaH.value / 2,
      ],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    }
  } else {
    // Apertura hacia la derecha: líneas desde el lado derecho
    return {
      points: [
        hojaX.value + hojaW.value - 5,
        hojaY.value + 5,
        hojaX.value + 5,
        hojaY.value + hojaH.value / 2,
      ],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    }
  }
})

const lineaApertura2 = computed(() => {
  if (ladoApertura.value === 'izquierda') {
    // Apertura hacia la izquierda: segunda línea
    return {
      points: [
        hojaX.value + 5,
        hojaY.value + hojaH.value - 5,
        hojaX.value + hojaW.value - 5,
        hojaY.value + hojaH.value / 2,
      ],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    }
  } else {
    // Apertura hacia la derecha: segunda línea
    return {
      points: [
        hojaX.value + hojaW.value - 5,
        hojaY.value + hojaH.value - 5,
        hojaX.value + 5,
        hojaY.value + hojaH.value / 2,
      ],
      stroke: 'black',
      dash: [10, 5],
      strokeWidth: 2,
    }
  }
})

// Etiquetas (iguales que proyectante)
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

// ✅ NUEVA etiqueta: "Apertura Interior/Exterior"
const direccionLabel = computed(() => ({
  x: widthLabel.value.x,
  y: widthLabel.value.y + fontSize.value + 5,
  text: `Apertura ${direccionApertura.value.charAt(0).toUpperCase()}${direccionApertura.value.slice(1)}`,
  fontSize: Math.min(18, fontSize.value * 0.8),
  fill: 'black',
}))

// Al final del script setup de VistaVentanaAbatirS60.vue
const stageRef = ref(null)

const exportarImagen = () => {
  if (stageRef.value) {
    const dataURL = stageRef.value.getStage().toDataURL({ pixelRatio: 1, quality: 0.9 })
    return dataURL
  }
  return null
}

defineExpose({ exportarImagen })
</script>