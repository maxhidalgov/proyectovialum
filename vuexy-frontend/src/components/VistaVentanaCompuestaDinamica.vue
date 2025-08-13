<template>
  <v-stage :config="{ width: 900, height: 420 }">
    <v-layer>
      <!-- marco del área -->
      <v-rect :x="0" :y="0" :width="900" :height="420" stroke="#444" :strokeWidth="1" />
      
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
        @agregar="(idx) => emit('agregar', idx)"
        @agregar-borde="(pos) => emit('agregar-borde', pos)"
        @editar-ventana="(path) => emit('editar-ventana', path)"
        @eliminar-ventana="(path) => emit('eliminar-ventana', path)"
      />

      <!-- BOTONES NARANJAS centrados según el tamaño real -->
      <!-- Botón arriba -->
      <v-group @click="emit('agregar-borde', 'arriba')" style="cursor:pointer">
        <v-circle
          :x="offsetX + (anchoMM * escala) / 2" 
          :y="offsetY - 15" 
          :radius="14"
          fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.85"
        />
        <v-text
          :x="offsetX + (anchoMM * escala) / 2 - 7" 
          :y="offsetY - 25"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold"
        />
      </v-group>

      <!-- Botón abajo -->
      <v-group @click="emit('agregar-borde', 'abajo')" style="cursor:pointer">
        <v-circle
          :x="offsetX + (anchoMM * escala) / 2" 
          :y="offsetY + (altoMM * escala) + 15" 
          :radius="14"
          fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.85"
        />
        <v-text
          :x="offsetX + (anchoMM * escala) / 2 - 7" 
          :y="offsetY + (altoMM * escala) + 5"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold"
        />
      </v-group>

      <!-- Botón izquierda -->
      <v-group @click="emit('agregar-borde', 'izquierda')" style="cursor:pointer">
        <v-circle
          :x="offsetX - 15" 
          :y="offsetY + (altoMM * escala) / 2" 
          :radius="14"
          fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.85"
        />
        <v-text
          :x="offsetX - 22" 
          :y="offsetY + (altoMM * escala) / 2 - 10"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold"
        />
      </v-group>

      <!-- Botón derecha -->
      <v-group @click="emit('agregar-borde', 'derecha')" style="cursor:pointer">
        <v-circle
          :x="offsetX + (anchoMM * escala) + 15" 
          :y="offsetY + (altoMM * escala) / 2" 
          :radius="14"
          fill="#fff" stroke="#ff5722" :strokeWidth="2" opacity="0.85"
        />
        <v-text
          :x="offsetX + (anchoMM * escala) + 8" 
          :y="offsetY + (altoMM * escala) / 2 - 10"
          text="+" fontSize="24" fill="#ff5722" fontStyle="bold"
        />
      </v-group>
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed, defineEmits } from 'vue'
import VistaVentanaCompuestaInterna from './VistaVentanaCompuestaInterna.vue'

const emit = defineEmits(['agregar', 'agregar-borde', 'editar-ventana', 'eliminar-ventana'])

const props = defineProps({
  ancho: { type: [Number, String], required: true },
  alto: { type: [Number, String], required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  orientacion: { type: String, default: 'horizontal' },
  items: { type: Array, default: () => [] },
  forceReRender: { type: Number, default: 0 }
})

// ✅ MEJORADO: Área de renderizado adaptativa
const AREA_W = 860, AREA_H = 360
const anchoMM = computed(() => Number(props.ancho) || 1)
const altoMM = computed(() => Number(props.alto) || 1)

// ✅ Escala adaptativa para que siempre se vea completo
const escala = computed(() => {
  const escalaAncho = AREA_W / anchoMM.value
  const escalaAlto = AREA_H / altoMM.value
  const escalaMinima = Math.min(escalaAncho, escalaAlto)
  
  // Limita la escala mínima para que no sea demasiado pequeño
  return Math.max(escalaMinima, 0.1)
})

// ✅ Posición centrada si la ventana es más pequeña que el área
const offsetX = computed(() => {
  const anchoRenderizado = anchoMM.value * escala.value
  return anchoRenderizado < AREA_W ? (AREA_W - anchoRenderizado) / 2 + 20 : 20
})

const offsetY = computed(() => {
  const altoRenderizado = altoMM.value * escala.value
  return altoRenderizado < AREA_H ? (AREA_H - altoRenderizado) / 2 + 20 : 20
})
</script>