<template>
  <v-stage :config="{ width: canvasWidth, height: canvasHeight }">
    <v-layer>
      <!-- IZQUIERDA -->
      <template v-if="tipoVentanaIzquierda?.compuesta">
        <VentanaCompuesta
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
      </template>
      <template v-else>
        <VentanaSimple
          :x="posXIzquierda"
          :y="posYGlobal"
          :ancho="num(anchoIzquierda)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :tipo="tipoVentanaIzquierda?.partes?.[0]?.tipo || tipoVentanaIzquierda?.tipo"
          :lado-apertura="tipoVentanaIzquierda?.ladoApertura || tipoVentanaIzquierda?.partes?.[0]?.ladoApertura || 'izquierda'"
          :direccion-apertura="tipoVentanaIzquierda?.direccionApertura || tipoVentanaIzquierda?.partes?.[0]?.direccionApertura || 'interior'"
        />
      </template>

      <!-- CENTRO -->
      <template v-if="tipoVentanaCentro?.compuesta">
        <VentanaCompuesta
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
      </template>
      <template v-else>
        <VentanaSimple
          :x="posXCentro"
          :y="posYGlobal"
          :ancho="num(anchoCentro)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :tipo="tipoVentanaCentro?.partes?.[0]?.tipo || tipoVentanaCentro?.tipo"
          :lado-apertura="tipoVentanaCentro?.ladoApertura || tipoVentanaCentro?.partes?.[0]?.ladoApertura || 'izquierda'"
          :direccion-apertura="tipoVentanaCentro?.direccionApertura || tipoVentanaCentro?.partes?.[0]?.direccionApertura || 'interior'"
        />
      </template>

      <!-- DERECHA -->
      <template v-if="tipoVentanaDerecha?.compuesta">
        <VentanaCompuesta
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
      </template>
      <template v-else>
        <VentanaSimple
          :x="posXDerecha"
          :y="posYGlobal"
          :ancho="num(anchoDerecha)"
          :alto="alto"
          :escala="escalaGlobal"
          :color-marco="colorMarco"
          :tipo="tipoVentanaDerecha?.partes?.[0]?.tipo || tipoVentanaDerecha?.tipo"
          :lado-apertura="tipoVentanaDerecha?.ladoApertura || tipoVentanaDerecha?.partes?.[0]?.ladoApertura || 'izquierda'"
          :direccion-apertura="tipoVentanaDerecha?.direccionApertura || tipoVentanaDerecha?.partes?.[0]?.direccionApertura || 'interior'"
        />
      </template>
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed,watch } from 'vue'
import VentanaSimple from './VentanaSimple.vue'
import VentanaCompuesta from './VentanaCompuesta.vue'



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

// 🔹 Función para actualizar la parte contraria
function actualizarParteContraria(tipoVentana, parte, nuevoValor) {
  if (!tipoVentana?.partes) return
  const altoParte = num(nuevoValor)
  const restante = props.alto - altoParte
  if (restante < 0) return

  if (parte === 'superior') {
    tipoVentana.partes[1].alto = restante
  } else if (parte === 'inferior') {
    tipoVentana.partes[0].alto = restante
  }
}

// 🔹 Watchers para cada ventana (bidireccional)
watch(
  () => props.tipoVentanaIzquierda?.partes?.[0]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaIzquierda, 'superior', nuevo)
)
watch(
  () => props.tipoVentanaIzquierda?.partes?.[1]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaIzquierda, 'inferior', nuevo)
)

watch(
  () => props.tipoVentanaCentro?.partes?.[0]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaCentro, 'superior', nuevo)
)
watch(
  () => props.tipoVentanaCentro?.partes?.[1]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaCentro, 'inferior', nuevo)
)

watch(
  () => props.tipoVentanaDerecha?.partes?.[0]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaDerecha, 'superior', nuevo)
)
watch(
  () => props.tipoVentanaDerecha?.partes?.[1]?.alto,
  (nuevo) => actualizarParteContraria(props.tipoVentanaDerecha, 'inferior', nuevo)
)



// 🔹 Función para ajustar automáticamente las partes
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

// 🔹 Computed para cada sección
const parteSuperiorIzquierda = computed(() => ajustarPartes(props.tipoVentanaIzquierda).parteSuperior)
const parteInferiorIzquierda = computed(() => ajustarPartes(props.tipoVentanaIzquierda).parteInferior)

const parteSuperiorCentro = computed(() => ajustarPartes(props.tipoVentanaCentro).parteSuperior)
const parteInferiorCentro = computed(() => ajustarPartes(props.tipoVentanaCentro).parteInferior)

const parteSuperiorDerecha = computed(() => ajustarPartes(props.tipoVentanaDerecha).parteSuperior)
const parteInferiorDerecha = computed(() => ajustarPartes(props.tipoVentanaDerecha).parteInferior)

// 🔹 Configuración general de canvas
const canvasWidth = 900
const canvasHeight = 400
const separacion = 20
const posYGlobal = 50

const anchoTotal = computed(() =>
  num(props.anchoIzquierda) + num(props.anchoCentro) + num(props.anchoDerecha)
)

const escalaGlobal = computed(() => {
  const eA = (canvasWidth - separacion * 2) / anchoTotal.value
  const eB = (canvasHeight - 100) / props.alto
  return Math.min(eA, eB)
})

const posXIzquierda = computed(() => 0)
const posXCentro = computed(() => num(props.anchoIzquierda) * escalaGlobal.value + separacion)
const posXDerecha = computed(() => (num(props.anchoIzquierda) + num(props.anchoCentro)) * escalaGlobal.value + separacion * 2)
</script>
