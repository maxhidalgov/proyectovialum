<template>
  <v-group :x="x" :y="y" :scale="{ x: escalaManilla, y: escalaManilla }">
    <v-shape
      :fill="'rgba(0,0,0,0.25)'"
      stroke="black"
      :strokeWidth="1 / escalaManilla" 
      :sceneFunc="drawHandle"
    />
  </v-group>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  x: Number,
  y: Number,
  escalaManilla: {
    type: Number,
    default: 1
  }
})

const drawHandle = (ctx, shape) => {
  ctx.beginPath()
  ctx.rect(-6, -12, 12, 24)
  ctx.rect(-4, -4, 8, 35)
  ctx.fillStrokeShape(shape)
}
</script>
