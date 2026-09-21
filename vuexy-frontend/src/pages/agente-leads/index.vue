<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
      <div>
        <h2 class="text-h5 font-weight-bold mb-1">Agente de Leads</h2>
        <span class="text-body-2 text-medium-emphasis">
          Recepcionista virtual que atiende consultas nuevas, califica y te pasa el lead
        </span>
      </div>
      <VChip color="warning" variant="tonal" size="small" prepend-icon="mdi-flask-outline">
        Simulador de prueba
      </VChip>
    </div>

    <VRow>
      <!-- Simulador de chat -->
      <VCol cols="12" md="6">
        <VCard class="d-flex flex-column" style="height: 70vh;">
          <VCardTitle class="d-flex align-center gap-2 py-3">
            <VIcon color="green" icon="mdi-whatsapp" />
            <span class="text-body-1 font-weight-bold">Conversación de prueba</span>
            <VSpacer />
            <VBtn size="x-small" variant="text" prepend-icon="mdi-restart" @click="reiniciar">Reiniciar</VBtn>
          </VCardTitle>
          <VDivider />

          <div ref="scroller" class="flex-grow-1 pa-3" style="overflow-y: auto; background: rgba(0,0,0,0.02);">
            <div v-for="(m, i) in mensajes" :key="i" class="d-flex mb-2"
              :class="m.rol === 'user' ? 'justify-end' : 'justify-start'">
              <div class="px-3 py-2 rounded-lg" :style="burbuja(m.rol)" style="max-width: 78%; white-space: pre-wrap; line-height: 1.4;">
                {{ m.texto }}
              </div>
            </div>
            <div v-if="pensando" class="d-flex justify-start mb-2">
              <div class="px-3 py-2 rounded-lg" :style="burbuja('bot')">
                <VProgressCircular indeterminate size="16" width="2" /> escribiendo…
              </div>
            </div>
          </div>

          <VDivider />
          <div class="pa-2 d-flex gap-2">
            <VTextField v-model="entrada" placeholder="Escribe como si fueras el cliente…"
              density="compact" variant="outlined" hide-details autofocus
              :disabled="pensando" @keyup.enter="enviar" />
            <VBtn color="primary" icon="mdi-send" :loading="pensando" :disabled="!entrada.trim()" @click="enviar" />
          </div>
        </VCard>
      </VCol>

      <!-- Leads capturados -->
      <VCol cols="12" md="6">
        <VCard style="height: 70vh;" class="d-flex flex-column">
          <VCardTitle class="d-flex align-center gap-2 py-3">
            <VIcon color="primary" icon="mdi-account-plus" />
            <span class="text-body-1 font-weight-bold">Leads capturados</span>
            <VChip size="x-small" color="primary" variant="tonal">{{ leads.length }}</VChip>
            <VSpacer />
            <VBtn size="x-small" variant="text" icon="mdi-refresh" @click="cargarLeads" />
          </VCardTitle>
          <VDivider />

          <div class="flex-grow-1 pa-3" style="overflow-y: auto;">
            <div v-if="!leads.length" class="text-center text-medium-emphasis pa-8">
              Aún no hay leads. Prueba una conversación en el simulador.
            </div>
            <VCard v-for="l in leads" :key="l.id" variant="outlined" class="mb-2">
              <VCardText class="py-2">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="font-weight-bold">{{ l.nombre || 'Sin nombre' }}</span>
                  <VChip size="x-small" :color="colorEstado(l.estado)" variant="tonal">{{ l.estado }}</VChip>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-1">
                  <VChip v-if="l.tipo_producto" size="x-small" variant="tonal">{{ l.tipo_producto }}</VChip>
                  <VChip v-if="l.material" size="x-small" variant="tonal">{{ l.material }}</VChip>
                  <VChip v-if="l.tipo_obra" size="x-small" variant="tonal">{{ l.tipo_obra }}</VChip>
                  <VChip v-if="l.comuna" size="x-small" variant="tonal" prepend-icon="mdi-map-marker">{{ l.comuna }}</VChip>
                </div>
                <div v-if="l.detalle" class="text-caption text-medium-emphasis mb-1">{{ l.detalle }}</div>
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption">
                    <VIcon size="12" icon="mdi-phone" /> {{ l.telefono || 's/n' }}
                  </span>
                  <VSelect :model-value="l.estado" :items="estados" density="compact" variant="plain"
                    hide-details style="max-width: 130px;" @update:model-value="v => cambiarEstadoLead(l, v)" />
                </div>
              </VCardText>
            </VCard>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar v-model="snack.show" :color="snack.color" location="top">{{ snack.text }}</VSnackbar>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import api from '@/axiosInstance'

const conversacionId = ref(null)
const mensajes = ref([])
const entrada = ref('')
const pensando = ref(false)
const leads = ref([])
const scroller = ref(null)
const snack = ref({ show: false, text: '', color: 'success' })

const estados = ['nuevo', 'contactado', 'convertido', 'descartado']

function notify(text, color = 'success') { snack.value = { show: true, text, color } }

function burbuja(rol) {
  return rol === 'user'
    ? 'background:#dcf8c6; color:#111;'
    : 'background:#ffffff; color:#111; border:1px solid rgba(0,0,0,0.08);'
}
function colorEstado(e) {
  return { nuevo: 'info', contactado: 'warning', convertido: 'success', descartado: 'error' }[e] || 'grey'
}

async function scrollBottom() {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}

async function reiniciar() {
  mensajes.value = []
  entrada.value = ''
  try {
    const { data } = await api.post('/api/agente-leads/iniciar', { canal: 'simulador' })
    conversacionId.value = data.conversacion_id
    mensajes.value.push({ rol: 'bot', texto: data.saludo })
    scrollBottom()
  } catch (e) {
    notify('No se pudo iniciar la conversación', 'error')
  }
}

async function enviar() {
  const texto = entrada.value.trim()
  if (!texto || pensando.value) return
  entrada.value = ''
  mensajes.value.push({ rol: 'user', texto })
  scrollBottom()
  pensando.value = true
  try {
    const { data } = await api.post('/api/agente-leads/mensaje', {
      conversacion_id: conversacionId.value,
      texto,
    })
    mensajes.value.push({ rol: 'bot', texto: data.texto })
    if (data.lead) {
      notify(`Lead capturado: ${data.lead.nombre || 'sin nombre'}`)
      cargarLeads()
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'No se pudo enviar el mensaje'
    mensajes.value.push({ rol: 'bot', texto: '⚠️ ' + msg })
  } finally {
    pensando.value = false
    scrollBottom()
  }
}

async function cargarLeads() {
  try {
    const { data } = await api.get('/api/agente-leads/leads')
    leads.value = data.leads || []
  } catch (e) { /* silencio */ }
}

async function cambiarEstadoLead(lead, estado) {
  try {
    await api.patch(`/api/agente-leads/leads/${lead.id}`, { estado })
    lead.estado = estado
  } catch (e) {
    notify('No se pudo actualizar el lead', 'error')
  }
}

onMounted(() => {
  reiniciar()
  cargarLeads()
})
</script>
