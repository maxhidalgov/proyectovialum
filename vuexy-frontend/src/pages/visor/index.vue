<!-- VentanaRealistaSVG.vue con 2 hojas y esquinas 45° detalladas -->
<template>
  <v-card class="pa-4">
    <v-row>
      <v-col cols="6">
        <v-text-field v-model.number="ancho" label="Ancho (mm)" type="number" />
        <v-text-field v-model.number="alto" label="Alto (mm)" type="number" />
        <v-btn class="mt-4" color="primary" @click="dibujar = !dibujar">Dibujar ventana realista</v-btn>
      </v-col>

      <v-col cols="6">
        <svg v-if="dibujar" :width="svgW" :height="svgH" :viewBox="`0 0 ${ancho} ${alto}`" style="border:1px solid #ccc;">
          <defs>
            <linearGradient id="vidrio" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#d3f2ff" stop-opacity="0.9"/>
              <stop offset="100%" stop-color="#4c8ca7" stop-opacity="0.9"/>
            </linearGradient>
          </defs>

          <!-- Marco exterior 45° detallado -->
          <polygon
            :points="`
              0,40 40,0 ${ancho - 40},0 ${ancho},40
              ${ancho},${alto - 40} ${ancho - 40},${alto}
              40,${alto} 0,${alto - 40}`"
            fill="#ccc"
            stroke="#888"
            stroke-width="1"
          />

          <!-- Marco hoja izquierda -->
          <polygon
            :points="`
              0,40 40,0 ${ancho/2 - 40},0 ${ancho/2},40
              ${ancho/2},${alto - 40} ${ancho/2 - 40},${alto}
              40,${alto} 0,${alto - 40}`"
            fill="#e0e0e0"
            stroke="#999"
            stroke-width="1"
          />

          <!-- Vidrio hoja izquierda -->
          <rect
            x="60"
            y="60"
            :width="ancho / 2 - 120"
            :height="alto - 120"
            fill="url(#vidrio)"
            stroke="#666"
            stroke-width="1"
          />

          <!-- Manilla izquierda -->
          <ManillaImagen :x="30" :y="alto / 2 - altoManilla / 2" :ancho="altoManilla * 0.5" :alto="altoManilla" />
          <FlechaNumero :x="ancho / 4" :y="alto / 2" numero="1" direccion="derecha" />

          <!-- Marco hoja derecha -->
          <polygon
            :points="`
              ${ancho/2},40 ${ancho/2 + 40},0 ${ancho - 40},0 ${ancho},40
              ${ancho},${alto - 40} ${ancho - 40},${alto}
              ${ancho/2 + 40},${alto} ${ancho/2},${alto - 40}`"
            fill="#e0e0e0"
            stroke="#999"
            stroke-width="1"
          />

          <!-- Vidrio hoja derecha -->
          <rect
            :x="ancho / 2 + 60"
            y="60"
            :width="ancho / 2 - 120"
            :height="alto - 120"
            fill="url(#vidrio)"
            stroke="#666"
            stroke-width="1"
          />

          <!-- Manilla derecha -->
          <ManillaImagen :x="ancho - 60" :y="alto / 2 - altoManilla / 2" :ancho="altoManilla * 0.5" :alto="altoManilla" />
          <FlechaNumero :x="ancho * 0.75" :y="alto / 2" numero="2" direccion="izquierda" />
        </svg>
      </v-col>
    </v-row>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue'
import ManillaImagen from '@/components/svg/ManillaImagen.vue'
import FlechaNumero from '@/components/svg/FlechaNumero.vue'

const ancho = ref(2000)
const alto = ref(2000)
const dibujar = ref(true)

const escala = 0.25
const svgW = computed(() => ancho.value * escala)
const svgH = computed(() => alto.value * escala)

const altoManilla = computed(() => {
  const min = 60
  const max = 180
  const escalaAltura = alto.value / 2000
  return Math.min(max, Math.max(min, Math.round(escalaAltura * 160)))
})
</script>
