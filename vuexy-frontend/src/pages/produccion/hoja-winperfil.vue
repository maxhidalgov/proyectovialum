<template>
  <VThemeProvider theme="light" with-background>
    <div class="hoja-print pa-4">
      <div class="d-flex justify-space-between align-center mb-4 d-print-none">
        <div>
          <h1 class="text-h6 font-weight-bold">Hoja de Cortes</h1>
          <div v-if="data" class="text-body-2 text-medium-emphasis">
            {{ data.cotizacion.cliente }} · WP {{ serie }}-{{ numero }}
          </div>
        </div>
        <div>
          <VBtn variant="tonal" color="secondary" class="mr-2" :loading="loading" prepend-icon="mdi-refresh" @click="cargar">
            Actualizar
          </VBtn>
          <VBtn color="deep-purple" prepend-icon="mdi-printer" @click="imprimir">
            Imprimir / PDF
          </VBtn>
        </div>
      </div>

      <!-- Título solo visible al imprimir -->
      <div class="print-only mb-4">
        <h2 style="margin:0; font-size:18px; font-weight:700;">Hoja de Cortes</h2>
        <div v-if="data" style="font-size:13px; color:#555;">
          {{ data.cotizacion.cliente }} · Winperfil {{ serie }}-{{ numero }} · {{ hoy }}
        </div>
      </div>

      <div v-if="loading" class="text-center py-10">
        <VProgressCircular indeterminate color="deep-purple" />
        <div class="text-caption text-medium-emphasis mt-3">Calculando optimización de barras...</div>
      </div>
      <VAlert v-else-if="error" type="warning" variant="tonal">{{ error }}</VAlert>
      <HojaCortesView v-else-if="data" :data="data" />
    </div>
  </VThemeProvider>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/axiosInstance'
import HojaCortesView from '@/components/HojaCortesView.vue'

const route = useRoute()
const data    = ref(null)
const loading = ref(true)
const error   = ref('')

const serie  = computed(() => route.query.serie || '')
const numero = computed(() => route.query.numero || '')
const hoy    = new Date().toLocaleDateString('es-CL')

async function cargar() {
  loading.value = true
  error.value = ''
  try {
    const { data: d } = await api.get('/api/winperfil/hoja-cortes', {
      params: { cotizacion_id: route.query.cotizacion_id },
    })
    data.value = d
  } catch (e) {
    error.value = e.response?.data?.error || 'No se pudo generar la hoja de cortes.'
  } finally {
    loading.value = false
  }
}

function imprimir() {
  window.print()
}

onMounted(cargar)
</script>

<style scoped>
.hoja-print { background: #fff; min-height: 100vh; }
.print-only { display: none; }
</style>

<style>
@media print {
  .d-print-none { display: none !important; }
  .print-only { display: block !important; }
  @page { margin: 10mm; }
  /* que los colores de las piezas se impriman */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
