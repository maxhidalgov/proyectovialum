<template>
  <v-container fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4">
      <v-icon icon="mdi-cart-arrow-down" size="32" color="deep-purple" />
      <div>
        <h1 class="text-h5 font-weight-bold">Órdenes de Compra</h1>
        <p class="text-caption text-grey mt-1">Historial de órdenes generadas para proveedores</p>
      </div>
      <v-spacer />
      <v-text-field
        v-model="busqueda"
        prepend-inner-icon="mdi-magnify"
        label="Buscar N° o proveedor"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:280px"
        @update:model-value="cargar"
      />
    </div>

    <v-card variant="outlined">
      <v-data-table
        :headers="headers"
        :items="ordenes"
        :loading="loading"
        density="compact"
        items-per-page="25"
      >
        <template #item.numero="{ item }">
          <span class="font-weight-bold font-monospace">{{ item.numero }}</span>
        </template>
        <template #item.fecha="{ item }">
          {{ fmtFecha(item.fecha) }}
        </template>
        <template #item.proveedor="{ item }">
          {{ item.proveedor || '—' }}
        </template>
        <template #item.cliente="{ item }">
          {{ item.cliente || '—' }}
        </template>
        <template #item.items_count="{ item }">
          <v-chip size="x-small" color="deep-purple" variant="tonal">{{ item.items_count }} ítems</v-chip>
        </template>
        <template #item.estado="{ item }">
          <div v-if="item.estado === 'enviada'" class="d-flex flex-column">
            <v-chip size="x-small" color="success" variant="tonal">
              <v-icon start size="12">{{ item.enviado_via === 'whatsapp' ? 'mdi-whatsapp' : 'mdi-email-check' }}</v-icon>
              Enviada
            </v-chip>
            <span class="text-caption text-grey mt-1">{{ fmtFecha(item.enviado_at) }}</span>
          </div>
          <v-chip v-else size="x-small" color="grey" variant="tonal">Generada</v-chip>
        </template>
        <template #item.acciones="{ item }">
          <div class="d-flex gap-1 justify-end">
            <v-btn size="x-small" color="deep-purple" variant="flat" prepend-icon="mdi-send" @click="abrirEnvio(item)">
              Enviar
            </v-btn>
            <v-btn size="x-small" color="red" variant="tonal" icon="mdi-file-pdf-box" @click="descargar(item, 'pdf')" />
            <v-btn size="x-small" color="green" variant="tonal" icon="mdi-file-excel" @click="descargar(item, 'excel')" />
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <v-icon size="40" class="mb-2">mdi-cart-off</v-icon>
            <p>No hay órdenes de compra. Genéralas desde el Taller (Materiales WP).</p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Diálogo de envío -->
    <v-dialog v-model="envio.show" max-width="520">
      <v-card>
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="deep-purple">mdi-send</v-icon>
          Enviar {{ envio.orden?.numero }}
          <span class="text-body-2 text-grey">· {{ envio.orden?.proveedor || 'Sin proveedor' }}</span>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Se enviará la orden con el PDF adjunto y quedará marcada como "pedido al proveedor" en Operaciones.
          </p>

          <v-text-field
            v-model="envio.email"
            label="Correo del proveedor"
            prepend-inner-icon="mdi-email-outline"
            type="email"
            variant="outlined"
            density="compact"
            class="mb-3"
            hide-details
          />
          <v-text-field
            v-model="envio.telefono"
            label="WhatsApp del proveedor (ej: +56 9 1234 5678)"
            prepend-inner-icon="mdi-whatsapp"
            variant="outlined"
            density="compact"
            class="mb-3"
            hide-details
          />
          <v-textarea
            v-model="envio.mensaje"
            label="Mensaje"
            variant="outlined"
            density="compact"
            rows="4"
            auto-grow
            hide-details
          />
          <p class="text-caption text-grey mt-2">El correo/teléfono se guardan en el proveedor para la próxima vez.</p>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-btn variant="text" @click="envio.show = false">Cancelar</v-btn>
          <v-spacer />
          <v-btn
            color="green"
            variant="flat"
            prepend-icon="mdi-whatsapp"
            :loading="envio.loadingWa"
            @click="enviar('whatsapp')"
          >
            WhatsApp
          </v-btn>
          <v-btn
            color="deep-purple"
            variant="flat"
            prepend-icon="mdi-email-fast"
            :loading="envio.loadingMail"
            @click="enviar('email')"
          >
            Correo
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3500" location="top">
      {{ snack.msg }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'
const api = axios

const ordenes  = ref([])
const loading  = ref(false)
const busqueda = ref('')
const snack    = ref({ show: false, color: 'success', msg: '' })

const headers = [
  { title: 'N°',        key: 'numero' },
  { title: 'Fecha',     key: 'fecha' },
  { title: 'Proveedor', key: 'proveedor' },
  { title: 'Obra/Cliente', key: 'cliente' },
  { title: 'Ítems',     key: 'items_count' },
  { title: 'Envío',     key: 'estado' },
  { title: 'Creada por', key: 'creador' },
  { title: '',          key: 'acciones', sortable: false, align: 'end' },
]

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/ordenes-compra', { params: { buscar: busqueda.value || undefined } })
    ordenes.value = Array.isArray(data) ? data : []
  } catch {
    ordenes.value = []
  } finally {
    loading.value = false
  }
}

async function descargar(orden, formato) {
  try {
    const res = await api.get(`/api/ordenes-compra/${orden.id}/${formato}`, { responseType: 'blob' })
    const ext = formato === 'pdf' ? 'pdf' : 'csv'
    const mime = formato === 'pdf' ? 'application/pdf' : 'text/csv'
    const url = window.URL.createObjectURL(new Blob([res.data], { type: mime }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${orden.numero}.${ext}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch {
    snack.value = { show: true, color: 'error', msg: 'Error al descargar' }
  }
}

function fmtFecha(f) {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Envío al proveedor ───────────────────────────────────────────
const envio = ref({
  show: false,
  orden: null,
  email: '',
  telefono: '',
  mensaje: '',
  loadingMail: false,
  loadingWa: false,
})

function abrirEnvio(orden) {
  const prov = orden.proveedor || ''
  envio.value = {
    show: true,
    orden,
    email: orden.proveedor_email || '',
    telefono: orden.proveedor_telefono || '',
    mensaje: `Hola${prov ? ' ' + prov : ''},\n\nAdjunto la orden de compra ${orden.numero}. Quedo atento a la confirmación.\n\nSaludos,\nVialum`,
    loadingMail: false,
    loadingWa: false,
  }
}

async function enviar(via) {
  const e = envio.value
  if (via === 'email' && !e.email) {
    snack.value = { show: true, color: 'warning', msg: 'Ingresa el correo del proveedor' }
    return
  }
  if (via === 'whatsapp' && !e.telefono) {
    snack.value = { show: true, color: 'warning', msg: 'Ingresa el WhatsApp del proveedor' }
    return
  }

  if (via === 'email') e.loadingMail = true
  else e.loadingWa = true

  try {
    const { data } = await api.post(`/api/ordenes-compra/${e.orden.id}/enviar`, {
      via,
      email: e.email || undefined,
      telefono: e.telefono || undefined,
      mensaje: e.mensaje || undefined,
    })

    if (via === 'whatsapp' && data.wa_url) {
      window.open(data.wa_url, '_blank')
      snack.value = { show: true, color: 'success', msg: 'WhatsApp abierto. Adjunta el PDF si lo necesitas.' }
    } else {
      snack.value = { show: true, color: 'success', msg: data.mensaje || 'Orden enviada' }
    }
    envio.value.show = false
    cargar()
  } catch (err) {
    snack.value = {
      show: true,
      color: 'error',
      msg: err.response?.data?.error || 'No se pudo enviar la orden',
    }
  } finally {
    e.loadingMail = false
    e.loadingWa = false
  }
}

onMounted(cargar)
</script>
