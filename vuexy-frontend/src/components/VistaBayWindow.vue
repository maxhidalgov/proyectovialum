<template>
  <v-stage ref="stageRef" :config="{ width: canvasWidth, height: canvasHeight }">
    <v-layer ref="layerRef">
      <!-- IZQUIERDA -->
      <template v-if="num(anchoIzquierda) > 0">
        <VentanaCompuesta
          v-if="tipoVentanaIzquierda?.compuesta"
          :x="posXIzquierda"
          :y="posYGlobal"
          :ancho="num(anchoIzquierda)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :parte-superior="parteSuperiorIzquierda"
          :parte-inferior="parteInferiorIzquierda"
          :lado-apertura-superior="tipoVentanaIzquierda?.partes?.[0]?.ladoApertura || 'izquierda'"
          :lado-apertura-inferior="tipoVentanaIzquierda?.partes?.[1]?.ladoApertura || 'izquierda'"
          :direccion-apertura-superior="tipoVentanaIzquierda?.partes?.[0]?.direccionApertura || 'interior'"
          :direccion-apertura-inferior="tipoVentanaIzquierda?.partes?.[1]?.direccionApertura || 'interior'"
        />
        <VentanaSimple
          v-else
          :x="posXIzquierda"
          :y="posYGlobal"
          :ancho="num(anchoIzquierda)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :tipo="tipoVentanaIzquierda?.partes?.[0]?.tipo || tipoVentanaIzquierda?.tipo"
          :lado-apertura="tipoVentanaIzquierda?.ladoApertura || tipoVentanaIzquierda?.partes?.[0]?.ladoApertura || 'izquierda'"
          :direccion-apertura="tipoVentanaIzquierda?.direccionApertura || tipoVentanaIzquierda?.partes?.[0]?.direccionApertura || 'interior'"
          :hojas-moviles="tipoVentanaIzquierda?.hojas_moviles ?? null"
          :hoja-movil-seleccionada="tipoVentanaIzquierda?.hojaMovilSeleccionada ?? null"
          :orden-hoja1-al-frente="tipoVentanaIzquierda?.hoja1AlFrente ?? true"
        />
        <!-- Esquinero izq–centro -->
        <v-rect
          :x="posXEsquineroIzq"
          :y="posYGlobal"
          :width="ESQUINERO_PX"
          :height="altoRenderizado"
          v-bind="esquineroFill"
          :stroke="'#00000033'"
          :stroke-width="1"
        />
      </template>

      <!-- CENTRO -->
      <VentanaCompuesta
        v-if="tipoVentanaCentro?.compuesta"
        :x="posXCentro"
        :y="posYGlobal"
        :ancho="num(anchoCentro)"
        :alto="alto"
        :escala="escalaGlobal"
        :color-marco="colorMarco"
        :parte-superior="parteSuperiorCentro"
        :parte-inferior="parteInferiorCentro"
        :lado-apertura-superior="tipoVentanaCentro?.partes?.[0]?.ladoApertura || 'izquierda'"
        :lado-apertura-inferior="tipoVentanaCentro?.partes?.[1]?.ladoApertura || 'izquierda'"
        :direccion-apertura-superior="tipoVentanaCentro?.partes?.[0]?.direccionApertura || 'interior'"
        :direccion-apertura-inferior="tipoVentanaCentro?.partes?.[1]?.direccionApertura || 'interior'"
      />
      <VentanaSimple
        v-else
        :x="posXCentro"
        :y="posYGlobal"
        :ancho="num(anchoCentro)"
        :alto="alto"
        :escala="escalaGlobal"
        :color-marco="colorMarco"
        :tipo="tipoVentanaCentro?.partes?.[0]?.tipo || tipoVentanaCentro?.tipo"
        :lado-apertura="tipoVentanaCentro?.ladoApertura || tipoVentanaCentro?.partes?.[0]?.ladoApertura || 'izquierda'"
        :direccion-apertura="tipoVentanaCentro?.direccionApertura || tipoVentanaCentro?.partes?.[0]?.direccionApertura || 'interior'"
        :hojas-moviles="tipoVentanaCentro?.hojas_moviles ?? null"
        :hoja-movil-seleccionada="tipoVentanaCentro?.hojaMovilSeleccionada ?? null"
        :orden-hoja1-al-frente="tipoVentanaCentro?.hoja1AlFrente ?? true"
        :show-height-label="false"
      />

      <!-- DERECHA -->
      <template v-if="num(anchoDerecha) > 0">
        <!-- Esquinero centro–der -->
        <v-rect
          :x="posXEsquineroDer"
          :y="posYGlobal"
          :width="ESQUINERO_PX"
          :height="altoRenderizado"
          v-bind="esquineroFill"
          :stroke="'#00000033'"
          :stroke-width="1"
        />
        <VentanaCompuesta
          v-if="tipoVentanaDerecha?.compuesta"
          :x="posXDerecha"
          :y="posYGlobal"
          :ancho="num(anchoDerecha)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :parte-superior="parteSuperiorDerecha"
          :parte-inferior="parteInferiorDerecha"
          :lado-apertura-superior="tipoVentanaDerecha?.partes?.[0]?.ladoApertura || 'izquierda'"
          :lado-apertura-inferior="tipoVentanaDerecha?.partes?.[1]?.ladoApertura || 'izquierda'"
          :direccion-apertura-superior="tipoVentanaDerecha?.partes?.[0]?.direccionApertura || 'interior'"
          :direccion-apertura-inferior="tipoVentanaDerecha?.partes?.[1]?.direccionApertura || 'interior'"
        />
        <VentanaSimple
          v-else
          :x="posXDerecha"
          :y="posYGlobal"
          :ancho="num(anchoDerecha)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :tipo="tipoVentanaDerecha?.partes?.[0]?.tipo || tipoVentanaDerecha?.tipo"
          :lado-apertura="tipoVentanaDerecha?.ladoApertura || tipoVentanaDerecha?.partes?.[0]?.ladoApertura || 'izquierda'"
          :direccion-apertura="tipoVentanaDerecha?.direccionApertura || tipoVentanaDerecha?.partes?.[0]?.direccionApertura || 'interior'"
          :hojas-moviles="tipoVentanaDerecha?.hojas_moviles ?? null"
          :hoja-movil-seleccionada="tipoVentanaDerecha?.hojaMovilSeleccionada ?? null"
          :orden-hoja1-al-frente="tipoVentanaDerecha?.hoja1AlFrente ?? true"
          :show-height-label="false"
        />
      </template>
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed, watch, ref, nextTick, reactive, onMounted } from 'vue'
import VentanaSimple from './VentanaSimple.vue'
import VentanaCompuesta from './VentanaCompuesta.vue'
import robleUrl from '@/assets/images/roble.png'
import nogalUrl from '@/assets/images/nogal.png'

const stageRef = ref(null)
const layerRef = ref(null)

async function exportarImagen() {
  await nextTick()
  const layer = layerRef.value?.getNode?.()
  if (layer) layer.draw()
  await new Promise(resolve => setTimeout(resolve, 150))
  const stage = stageRef.value?.getStage?.()
  if (!stage) return null
  return stage.toDataURL({ pixelRatio: 1, quality: 0.9 })
}

defineExpose({ exportarImagen })

// ── Texturas para esquineros ─────────────────────────────────────────────────
const texturasEsq = reactive({ roble: null, nogal: null })
onMounted(() => {
  const cargar = (key, url) => {
    const img = new Image()
    img.src = url
    img.onload = () => { texturasEsq[key] = img }
  }
  cargar('roble', robleUrl)
  cargar('nogal', nogalUrl)
})

const ESQUINERO_PX = 12   // ancho visual del esquinero en px (fijo, no escalado)
const MARGIN = 45         // margen izquierdo (heightLabel en x=MARGIN-25=20)
const MAX_CANVAS_WIDTH = 870
const canvasHeight = 490
const posYGlobal = 55

const props = defineProps({
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  anchoIzquierda: { type: [Number, String], required: true },
  anchoCentro: { type: [Number, String], required: true },
  anchoDerecha: { type: [Number, String], required: true },
  tipoVentanaIzquierda: { type: Object, required: true },
  tipoVentanaCentro: { type: Object, required: true },
  tipoVentanaDerecha: { type: Object, required: true }
})

const num = (v) => Number(v) || 0

// ── Color del esquinero ──────────────────────────────────────────────────────
const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
  roble: '#b8864e',
  mate: '#c0beba',
  titanio: '#998F77',
}

const nombreColor = computed(() => {
  if (typeof props.colorMarco === 'object' && props.colorMarco?.nombre)
    return String(props.colorMarco.nombre).toLowerCase()
  if (typeof props.colorMarco === 'string')
    return props.colorMarco.toLowerCase()
  return 'blanco'
})

const colorHex = computed(() => colorHexMap[nombreColor.value] || '#ffffff')

// Fill para los v-rect de esquinero: textura si roble/nogal, sólido si no
const esquineroFill = computed(() => {
  const n = nombreColor.value
  if (['roble', 'nogal'].includes(n) && texturasEsq[n]) {
    return {
      fill: null,
      fillPatternImage: texturasEsq[n],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: 0.2, y: 0.2 },
    }
  }
  return { fill: colorHex.value, fillPatternImage: null }
})

// ── Derivados de forma ───────────────────────────────────────────────────────
const tieneIzquierda = computed(() => num(props.anchoIzquierda) > 0)
const tieneDerecha   = computed(() => num(props.anchoDerecha) > 0)

const numEsquineros  = computed(() =>
  (tieneIzquierda.value ? 1 : 0) + (tieneDerecha.value ? 1 : 0)
)

// ── Escala global ────────────────────────────────────────────────────────────
// Los esquineros son px fijos → se restan del espacio disponible antes de escalar
const anchoTotal = computed(() =>
  num(props.anchoIzquierda) + num(props.anchoCentro) + num(props.anchoDerecha)
)

const escalaGlobal = computed(() => {
  const espDisponibleAncho = MAX_CANVAS_WIDTH - MARGIN * 2 - numEsquineros.value * ESQUINERO_PX
  const eA = espDisponibleAncho / anchoTotal.value
  const eB = (canvasHeight - posYGlobal - 50) / props.alto
  return Math.min(eA, eB)
})

const canvasWidth = computed(() => {
  const contentWidth = anchoTotal.value * escalaGlobal.value + numEsquineros.value * ESQUINERO_PX
  return Math.round(MARGIN + contentWidth + MARGIN)
})

const altoRenderizado = computed(() => props.alto * escalaGlobal.value)

// ── Posiciones X ─────────────────────────────────────────────────────────────
const posXIzquierda = computed(() => MARGIN)

const posXEsquineroIzq = computed(() =>
  MARGIN + num(props.anchoIzquierda) * escalaGlobal.value
)

const posXCentro = computed(() =>
  tieneIzquierda.value
    ? posXEsquineroIzq.value + ESQUINERO_PX
    : MARGIN
)

const posXEsquineroDer = computed(() =>
  posXCentro.value + num(props.anchoCentro) * escalaGlobal.value
)

const posXDerecha = computed(() =>
  posXEsquineroDer.value + ESQUINERO_PX
)

// ── Ajuste de partes compuestas ───────────────────────────────────────────────
function actualizarParteContraria(tipoVentana, parte, nuevoValor) {
  if (!tipoVentana?.partes) return
  const altoParte = num(nuevoValor)
  const restante = props.alto - altoParte
  if (restante < 0) return
  if (parte === 'superior') tipoVentana.partes[1].alto = restante
  else if (parte === 'inferior') tipoVentana.partes[0].alto = restante
}

watch(() => props.tipoVentanaIzquierda?.partes?.[0]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaIzquierda, 'superior', v))
watch(() => props.tipoVentanaIzquierda?.partes?.[1]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaIzquierda, 'inferior', v))
watch(() => props.tipoVentanaCentro?.partes?.[0]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaCentro, 'superior', v))
watch(() => props.tipoVentanaCentro?.partes?.[1]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaCentro, 'inferior', v))
watch(() => props.tipoVentanaDerecha?.partes?.[0]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaDerecha, 'superior', v))
watch(() => props.tipoVentanaDerecha?.partes?.[1]?.alto,
  (v) => actualizarParteContraria(props.tipoVentanaDerecha, 'inferior', v))

function ajustarPartes(tipoVentana) {
  const sup = tipoVentana?.partes?.[0] || {}
  const inf = tipoVentana?.partes?.[1] || {}
  const altoSup = num(sup.alto)
  const altoInf = num(inf.alto)
  if (altoSup + altoInf === 0) {
    return {
      parteSuperior: { ...sup, alto: props.alto / 2 },
      parteInferior: { ...inf, alto: props.alto / 2 }
    }
  }
  if (altoSup + altoInf !== props.alto) {
    const factor = props.alto / (altoSup + altoInf)
    return {
      parteSuperior: { ...sup, alto: altoSup * factor },
      parteInferior: { ...inf, alto: altoInf * factor }
    }
  }
  return { parteSuperior: sup, parteInferior: inf }
}

const parteSuperiorIzquierda = computed(() => ajustarPartes(props.tipoVentanaIzquierda).parteSuperior)
const parteInferiorIzquierda  = computed(() => ajustarPartes(props.tipoVentanaIzquierda).parteInferior)
const parteSuperiorCentro     = computed(() => ajustarPartes(props.tipoVentanaCentro).parteSuperior)
const parteInferiorCentro     = computed(() => ajustarPartes(props.tipoVentanaCentro).parteInferior)
const parteSuperiorDerecha    = computed(() => ajustarPartes(props.tipoVentanaDerecha).parteSuperior)
const parteInferiorDerecha    = computed(() => ajustarPartes(props.tipoVentanaDerecha).parteInferior)
</script>
