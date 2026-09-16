<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
      <div>
        <h2 class="text-h5 font-weight-bold mb-1">Seguimiento de cotizaciones</h2>
        <span class="text-body-2 text-medium-emphasis">Cotizaciones enviadas pendientes de respuesta</span>
      </div>
      <VBtn variant="tonal" size="small" :loading="loading" prepend-icon="mdi-refresh" @click="cargar">
        Actualizar
      </VBtn>
    </div>

    <!-- Métricas -->
    <VRow class="mb-2">
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="primary">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Pendientes</div>
            <div class="text-h5 font-weight-bold">{{ metricas.pendientes ?? 0 }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="error">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Sin respuesta +7 días</div>
            <div class="text-h5 font-weight-bold">{{ metricas.sin_respuesta_7d ?? 0 }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="success">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Tasa de conversión</div>
            <div class="text-h5 font-weight-bold">
              {{ metricas.tasa_conversion != null ? metricas.tasa_conversion + '%' : '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ metricas.aprobadas ?? 0 }} aprob. / {{ metricas.rechazadas ?? 0 }} rech.
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal">
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">Total enviadas</div>
            <div class="text-h5 font-weight-bold">{{ metricas.total_enviadas ?? 0 }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VDataTable
        :headers="headers"
        :items="cotizaciones"
        :loading="loading"
        :items-per-page="25"
        density="comfortable"
        no-data-text="No hay cotizaciones enviadas pendientes de respuesta"
        class="text-no-wrap"
      >
        <template #item.semaforo="{ item }">
          <VTooltip location="top" :text="tooltipSemaforo(item)">
            <template #activator="{ props }">
              <VIcon v-bind="props" :color="colorSemaforo(item.semaforo)" icon="mdi-circle" size="16" />
            </template>
          </VTooltip>
        </template>

        <template #item.cliente="{ item }">
          <div class="font-weight-medium">{{ item.cliente }}</div>
          <div class="text-caption text-medium-emphasis">
            #{{ item.id }}<template v-if="item.vendedor"> · {{ item.vendedor }}</template>
          </div>
        </template>

        <template #item.total_bruto="{ item }">
          {{ clp(item.total_bruto) }}
        </template>

        <template #item.dias_desde_envio="{ item }">
          <VChip :color="colorSemaforo(item.semaforo)" size="small" variant="tonal">
            {{ item.dias_desde_envio === 0 ? 'Hoy' : item.dias_desde_envio + ' d' }}
          </VChip>
          <div class="text-caption text-medium-emphasis mt-1">
            <VIcon size="12" :icon="item.enviado_via === 'email' ? 'mdi-email-outline' : 'mdi-whatsapp'" />
            {{ fecha(item.enviado_at) }}
          </div>
        </template>

        <template #item.proximo_recordatorio="{ item }">
          <span v-if="item.proximo_recordatorio">{{ fecha(item.proximo_recordatorio) }}</span>
          <span v-else class="text-disabled">—</span>
        </template>

        <template #item.acciones="{ item }">
          <div class="d-flex gap-1 justify-end">
            <VTooltip text="Aprobada" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="success"
                  :loading="accionId === item.id" @click="marcarEstado(item, 'Aprobada')">
                  <VIcon icon="mdi-check-circle" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Rechazada" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="error"
                  :loading="accionId === item.id" @click="marcarEstado(item, 'Rechazada')">
                  <VIcon icon="mdi-close-circle" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Reenviar por WhatsApp" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="green"
                  :loading="accionId === item.id" @click="reenviar(item)">
                  <VIcon icon="mdi-whatsapp" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Ver PDF" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text"
                  :href="pdfUrl(item.id)" target="_blank">
                  <VIcon icon="mdi-file-pdf-box" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VSnackbar v-model="snack.show" :color="snack.color" location="top">
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/axiosInstance'
import { asegurarGraficosCotizacion } from '@/composables/useSvgToPng'

const loading = ref(false)
const accionId = ref(null)
const cotizaciones = ref([])
const metricas = ref({})
const snack = ref({ show: false, text: '', color: 'success' })

const headers = [
  { title: '', key: 'semaforo', sortable: false, width: 40 },
  { title: 'Cliente', key: 'cliente' },
  { title: 'Monto', key: 'total_bruto', align: 'end' },
  { title: 'Enviada', key: 'dias_desde_envio' },
  { title: 'Próximo recordatorio', key: 'proximo_recordatorio' },
  { title: '', key: 'acciones', sortable: false, align: 'end' },
]

function notify(text, color = 'success') {
  snack.value = { show: true, text, color }
}

function clp(n) {
  return n == null ? '—' : '$' + Math.round(n).toLocaleString('es-CL')
}
function fecha(f) {
  if (!f) return '—'
  const d = new Date(f)
  return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
function colorSemaforo(s) {
  return { verde: 'success', amarillo: 'warning', rojo: 'error' }[s] || 'grey'
}
function tooltipSemaforo(item) {
  const d = item.dias_desde_envio
  if (d == null) return 'Enviada'
  if (d < 3) return `Enviada hace ${d} día(s) — reciente`
  if (d <= 7) return `Sin respuesta hace ${d} días — hacer seguimiento`
  return `Sin respuesta hace ${d} días — urgente`
}
function pdfUrl(id) {
  return `${window.location.origin}/cotizaciones/${id}/pdf`
}

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/cotizaciones/seguimiento')
    cotizaciones.value = data.cotizaciones || []
    metricas.value = data.metricas || {}
  } catch (e) {
    notify('No se pudo cargar el seguimiento', 'error')
  } finally {
    loading.value = false
  }
}

async function marcarEstado(item, estado) {
  accionId.value = item.id
  try {
    await api.patch(`/api/cotizaciones/${item.id}/estado`, { estado })
    cotizaciones.value = cotizaciones.value.filter(c => c.id !== item.id)
    metricas.value.pendientes = Math.max(0, (metricas.value.pendientes || 1) - 1)
    if (estado === 'Aprobada') metricas.value.aprobadas = (metricas.value.aprobadas || 0) + 1
    if (estado === 'Rechazada') metricas.value.rechazadas = (metricas.value.rechazadas || 0) + 1
    notify(`Cotización #${item.id} marcada como ${estado}`)
  } catch (e) {
    notify(e.response?.data?.message || 'No se pudo cambiar el estado', 'error')
  } finally {
    accionId.value = null
  }
}

async function reenviar(item) {
  accionId.value = item.id
  try {
    await asegurarGraficosCotizacion(api, item.id)
    const { data } = await api.post(`/api/cotizaciones/${item.id}/enviar`, { via: 'whatsapp' })
    if (data.wa_url) window.open(data.wa_url, '_blank')
    notify(`Reenviada la cotización #${item.id}`)
    cargar()
  } catch (e) {
    notify(e.response?.data?.message || 'No se pudo reenviar', 'error')
  } finally {
    accionId.value = null
  }
}

onMounted(cargar)
</script>
