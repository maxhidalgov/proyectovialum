<!-- FlechaNumero.vue - flecha con número y dirección -->
<template>
  <g>
    <polygon
      :points="puntos"
      fill="white"
      stroke="#333"
      stroke-width="2"
    />
    <text
      :x="x + (direccion === 'izquierda' ? 20 : -30)"
      :y="y + 10"
      font-size="28"
      fill="#333"
      font-weight="bold"
    >{{ numero }}</text>
  </g>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  x: Number,
  y: Number,
  numero: String,
  direccion: { type: String, default: 'derecha' }
})

const puntos = computed(() => {
  const dx = props.direccion === 'izquierda' ? -40 : 40
  return `${props.x + dx},${props.y - 20} ${props.x},${props.y} ${props.x + dx},${props.y + 20}`
})
</script>
