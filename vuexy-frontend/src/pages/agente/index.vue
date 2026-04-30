<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/axiosInstance'

definePage({ meta: { layout: 'default' } })

const router = useRouter()
const messages = ref([])          // historial completo para la API
const displayMessages = ref([])   // solo mensajes visibles (user + assistant text)
const input = ref('')
const loading = ref(false)
const cotizacionCreada = ref(null)
const messagesContainer = ref(null)
const inputRef = ref(null)

const welcomeMessage = `¡Hola! Soy el asistente cotizador de Vialum. 👋

Puedo crear cotizaciones a partir de una descripción. Por ejemplo:

_"2 ventanas correderas AL25 de 1200×1000 en blanco con vidrio monolítico para Juan González"_

¿Con qué cotización te ayudo hoy?`

onMounted(() => {
  displayMessages.value.push({ role: 'assistant', text: welcomeMessage })
})

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const send = async () => {
  const text = input.value.trim()
  if (!text || loading.value) return

  input.value = ''
  cotizacionCreada.value = null

  // Agregar mensaje del usuario a la UI
  displayMessages.value.push({ role: 'user', text })
  scrollToBottom()

  // Agregar al historial de la API
  messages.value.push({ role: 'user', content: text })

  loading.value = true
  try {
    const { data } = await api.post('/api/agente/cotizar', {
      messages: messages.value,
    })

    // El backend ya agrega la respuesta al historial — usar el historial devuelto
    messages.value = data.messages

    displayMessages.value.push({ role: 'assistant', text: data.message })

    if (data.cotizacion_creada) {
      cotizacionCreada.value = data.cotizacion_creada
    }
  } catch (err) {
    displayMessages.value.push({
      role: 'error',
      text: 'Error al conectar con el agente. Intenta nuevamente.',
    })
  } finally {
    loading.value = false
    scrollToBottom()
    nextTick(() => inputRef.value?.focus())
  }
}

const nuevaCotizacion = () => {
  messages.value = []
  displayMessages.value = [{ role: 'assistant', text: welcomeMessage }]
  cotizacionCreada.value = null
  input.value = ''
}

// Convertir markdown básico a HTML seguro
const formatText = (text) => {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}
</script>

<template>
  <VRow>
    <VCol cols="12" md="8" offset-md="2">
      <VCard class="d-flex flex-column" style="height: calc(100vh - 120px);">

        <!-- Header -->
        <VCardTitle class="d-flex align-center gap-2 pa-4 border-b">
          <VIcon icon="tabler-robot" color="primary" size="26" />
          <span>Agente Cotizador</span>
          <VSpacer />
          <VBtn variant="text" size="small" @click="nuevaCotizacion" title="Nueva conversación">
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </VCardTitle>

        <!-- Mensajes -->
        <VCardText
          ref="messagesContainer"
          class="flex-grow-1 overflow-y-auto pa-4"
          style="scroll-behavior: smooth;"
        >
          <div v-for="(msg, i) in displayMessages" :key="i" class="mb-4">

            <!-- Mensaje usuario -->
            <div v-if="msg.role === 'user'" class="d-flex justify-end">
              <VCard
                color="primary"
                class="pa-3 text-body-2"
                style="max-width: 75%; border-radius: 16px 16px 4px 16px;"
              >
                <span style="color: white;" v-html="formatText(msg.text)" />
              </VCard>
            </div>

            <!-- Mensaje asistente -->
            <div v-else-if="msg.role === 'assistant'" class="d-flex align-start gap-2">
              <VAvatar color="primary" variant="tonal" size="32">
                <VIcon icon="tabler-robot" size="18" />
              </VAvatar>
              <VCard
                variant="tonal"
                class="pa-3 text-body-2"
                style="max-width: 80%; border-radius: 4px 16px 16px 16px;"
              >
                <span v-html="formatText(msg.text)" />
              </VCard>
            </div>

            <!-- Error -->
            <div v-else-if="msg.role === 'error'" class="d-flex align-start gap-2">
              <VAvatar color="error" variant="tonal" size="32">
                <VIcon icon="tabler-alert-circle" size="18" />
              </VAvatar>
              <VCard
                color="error"
                variant="tonal"
                class="pa-3 text-body-2"
                style="max-width: 80%; border-radius: 4px 16px 16px 16px;"
              >
                {{ msg.text }}
              </VCard>
            </div>

          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="d-flex align-start gap-2 mb-4">
            <VAvatar color="primary" variant="tonal" size="32">
              <VIcon icon="tabler-robot" size="18" />
            </VAvatar>
            <VCard variant="tonal" class="pa-3" style="border-radius: 4px 16px 16px 16px;">
              <div class="d-flex gap-1 align-center" style="height: 20px;">
                <span class="typing-dot" />
                <span class="typing-dot" style="animation-delay: 0.2s;" />
                <span class="typing-dot" style="animation-delay: 0.4s;" />
              </div>
            </VCard>
          </div>

          <!-- Cotización creada -->
          <VAlert
            v-if="cotizacionCreada"
            type="success"
            variant="tonal"
            class="mt-2"
            prominent
          >
            <template #title>¡Cotización #{{ cotizacionCreada }} creada!</template>
            <div class="d-flex gap-2 mt-2">
              <VBtn size="small" color="success" @click="router.push(`/cotizaciones`)">
                Ver cotizaciones
              </VBtn>
              <VBtn size="small" variant="outlined" color="success" @click="nuevaCotizacion">
                Nueva cotización
              </VBtn>
            </div>
          </VAlert>
        </VCardText>

        <VDivider />

        <!-- Input -->
        <VCardActions class="pa-3 gap-2">
          <VTextField
            ref="inputRef"
            v-model="input"
            placeholder="Describe la cotización..."
            variant="outlined"
            density="compact"
            hide-details
            :disabled="loading"
            @keyup.enter="send"
          />
          <VBtn
            color="primary"
            icon
            :loading="loading"
            :disabled="!input.trim()"
            @click="send"
          >
            <VIcon icon="tabler-send" />
          </VBtn>
        </VCardActions>

      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.typing-dot {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.5;
  animation: typing-bounce 0.8s infinite ease-in-out;
}

@keyframes typing-bounce {
  0%, 80%, 100% { transform: translateY(0); }
  40%            { transform: translateY(-6px); }
}
</style>
