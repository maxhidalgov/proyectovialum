<!-- ManillaImagen.vue - manilla basada en una imagen PNG/SVG -->
<template>
  <image
    :x="x"
    :y="y"
    :width="ancho"
    :height="alto"
    :href="manillaSrc"
  />
</template>

<script setup>
import manillaSrc from '@/assets/images/manilla-blanca.png' // Ruta a la imagen de la manilla

const props = defineProps({
  x: Number,
  y: Number,
  ancho: { type: Number, default: 30 },
  alto: { type: Number, default: 80 }
})
</script>
