<template>
  <VThemeProvider theme="light" with-background>
    <div class="hoja-print pa-4">
      <!-- Barra de acciones (no se captura ni imprime) -->
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
          <VBtn variant="outlined" color="secondary" class="mr-2" prepend-icon="mdi-printer" @click="imprimir">
            Imprimir
          </VBtn>
          <VBtn color="deep-purple" prepend-icon="mdi-download" :loading="generandoPdf" @click="descargarPdf">
            Descargar PDF
          </VBtn>
        </div>
      </div>

      <div v-if="loading" class="text-center py-10">
        <VProgressCircular indeterminate color="deep-purple" />
        <div class="text-caption text-medium-emphasis mt-3">Calculando optimización de barras...</div>
      </div>
      <VAlert v-else-if="error" type="warning" variant="tonal">{{ error }}</VAlert>

      <!-- Contenido capturable (PDF / impresión) -->
      <div v-else-if="data" class="hoja-doc-wrap">
        <div ref="hojaRef" class="hoja-doc">
          <div class="doc-header mb-4 d-flex justify-space-between align-center">
            <div>
              <h2 class="doc-title">Hoja de Cortes</h2>
              <div class="doc-sub">
                {{ data.cotizacion.cliente }} · Winperfil {{ serie }}-{{ numero }} · {{ hoy }}
              </div>
            </div>
            <img v-if="logoData" :src="logoData" alt="Vialum" class="doc-logo" />
          </div>
          <HojaCortesView :data="data" />
        </div>
      </div>
    </div>
  </VThemeProvider>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toCanvas } from 'html-to-image'
import jsPDF from 'jspdf'
import api from '@/axiosInstance'
import HojaCortesView from '@/components/HojaCortesView.vue'

// Sin navbar/footer de la app: documento limpio
definePage({ meta: { layout: 'blank', public: false } })

const route = useRoute()
const data        = ref(null)
const loading     = ref(true)
const error       = ref('')
const generandoPdf = ref(false)
const hojaRef     = ref(null)
const logoData    = ref('')

const serie  = computed(() => route.query.serie || '')
const numero = computed(() => route.query.numero || '')
const hoy    = new Date().toLocaleDateString('es-CL')

async function cargarLogo() {
  try {
    const { data } = await api.get('/api/logo')
    if (data?.logo) logoData.value = data.logo
  } catch {
    /* sin logo, el documento se genera igual */
  }
}

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

async function descargarPdf() {
  if (!hojaRef.value) return
  generandoPdf.value = true
  try {
    const node = hojaRef.value
    const canvas = await toCanvas(node, {
      backgroundColor: '#ffffff',
      pixelRatio: 3,
      cacheBust: true,
      width: node.scrollWidth,
      height: node.scrollHeight,
      style: { margin: '0' },
    })

    const pdf = new jsPDF('p', 'mm', 'a4')
    const pageW = 210, pageH = 297, margin = 8
    const imgW = pageW - margin * 2
    const imgH = canvas.height * imgW / canvas.width
    const imgData = canvas.toDataURL('image/jpeg', 0.92)
    const pageContentH = pageH - margin * 2

    let heightLeft = imgH
    let position = margin
    pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH)
    heightLeft -= pageContentH

    while (heightLeft > 0) {
      position = margin - (imgH - heightLeft)
      pdf.addPage()
      pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH)
      heightLeft -= pageContentH
    }

    pdf.save(`Pauta_Corte_${serie.value}-${numero.value || 'winperfil'}.pdf`)
  } catch (e) {
    console.error('descargarPdf', e)
    alert('No se pudo generar el PDF. Prueba con "Imprimir" → Guardar como PDF.')
  } finally {
    generandoPdf.value = false
  }
}

onMounted(() => {
  cargar()
  cargarLogo()
})
</script>

<style scoped>
.hoja-print { background: #fff; min-height: 100vh; }
/* Wrapper solo centra en pantalla; NO se captura */
.hoja-doc-wrap { display: flex; justify-content: center; }
/* Ancho fijo tipo A4 y SIN margin auto: la captura sale 1:1, centrada y sin recortes */
.hoja-doc { width: 760px; }
.doc-header { border-bottom: 2px solid #6a1b9a; padding-bottom: 8px; }
.doc-title { margin: 0; font-size: 22px; font-weight: 800; color: #6a1b9a; }
.doc-sub { font-size: 14px; color: #555; }
.doc-logo { height: 52px; object-fit: contain; }
</style>

<style>
@media print {
  .d-print-none { display: none !important; }
  @page { margin: 10mm; }
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .v-table__wrapper { overflow: visible !important; }
  .barra-wrap, .cortes-tabla { break-inside: avoid; }
}
</style>
