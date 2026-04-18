<template>
  <v-stage ref="stageRef" :config="{ width: 400, height: 400 }">
    <v-layer ref="layerRef">
      <!-- Marco (oculto al exportar) -->
      <v-rect v-if="!modoExportar"
        :x="0" :y="0" :width="400" :height="400"
        stroke="#444" :strokeWidth="1"
      />

      <VistaVentanaCompuestaInterna
        :key="`interna-${forceReRender}`"
        :ancho="ancho"
        :alto="alto"
        :colorMarco="colorMarco"
        :orientacion="orientacion"
        :items="items"
        :escala="escala"
        :x="offsetX"
        :y="offsetY"
        :modo-exportar="modoExportar"
        @agregar="(payload) => emit('agregar', payload)"
        @editar-ventana="(path) => emit('editar-ventana', path)"
        @eliminar-ventana="(path) => emit('eliminar-ventana', path)"
      />

      <!-- Naranja arriba -->
      <v-group v-if="!modoExportar" @click="emit('agregar-borde', 'arriba')" style="cursor:pointer">
        <v-circle :x="offsetX + (anchoMM * escala) / 2" :y="offsetY - 18"
          :radius="14" fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.9" />
        <v-text :x="offsetX + (anchoMM * escala) / 2 - 7" :y="offsetY - 28"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold" />
      </v-group>

      <!-- Naranja abajo -->
      <v-group v-if="!modoExportar" @click="emit('agregar-borde', 'abajo')" style="cursor:pointer">
        <v-circle :x="offsetX + (anchoMM * escala) / 2" :y="offsetY + (altoMM * escala) + 18"
          :radius="14" fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.9" />
        <v-text :x="offsetX + (anchoMM * escala) / 2 - 7" :y="offsetY + (altoMM * escala) + 8"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold" />
      </v-group>

      <!-- Naranja izquierda -->
      <v-group v-if="!modoExportar" @click="emit('agregar-borde', 'izquierda')" style="cursor:pointer">
        <v-circle :x="offsetX - 18" :y="offsetY + (altoMM * escala) / 2"
          :radius="14" fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.9" />
        <v-text :x="offsetX - 25" :y="offsetY + (altoMM * escala) / 2 - 10"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold" />
      </v-group>

      <!-- Naranja derecha -->
      <v-group v-if="!modoExportar" @click="emit('agregar-borde', 'derecha')" style="cursor:pointer">
        <v-circle :x="offsetX + (anchoMM * escala) + 18" :y="offsetY + (altoMM * escala) / 2"
          :radius="14" fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.9" />
        <v-text :x="offsetX + (anchoMM * escala) + 11" :y="offsetY + (altoMM * escala) / 2 - 10"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold" />
      </v-group>
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed, ref, nextTick } from 'vue'
import VistaVentanaCompuestaInterna from './VistaVentanaCompuestaInterna.vue'

const emit = defineEmits(['agregar', 'agregar-borde', 'editar-ventana', 'eliminar-ventana'])

const stageRef = ref(null)
const layerRef = ref(null)
const modoExportar = ref(false)

async function exportarImagen() {
  modoExportar.value = true

  await nextTick()

  const layer = layerRef.value?.getNode?.()
  if (layer) layer.draw()

  await new Promise(resolve => setTimeout(resolve, 150))

  const stage = stageRef.value?.getStage?.()
  if (!stage) { modoExportar.value = false; return null }

  const dataURL = stage.toDataURL({ pixelRatio: 1, quality: 0.9 })

  modoExportar.value = false
  return dataURL
}

defineExpose({ exportarImagen })

const props = defineProps({
  ancho: { type: [Number, String], required: true },
  alto: { type: [Number, String], required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  orientacion: { type: String, default: 'horizontal' },
  items: { type: Array, default: () => [] },
  forceReRender: { type: Number, default: 0 }
})

const AREA_W = 360, AREA_H = 360
const anchoMM = computed(() => Number(props.ancho) || 1)
const altoMM = computed(() => Number(props.alto) || 1)

const escala = computed(() => {
  const escalaAncho = AREA_W / anchoMM.value
  const escalaAlto = AREA_H / altoMM.value
  return Math.max(Math.min(escalaAncho, escalaAlto), 0.1)
})

const offsetX = computed(() => {
  const anchoRenderizado = anchoMM.value * escala.value
  return anchoRenderizado < AREA_W ? (AREA_W - anchoRenderizado) / 2 + 20 : 20
})

const offsetY = computed(() => {
  const altoRenderizado = altoMM.value * escala.value
  return altoRenderizado < AREA_H ? (AREA_H - altoRenderizado) / 2 + 20 : 20
})
</script>
