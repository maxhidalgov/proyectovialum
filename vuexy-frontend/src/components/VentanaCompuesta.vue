<template>
  <g>
    <!-- Parte superior -->
    <VentanaSimple
      :x="x"
      :y="y"
      :ancho="ancho"
      :alto="num(parteSuperior?.alto)"
      :escala="escala"
      :color-marco="colorMarco"
      :tipo="parteSuperior?.tipo"
      :lado-apertura="ladoAperturaSuperior"
      :direccion-apertura="direccionAperturaSuperior"
    />
    <!-- Parte inferior -->
    <VentanaSimple
      :x="x"
      :y="y + num(parteSuperior?.alto) * escala"
      :ancho="ancho"
      :alto="num(parteInferior?.alto)"
      :escala="escala"
      :color-marco="colorMarco"
      :tipo="parteInferior?.tipo"
      :lado-apertura="ladoAperturaInferior"
      :direccion-apertura="direccionAperturaInferior"
    />
  </g>
</template>

<script setup>
import VentanaSimple from './VentanaSimple.vue'

const props = defineProps({
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  ancho: { type: Number, required: true },
  alto: { type: Number, required: true },
  escala: { type: Number, required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  parteSuperior: { type: Object, default: () => ({ alto: 0, tipo: 'Fija' }) },
  parteInferior: { type: Object, default: () => ({ alto: 0, tipo: 'Fija' }) },
  direccionAperturaSuperior: { type: String, default: 'interior' },
  direccionAperturaInferior: { type: String, default: 'interior' },
  ladoAperturaSuperior: { type: String, default: 'izquierda' },
  ladoAperturaInferior: { type: String, default: 'izquierda' }
})

const num = (v) => Number(v) || 0
</script>
