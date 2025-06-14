<template>
  <v-container>
    <v-row>
      <v-col cols="12" md="4">
        <v-text-field v-model.number="ancho" label="Ancho (mm)" type="number" />
        <v-text-field v-model.number="alto" label="Alto (mm)" type="number" />
        <v-btn class="mt-2" @click="forzarRecarga">Actualizar Modelo</v-btn>
      </v-col>

      <v-col cols="12" md="8">
        <Visor3D
          :key="componenteKey"
          :ancho="ancho"
          :alto="alto"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
//import Visor3D from '@/layouts/components/visor3d.vue'

const ancho = ref(1000)
const alto = ref(1200)
const componenteKey = ref(0)

const forzarRecarga = () => {
  componenteKey.value++
}
</script>
