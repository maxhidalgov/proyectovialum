<template>
  <div class="ia-produccion-page">
    <!-- Header -->
    <VRow class="mb-3" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold d-flex align-center gap-2">
          <VIcon color="primary" size="28">mdi-robot</VIcon>
          Asistente de Producción
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Reporta avances, ausencias e incidentes en lenguaje natural
        </p>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2">
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="mdi-refresh"
          size="small"
          :loading="cargandoContexto"
          @click="cargarContexto"
        >
          Actualizar
        </VBtn>
      </VCol>
    </VRow>

    <VRow>
      <!-- Panel izquierdo: resumen del día -->
      <VCol cols="12" md="3">
        <!-- Incidentes abiertos -->
        <VCard class="mb-3" variant="outlined">
          <VCardTitle class="text-body-1 font-weight-medium pa-3 pb-1">
            <VIcon size="16" color="error" class="mr-1">mdi-alert-circle</VIcon>
            Incidentes abiertos
          </VCardTitle>
          <VCardText class="pa-2">
            <div v-if="cargandoContexto" class="text-center py-2">
              <VProgressCircular size="20" indeterminate color="primary" />
            </div>
            <div v-else-if="contexto.incidentes_abiertos?.length === 0" class="text-body-2 text-medium-emphasis pa-1">
              Sin incidentes 🎉
            </div>
            <div
              v-for="inc in contexto.incidentes_abiertos"
              :key="inc.id"
              class="incident-item pa-2 rounded mb-1"
            >
              <div class="d-flex align-center gap-1 mb-1">
                <VChip :color="colorIncidente(inc.tipo)" size="x-small" label>{{ inc.tipo }}</VChip>
                <VChip :color="inc.estado === 'abierto' ? 'error' : 'warning'" size="x-small" variant="tonal">
                  {{ inc.estado }}
                </VChip>
              </div>
              <p class="text-body-2 mb-0 text-truncate-2">{{ inc.descripcion }}</p>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ inc.cotizacion?.cliente?.nombre || 'General' }}
              </p>
            </div>
          </VCardText>
        </VCard>

        <!-- Etapas en progreso -->
        <VCard variant="outlined">
          <VCardTitle class="text-body-1 font-weight-medium pa-3 pb-1">
            <VIcon size="16" color="warning" class="mr-1">mdi-hammer-wrench</VIcon>
            En producción
          </VCardTitle>
          <VCardText class="pa-2">
            <div v-if="cargandoContexto" class="text-center py-2">
              <VProgressCircular size="20" indeterminate color="primary" />
            </div>
            <div v-else-if="contexto.etapas_en_progreso?.length === 0" class="text-body-2 text-medium-emphasis pa-1">
              Sin etapas activas
            </div>
            <div
              v-for="etapa in contexto.etapas_en_progreso"
              :key="etapa.id"
              class="pa-2 rounded mb-1 bg-grey-lighten-5"
            >
              <div class="d-flex justify-space-between align-center">
                <span class="text-body-2 font-weight-medium">{{ formatEtapa(etapa.etapa) }}</span>
                <VChip color="warning" size="x-small" variant="tonal">activo</VChip>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ etapa.cotizacion?.cliente?.nombre || `Cotiz. #${etapa.cotizacion_id}` }}
              </p>
              <p v-if="etapa.empleado" class="text-caption mb-0">
                <VIcon size="10">mdi-account</VIcon> {{ etapa.empleado.nombre }}
              </p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel central: chat -->
      <VCol cols="12" md="9">
        <VCard style="height: calc(100vh - 180px); display: flex; flex-direction: column;">
          <!-- Mensajes -->
          <div
            ref="mensajesRef"
            class="mensajes-container flex-grow-1 overflow-y-auto pa-4"
          >
            <!-- Estado vacío -->
            <div v-if="mensajes.length === 0" class="empty-state text-center py-10">
              <VIcon size="64" color="primary" class="mb-3">mdi-robot-outline</VIcon>
              <h6 class="text-h6 mb-2">¿Qué pasó hoy?</h6>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Cuéntame en lenguaje natural. Algunos ejemplos:
              </p>
              <div class="sugerencias d-flex flex-wrap gap-2 justify-center">
                <VChip
                  v-for="sug in sugerencias"
                  :key="sug"
                  variant="tonal"
                  color="primary"
                  size="small"
                  class="cursor-pointer"
                  @click="usarSugerencia(sug)"
                >
                  {{ sug }}
                </VChip>
              </div>
            </div>

            <!-- Mensajes del chat -->
            <div v-for="msg in mensajes" :key="msg.id || msg._tempId" class="mensaje-row mb-4">
              <!-- Mensaje del usuario -->
              <div v-if="msg.rol === 'user'" class="d-flex justify-end">
                <div class="mensaje usuario-msg pa-3 rounded-lg">
                  <p class="mb-0 text-body-2">{{ msg.contenido }}</p>
                  <span class="text-caption text-medium-emphasis">
                    {{ formatHora(msg.created_at) }}
                  </span>
                </div>
              </div>

              <!-- Respuesta del asistente -->
              <div v-else class="d-flex align-start gap-2">
                <VAvatar color="primary" size="32" class="mt-1 flex-shrink-0">
                  <VIcon size="18">mdi-robot</VIcon>
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="mensaje asistente-msg pa-3 rounded-lg">
                    <div class="text-body-2 ia-response" v-html="renderMarkdown(msg.contenido)" />
                    <span class="text-caption text-medium-emphasis">
                      {{ formatHora(msg.created_at) }}
                    </span>
                  </div>
                  <!-- Acciones ejecutadas -->
                  <div v-if="msg.acciones_ejecutadas?.length" class="acciones-chips mt-1 d-flex flex-wrap gap-1">
                    <VChip
                      v-for="(accion, i) in msg.acciones_ejecutadas"
                      :key="i"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      prepend-icon="mdi-check"
                    >
                      {{ formatTool(accion.tool) }}
                    </VChip>
                  </div>
                </div>
              </div>
            </div>

            <!-- Indicador de carga -->
            <div v-if="enviando" class="d-flex align-start gap-2 mb-4">
              <VAvatar color="primary" size="32" class="mt-1">
                <VIcon size="18">mdi-robot</VIcon>
              </VAvatar>
              <div class="mensaje asistente-msg pa-3 rounded-lg">
                <div class="typing-indicator d-flex gap-1 align-center py-1">
                  <span class="dot" />
                  <span class="dot" />
                  <span class="dot" />
                </div>
              </div>
            </div>
          </div>

          <VDivider />

          <!-- Input -->
          <div class="pa-3">
            <VTextField
              v-model="inputMensaje"
              placeholder="Escribe aquí... (ej: 'hoy no vino Pedro', 'se quebró un termopanel en la obra Rivas')"
              variant="outlined"
              density="comfortable"
              hide-details
              :disabled="enviando"
              @keydown.enter.prevent="enviarMensaje"
              @keydown.shift.enter.prevent="inputMensaje += '\n'"
            >
              <template #append-inner>
                <VBtn
                  icon
                  variant="text"
                  color="primary"
                  :loading="enviando"
                  :disabled="!inputMensaje.trim()"
                  @click="enviarMensaje"
                >
                  <VIcon>mdi-send</VIcon>
                </VBtn>
              </template>
            </VTextField>
            <p class="text-caption text-medium-emphasis mt-1 mb-0">
              Enter para enviar · Shift+Enter para nueva línea
            </p>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import axios from '@/axiosInstance'
const api = axios

// ── Estado ────────────────────────────────────────────────────────────────────

const mensajes        = ref([])
const inputMensaje    = ref('')
const enviando        = ref(false)
const mensajesRef     = ref(null)
const contexto        = ref({ incidentes_abiertos: [], etapas_en_progreso: [] })
const cargandoContexto = ref(false)

const sugerencias = [
  'Hoy no vino Juan',
  '¿Qué cotizaciones están en producción?',
  'Se quebró un termopanel en la obra Rivas',
  'Pedro terminó el corte de la obra López',
  'Mañana van Pedro y Javier a medir donde Martínez',
  '¿Cuándo podemos entregar la cotización #81?',
]

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([cargarHistorial(), cargarContexto()])
})

// ── API ───────────────────────────────────────────────────────────────────────

async function cargarHistorial() {
  try {
    const { data } = await api.get('/api/ia/historial', { params: { limit: 40 } })
    mensajes.value = data
    await scrollAbajo()
  } catch (e) {
    console.error('Error cargando historial:', e)
  }
}

async function cargarContexto() {
  cargandoContexto.value = true
  try {
    const { data } = await api.get('/api/ia/contexto')
    contexto.value = data
  } catch (e) {
    console.error('Error cargando contexto:', e)
  } finally {
    cargandoContexto.value = false
  }
}

async function enviarMensaje() {
  const texto = inputMensaje.value.trim()
  if (!texto || enviando.value) return

  // Agrego el mensaje del usuario optimistamente
  const msgUsuario = {
    _tempId: Date.now(),
    rol: 'user',
    contenido: texto,
    created_at: new Date().toISOString(),
  }
  mensajes.value.push(msgUsuario)
  inputMensaje.value = ''
  enviando.value = true
  await scrollAbajo()

  try {
    const { data } = await api.post('/api/ia/chat', { mensaje: texto })

    mensajes.value.push({
      _tempId: Date.now() + 1,
      rol: 'assistant',
      contenido: data.respuesta,
      acciones_ejecutadas: data.acciones_ejecutadas,
      created_at: new Date().toISOString(),
    })

    // Si hubo acciones, actualizar el panel lateral
    if (data.acciones_ejecutadas?.length) {
      cargarContexto()
    }
  } catch (e) {
    mensajes.value.push({
      _tempId: Date.now() + 1,
      rol: 'assistant',
      contenido: 'Error al conectar con el asistente. Intenta de nuevo.',
      created_at: new Date().toISOString(),
    })
  } finally {
    enviando.value = false
    await scrollAbajo()
  }
}

function usarSugerencia(texto) {
  inputMensaje.value = texto
  enviarMensaje()
}

// ── Helpers ───────────────────────────────────────────────────────────────────

async function scrollAbajo() {
  await nextTick()
  if (mensajesRef.value) {
    mensajesRef.value.scrollTop = mensajesRef.value.scrollHeight
  }
}

function formatHora(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
}

function formatEtapa(etapa) {
  const map = {
    corte_perfiles: 'Corte perfiles',
    corte_vidrio: 'Corte vidrio',
    fabricacion_termopanel: 'Fab. termopanel',
    armado: 'Armado',
    vidriado: 'Vidriado',
    junquillos: 'Junquillos',
    control: 'Control',
    instalacion: 'Instalación',
    entrega: 'Entrega',
  }
  return map[etapa] || etapa
}

function formatTool(tool) {
  const map = {
    registrar_ausencia: 'Ausencia registrada',
    registrar_horas_extra: 'Horas extra',
    registrar_incidente: 'Incidente registrado',
    registrar_visita: 'Visita agendada',
    actualizar_etapa: 'Etapa actualizada',
    get_contexto_produccion: 'Contexto leído',
    estimar_fecha_entrega: 'Fecha estimada',
  }
  return map[tool] || tool
}

function colorIncidente(tipo) {
  const map = {
    rotura_vidrio: 'error',
    retraso: 'warning',
    material_faltante: 'orange',
    instalacion: 'info',
    otro: 'default',
  }
  return map[tipo] || 'default'
}

function renderMarkdown(texto) {
  if (!texto) return ''
  return texto
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/^### (.+)$/gm, '<h6 class="text-subtitle-2 font-weight-bold mt-2 mb-1">$1</h6>')
    .replace(/^## (.+)$/gm, '<h5 class="text-subtitle-1 font-weight-bold mt-3 mb-1">$1</h5>')
    .replace(/^- (.+)$/gm, '<li class="ml-3">$1</li>')
    .replace(/(<li.*<\/li>\n?)+/g, '<ul class="mb-2">$&</ul>')
    .replace(/\n/g, '<br>')
    .replace(/\|(.+)\|/g, (match) => {
      // Tabla markdown básica
      const cells = match.split('|').filter(c => c.trim()).map(c => `<td class="px-2 py-1">${c.trim()}</td>`)
      return `<tr>${cells.join('')}</tr>`
    })
}
</script>

<style scoped>
.ia-produccion-page {
  height: 100%;
}

.mensajes-container {
  padding-block: 16px;
}

.mensaje {
  max-width: 85%;
  word-break: break-word;
}

.usuario-msg {
  background-color: rgb(var(--v-theme-primary));
  color: white;
  border-radius: 18px 18px 4px 18px !important;
}

.asistente-msg {
  background-color: rgb(var(--v-theme-surface-variant), 0.15);
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 4px 18px 18px 18px !important;
}

.ia-response :deep(strong) {
  font-weight: 600;
}

.ia-response :deep(ul) {
  padding-left: 8px;
  margin-bottom: 6px;
}

.ia-response :deep(li) {
  margin-bottom: 2px;
}

.ia-response :deep(table) {
  border-collapse: collapse;
  font-size: 12px;
  margin: 8px 0;
}

.ia-response :deep(td) {
  border: 1px solid rgba(var(--v-border-color), 0.2);
}

.ia-response :deep(tr:first-child td) {
  font-weight: 600;
  background: rgba(var(--v-theme-primary), 0.05);
}

.incident-item {
  background: rgba(var(--v-theme-error), 0.04);
  border: 1px solid rgba(var(--v-theme-error), 0.12);
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Typing indicator */
.typing-indicator {
  padding: 4px 2px;
}

.dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  animation: bounce 1.2s infinite;
  display: inline-block;
}

.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
  40% { transform: translateY(-6px); opacity: 1; }
}
</style>
