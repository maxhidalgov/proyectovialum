<template>
  <div>
    <!-- Header -->
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-btn icon="mdi-arrow-left" variant="text" :to="{ name: 'produccion' }" />
          <div>
            <h1 class="text-h5 font-weight-bold">Hoja de Cortes</h1>
            <div v-if="data" class="text-body-2 text-medium-emphasis">
              Cotización #{{ data.cotizacion.id }} — {{ data.cotizacion.cliente }} —
              {{ data.cotizacion.fecha }}
            </div>
          </div>
        </div>
      </v-col>
      <v-col cols="auto" class="d-print-none">
        <v-btn
          prepend-icon="mdi-refresh"
          variant="tonal"
          color="secondary"
          class="mr-2"
          :loading="loading"
          @click="fetchData"
        >
          Actualizar
        </v-btn>
        <v-btn prepend-icon="mdi-printer" variant="outlined" @click="imprimir">
          Imprimir
        </v-btn>
      </v-col>
    </v-row>

    <v-skeleton-loader v-if="loading" type="article, table, article, table" />

    <v-alert v-else-if="error" type="error" rounded="lg">{{ error }}</v-alert>

    <v-alert v-else-if="data && data.grupos.length === 0" type="info" rounded="lg">
      Esta cotización no tiene ventanas con perfiles configurados.
    </v-alert>

    <!-- Componente compartido -->
    <HojaCortesView v-else-if="data" :data="data" />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/axiosInstance'
import HojaCortesView from '@/components/HojaCortesView.vue'

const route = useRoute()

const data    = ref(null)
const loading = ref(true)
const error   = ref(null)

function imprimir() {
  window.print()
}

async function fetchData() {
  loading.value = true
  error.value   = null
  data.value    = null

  try {
    const res = await api.get(`/api/cotizaciones/${route.params.id}/hoja-cortes?_t=${Date.now()}`)
    data.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar la hoja de cortes.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
watch(() => route.params.id, fetchData)
</script>

<style scoped>
@media print {
  .d-print-none { display: none !important; }
}
</style>
