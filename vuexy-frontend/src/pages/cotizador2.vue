<template>
  <v-container>
    <v-row>
      <!-- Formulario -->
      <v-col cols="12" md="6">
        <v-card class="pa-4">
          <v-card-title class="text-h6">Configuración de la ventana</v-card-title>
          <v-card-text>
            <v-text-field v-model="ancho" label="Ancho (mm)" type="number" />
            <v-text-field v-model="alto" label="Alto (mm)" type="number" />

            <v-select
              v-model="tipoVidrio"
              label="Tipo de vidrio"
              :items="['TP-B 4+10+4', 'Monolítico 5mm', 'TP-C 4+6+4']"
            />

            <v-select
              v-model="tipoApertura"
              label="Tipo de apertura"
              :items="[
                { label: 'Corredera (izq-der)', value: { izq: '→', der: '←' } },
                { label: 'Proyectante (ambas)', value: { izq: '\u2191', der: '\u2191' } },
                { label: 'Fija', value: { izq: '', der: '' } }
              ]"
              item-title="label"
              item-value="value"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Vista previa -->
      <v-col cols="12" md="6">
        <VentanaPreview
          :ancho="ancho"
          :alto="alto"
          :tipo-vidrio="tipoVidrio"
          :apertura-izquierda="tipoApertura.izq"
          :apertura-derecha="tipoApertura.der"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import VentanaPreview from './ventanapreview.vue' // Ajusta la ruta según tu proyecto

const ancho = ref(2000)
const alto = ref(2000)
const tipoVidrio = ref('TP-B 4+10+4')
const tipoApertura = ref({ izq: '→', der: '←' })
</script>

<style scoped>
</style>
