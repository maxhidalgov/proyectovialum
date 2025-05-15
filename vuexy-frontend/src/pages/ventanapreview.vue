<template>
    <v-card class="pa-4">
      <v-card-title class="text-h6">Vista previa ventana</v-card-title>
      <v-card-text>
        <svg
          :viewBox="`0 0 ${ancho} ${alto}`"
          :width="canvasWidth"
          :height="canvasHeight"
          style="border: 1px solid #ccc;"
        >
          <!-- Marco exterior -->
          <rect x="0" y="0" :width="ancho" :height="alto" :fill="colorMarco" stroke="black" stroke-width="10" />
  
          <!-- División central si hay 2 hojas -->
          <line v-if="dosHojas" :x1="ancho / 2" y1="0" :x2="ancho / 2" :y2="alto" stroke="black" stroke-width="2" />
  
          <!-- Vidrios -->
          <template v-if="dosHojas">
            <!-- A2 -->
            <rect x="10" y="10" :width="ancho / 2 - 20" :height="alto - 20" fill="#e0f7fa" stroke="black" stroke-width="1" />
            <text :x="ancho / 4" :y="alto / 2 - 10" text-anchor="middle">A2</text>
            <text :x="ancho / 4" :y="alto / 2 + 10" text-anchor="middle">{{ tipoVidrio }}</text>
            <text :x="ancho / 4" :y="alto / 2 + 30" text-anchor="middle">→</text>
  
            <!-- A1 -->
            <rect :x="ancho / 2 + 10" y="10" :width="ancho / 2 - 20" :height="alto - 20" fill="#e0f7fa" stroke="black" stroke-width="1" />
            <text :x="(ancho / 4) * 3" :y="alto / 2 - 10" text-anchor="middle">A1</text>
            <text :x="(ancho / 4) * 3" :y="alto / 2 + 10" text-anchor="middle">{{ tipoVidrio }}</text>
            <text :x="(ancho / 4) * 3" :y="alto / 2 + 30" text-anchor="middle">←</text>
          </template>
  
          <template v-else>
            <!-- Una hoja -->
            <rect x="10" y="10" :width="ancho - 20" :height="alto - 20" fill="#e0f7fa" stroke="black" stroke-width="1" />
            <text :x="ancho / 2" :y="alto / 2 - 10" text-anchor="middle">A1</text>
            <text :x="ancho / 2" :y="alto / 2 + 10" text-anchor="middle">{{ tipoVidrio }}</text>
          </template>
  
          <!-- Medidas -->
          <text :x="ancho / 2" :y="alto - 5" text-anchor="middle" font-size="20">{{ ancho }} mm</text>
          <text :transform="`rotate(-90 10,${alto / 2})`" x="10" :y="alto / 2" text-anchor="middle" font-size="20">{{ alto }} mm</text>
        </svg>
      </v-card-text>
    </v-card>
  </template>
  
  <script setup>
  const props = defineProps({
    ancho: { type: Number, default: 2000 },
    alto: { type: Number, default: 2000 },
    tipoVidrio: { type: String, default: 'TP-B 4+10+4' },
    colorMarco: { type: String, default: '#ffffff' }, // fondo configurable por prop
    hojasMoviles: { type: Number, default: 2 } // 1 o 2
  })
  
  const dosHojas = props.hojasMoviles === 2
  
  // Escalado automático proporcional (máx 400 px)
  const escalaMax = 400
  const aspectRatio = props.ancho / props.alto
  const canvasWidth = aspectRatio >= 1 ? escalaMax : escalaMax * aspectRatio
  const canvasHeight = aspectRatio >= 1 ? escalaMax / aspectRatio : escalaMax
  </script>
  
  <style scoped>
  svg text {
    font-family: 'Arial', sans-serif;
    fill: #000000;
  }
  </style>
  