<!-- ManillaBlanca.vue - SVG realista como componente -->
<template>
  <g>
    <!-- cuerpo de manilla blanca -->
    <rect
      :x="x"
      :y="y - alto / 2"
      :width="ancho"
      :height="alto"
      rx="4"
      ry="4"
      fill="white"
      stroke="#bbb"
      stroke-width="2"
    />
    <!-- detalle interior (sombra o reflejo) -->
    <line
      :x1="x + ancho / 2"
      :y1="y - alto / 2"
      :x2="x + ancho / 2"
      :y2="y + alto / 2"
      stroke="#ccc"
      stroke-width="1"
    />
  </g>
</template>

<script setup>
const props = defineProps({
  x: Number,
  y: Number,
  ancho: { type: Number, default: 12 },
  alto: { type: Number, default: 70 }
})
</script>
