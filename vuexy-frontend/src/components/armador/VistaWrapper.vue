<template>
  <div class="vista-wrapper" :style="wrapperStyle">
    <component
      :is="componenteOriginal"
      :ancho="anchoEscalado"
      :alto="altoEscalado"
      :color-marco="colorMarco"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  componenteOriginal: { type: Object, required: true },
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
})

// El componente original dibuja en 400x400 con maxCanvasSize=300
// Necesitamos escalar el canvas resultante al tamaño real del nodo
const maxCanvasOriginal = 400

// Factor de escala para ajustar el canvas de 400x400 al tamaño del nodo
const escalaX = computed(() => props.ancho / maxCanvasOriginal)
const escalaY = computed(() => props.alto / maxCanvasOriginal)

// Para que el componente original dibuje correctamente, necesitamos pasarle
// dimensiones "ficticias" que al ser escaladas internamente den el resultado correcto
// Como el componente usa maxCanvasSize=300 y escala a eso, necesitamos invertir esa lógica

// Calculamos qué ancho/alto debemos pasar para que después de la escala interna
// del componente (que lo reducirá a ~300px), al escalarlo nosotros, llegue al tamaño real
const anchoEscalado = computed(() => {
  // Queremos que el resultado final sea props.ancho
  // El componente escalará internamente a ~300px
  // Nosotros escalaremos eso a props.ancho
  // Por tanto, necesitamos pasar dimensiones que mantengan la proporción correcta
  return props.ancho
})

const altoEscalado = computed(() => {
  return props.alto
})

const wrapperStyle = computed(() => ({
  width: `${props.ancho}px`,
  height: `${props.alto}px`,
  transform: `scale(${escalaX.value}, ${escalaY.value})`,
  transformOrigin: 'top left',
  overflow: 'hidden',
}))
</script>

<style scoped>
.vista-wrapper {
  box-sizing: border-box;
}
</style>
