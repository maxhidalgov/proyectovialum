<script setup>
// Enviar una boleta o factura al cliente con el PDF como ARCHIVO:
//   · Correo · PDF  → llega un correo con el PDF adjunto
//   · WhatsApp · PDF → celular: menú de compartir con el PDF adjunto; computador: descarga el PDF y abre el chat
import { ref, computed, watch } from 'vue'
import api from '@/axiosInstance'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  doc: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'enviado'])

const abierto = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const TIPOS = { 1: 'Boleta', 2: 'Nota de crédito', 5: 'Factura' }
const tipo = computed(() => TIPOS[props.doc?.tipo_documento_bsale_id] || 'Documento')
const folio = computed(() => props.doc?.numero_documento_bsale || '')
const etiqueta = computed(() => `${tipo.value}${folio.value ? ' N° ' + folio.value : ''}`)
const nombreArchivo = computed(() => `${tipo.value}-${folio.value || props.doc?.id}-Vialum.pdf`)

const estado = ref({
  canal: 'email', email: '', telefono: '', mensaje: '', mensajeCorreo: '', mensajeWa: '',
  enviando: false, error: '', aviso: '', pdfArchivo: null, pdfBlob: null, pdfCargando: false,
})

const nombreCliente = () => {
  const n = String(props.doc?.cliente || '').replace(/[\s ]+/g, ' ').trim()
  return !n || n.toLowerCase() === 'consumidor final' ? '' : n
}

watch(() => props.modelValue, abrir => {
  if (!abrir || !props.doc) return
  const d = props.doc
  const nombre = nombreCliente()
  const saludo = nombre ? `Hola ${nombre},` : 'Hola,'
  const mensajeCorreo = `${saludo}\n\nTe enviamos adjunta la ${tipo.value.toLowerCase()}${folio.value ? ' N° ' + folio.value : ''} de Vialum.\n\nMuchas gracias.\n\nSaludos,\nVialum`
  const mensajeWa = `${saludo}\n\nTe envío adjunta la ${tipo.value.toLowerCase()}${folio.value ? ' N° ' + folio.value : ''} de Vialum. ¡Muchas gracias!`
  const canal = d.email_sugerido || !d.telefono_sugerido ? 'email' : 'wapdf'
  estado.value = {
    canal, email: d.email_sugerido || '', telefono: d.telefono_sugerido || '',
    mensaje: canal === 'email' ? mensajeCorreo : mensajeWa, mensajeCorreo, mensajeWa,
    enviando: false, error: '', aviso: '', pdfArchivo: null, pdfBlob: null, pdfCargando: false,
  }
  if (canal === 'wapdf') prepararPdf()
})

function elegirCanal(canal) {
  const e = estado.value
  if (e.canal === canal) return
  if (e.canal === 'email') e.mensajeCorreo = e.mensaje; else e.mensajeWa = e.mensaje
  e.canal = canal
  e.mensaje = canal === 'email' ? e.mensajeCorreo : e.mensajeWa
  e.error = ''
  e.aviso = ''
  if (canal === 'wapdf') prepararPdf()
}

// El PDF se genera ANTES del clic final: el navegador solo deja abrir el menú de compartir con un clic reciente
async function prepararPdf() {
  const e = estado.value
  if (e.pdfArchivo || e.pdfCargando || !props.doc) return
  e.pdfCargando = true
  try {
    const res = await api.get(`/api/ventas/documento/${props.doc.id}/pdf`, { responseType: 'blob' })
    e.pdfBlob = res.data
    e.pdfArchivo = new File([res.data], nombreArchivo.value, { type: 'application/pdf' })
  } catch (err) {
    e.error = 'No se pudo obtener el PDF del documento. Intenta de nuevo.'
  } finally {
    e.pdfCargando = false
  }
}

const telefonoWa = tel => {
  const d = String(tel || '').replace(/\D+/g, '')
  return d.length === 9 && d.startsWith('9') ? `56${d}` : d
}

async function enviarCorreo() {
  const e = estado.value
  if (!e.email) { e.error = 'Ingresa el correo del destinatario'; return }
  e.enviando = true
  e.error = ''
  try {
    const { data } = await api.post(`/api/ventas/documento/${props.doc.id}/enviar`, {
      via: 'email', email: e.email, mensaje: e.mensaje || undefined,
    })
    emit('enviado', { id: props.doc.id, ...data })
    abierto.value = false
  } catch (err) {
    e.error = err.response?.data?.message || 'No se pudo enviar el correo'
  } finally {
    e.enviando = false
  }
}

async function registrarWhatsapp(destino) {
  const { data } = await api.post(`/api/ventas/documento/${props.doc.id}/enviar`, {
    via: 'whatsapp', solo_registrar: true, telefono: destino || undefined,
  })
  emit('enviado', { id: props.doc.id, ...data })
}

async function enviarWhatsapp() {
  const e = estado.value
  e.error = ''
  e.aviso = ''
  if (!e.pdfArchivo) await prepararPdf()
  if (!e.pdfArchivo) return
  e.enviando = true
  try {
    // 1) Celular (y navegadores que lo permiten): menú de compartir con el PDF adjunto
    if (navigator.canShare && navigator.canShare({ files: [e.pdfArchivo] })) {
      try {
        await navigator.share({ files: [e.pdfArchivo], text: e.mensaje, title: etiqueta.value })
        await registrarWhatsapp(e.telefono)
        abierto.value = false
        return
      } catch (err) {
        if (err?.name === 'AbortError') return   // cerró el menú sin compartir: no se registra nada
      }
    }
    // 2) Computador: descarga el PDF y abre el chat con el mensaje; el PDF se adjunta con el clip
    const url = window.URL.createObjectURL(e.pdfBlob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', e.pdfArchivo.name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)

    const tel = telefonoWa(e.telefono)
    window.open(tel ? `https://wa.me/${tel}?text=${encodeURIComponent(e.mensaje)}` : 'https://web.whatsapp.com/', '_blank')
    await registrarWhatsapp(e.telefono)
    e.aviso = `Se descargó «${e.pdfArchivo.name}». En WhatsApp pulsa el clip (📎) → Documento y elige ese archivo: es el último de tu carpeta Descargas.`
  } catch (err) {
    e.error = err.response?.data?.message || 'No se pudo preparar el envío'
  } finally {
    e.enviando = false
  }
}
</script>

<template>
  <VDialog v-model="abierto" max-width="520">
    <VCard v-if="doc">
      <VCardItem>
        <VCardTitle>Enviar {{ etiqueta }}</VCardTitle>
        <VCardSubtitle>{{ doc.cliente || 'Consumidor Final' }}</VCardSubtitle>
      </VCardItem>
      <VDivider />
      <VCardText class="pa-4">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <VBtn
            size="small" :variant="estado.canal === 'email' ? 'flat' : 'outlined'"
            :color="estado.canal === 'email' ? 'primary' : undefined"
            prepend-icon="mdi-email-outline" @click="elegirCanal('email')"
          >
            Correo · PDF
          </VBtn>
          <VBtn
            size="small" :variant="estado.canal === 'wapdf' ? 'flat' : 'outlined'"
            :color="estado.canal === 'wapdf' ? 'green' : undefined"
            prepend-icon="mdi-whatsapp" @click="elegirCanal('wapdf')"
          >
            WhatsApp · PDF
          </VBtn>
        </div>

        <VTextField
          v-if="estado.canal === 'email'" v-model="estado.email"
          label="Correo del destinatario" placeholder="cliente@empresa.cl" type="email"
          density="compact" variant="outlined" prepend-inner-icon="mdi-email-outline" class="mb-3"
        />
        <VTextField
          v-else v-model="estado.telefono" label="Teléfono (WhatsApp)" placeholder="Ej: 9 1234 5678"
          density="compact" variant="outlined" prepend-inner-icon="mdi-phone" class="mb-3"
        />
        <VTextarea v-model="estado.mensaje" label="Mensaje" rows="6" density="compact" variant="outlined" auto-grow />

        <p v-if="estado.canal === 'email'" class="text-caption text-medium-emphasis mt-1">
          Se envía un correo desde <strong>contacto@vialum.cl</strong> con el <strong>PDF oficial adjunto</strong>.
          Si el cliente no tenía correo, queda guardado en su ficha.
        </p>
        <p v-else class="text-caption text-medium-emphasis mt-1">
          Envía el <strong>PDF como archivo</strong>. En el <strong>celular</strong> se abre el menú de compartir con el PDF ya adjunto (elige WhatsApp y el contacto).
          En el <strong>computador</strong> se descarga el PDF y se abre el chat: tú lo adjuntas con el clip.
        </p>

        <VAlert v-if="estado.aviso" color="success" variant="tonal" density="compact" class="mt-2 text-caption" icon="mdi-file-pdf-box">{{ estado.aviso }}</VAlert>
        <VAlert v-if="estado.error" color="error" variant="tonal" density="compact" class="mt-2 text-caption">{{ estado.error }}</VAlert>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-3">
        <VSpacer />
        <VBtn variant="text" @click="abierto = false">{{ estado.aviso ? 'Cerrar' : 'Cancelar' }}</VBtn>
        <VBtn
          v-if="!estado.aviso"
          :color="estado.canal === 'email' ? 'primary' : 'green'"
          :loading="estado.enviando || (estado.canal === 'wapdf' && estado.pdfCargando)"
          :prepend-icon="estado.canal === 'email' ? 'mdi-email-outline' : 'mdi-file-pdf-box'"
          @click="estado.canal === 'email' ? enviarCorreo() : enviarWhatsapp()"
        >
          {{ estado.canal === 'email' ? 'Enviar por correo' : 'Compartir PDF' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
