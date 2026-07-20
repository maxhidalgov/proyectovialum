<template>
  <VContainer fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VIcon icon="mdi-history" size="30" color="teal" />
      <div>
        <h1 class="text-h5 font-weight-bold">Historial de Ventas</h1>
        <p class="text-caption text-grey mt-1">Precio al que se vendió cada producto, por cliente</p>
      </div>
      <VSpacer />
      <VBtn
        variant="tonal"
        color="teal"
        prepend-icon="mdi-cloud-download"
        :loading="importando"
        @click="importarHistorico"
      >
        {{ importando ? `Importando… ${importProgreso}` : 'Importar histórico Bsale' }}
      </VBtn>
    </div>

    <VCard variant="outlined" class="mb-3">
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="5">
            <VTextField
              v-model="filtros.cliente"
              label="Cliente (nombre o RUT)"
              prepend-inner-icon="mdi-account-search"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              @update:model-value="buscarDebounced"
            />
          </VCol>
          <VCol cols="12" sm="5">
            <VTextField
              v-model="filtros.q"
              label="Producto"
              prepend-inner-icon="mdi-magnify"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              @update:model-value="buscarDebounced"
            />
          </VCol>
          <VCol cols="12" sm="2" class="d-flex align-center">
            <VBtn color="primary" variant="tonal" block @click="cargar">Buscar</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VAlert v-if="importMsg" type="info" density="compact" variant="tonal" closable class="mb-3" @click:close="importMsg = ''">
      {{ importMsg }}
    </VAlert>

    <VCard variant="outlined">
      <VDataTable
        :headers="headers"
        :items="filas"
        :loading="loading"
        density="compact"
        items-per-page="50"
      >
        <template #item.fecha_emision="{ item }">{{ fmtFecha(item.fecha_emision) }}</template>
        <template #item.tipo_documento_bsale_id="{ item }">
          <VChip size="x-small" :color="item.tipo_documento_bsale_id === 1 ? 'secondary' : (item.tipo_documento_bsale_id === 2 ? 'error' : 'info')" variant="tonal">
            {{ tipoDoc(item.tipo_documento_bsale_id) }}{{ item.numero_documento_bsale ? ' ' + item.numero_documento_bsale : '' }}
          </VChip>
        </template>
        <template #item.cantidad="{ item }">{{ fmtNum(item.cantidad) }}</template>
        <template #item.precio_unitario="{ item }">
          <span class="font-weight-bold">{{ clp(item.precio_unitario) }}</span>
          <span class="text-caption text-grey"> neto</span>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <VIcon size="40" class="mb-2">mdi-history</VIcon>
            <p>Sin resultados. Si es la primera vez, usa "Importar histórico Bsale" para traer las ventas pasadas.</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VSnackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">{{ snack.msg }}</VSnackbar>
  </VContainer>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/axiosInstance'

const filas   = ref([])
const loading = ref(false)
const filtros = ref({ cliente: '', q: '' })
const snack   = ref({ show: false, color: 'success', msg: '' })

const headers = [
  { title: 'Fecha',     key: 'fecha_emision' },
  { title: 'Documento', key: 'tipo_documento_bsale_id' },
  { title: 'Cliente',   key: 'cliente' },
  { title: 'Producto',  key: 'producto' },
  { title: 'Cant.',     key: 'cantidad', align: 'end' },
  { title: 'P. Unit.',  key: 'precio_unitario', align: 'end' },
]

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/ventas/historial-productos', {
      params: { cliente: filtros.value.cliente || undefined, q: filtros.value.q || undefined },
    })
    filas.value = Array.isArray(data) ? data : []
  } catch {
    filas.value = []
  } finally {
    loading.value = false
  }
}

let debTimer = null
function buscarDebounced() {
  clearTimeout(debTimer)
  debTimer = setTimeout(cargar, 400)
}

// ── Importar histórico de Bsale (en lotes) ─────────────────────────────────
const importando    = ref(false)
const importMsg     = ref('')
const importProgreso = ref('')

async function importarHistorico() {
  importando.value = true
  importMsg.value = ''
  let totalImportados = 0
  try {
    while (true) {
      const { data } = await api.post('/api/ventas/importar-lineas', { limit: 40 })
      totalImportados += data.importados
      importProgreso.value = `${totalImportados} docs · faltan ${data.pendientes}`
      if (data.importados === 0 || data.pendientes === 0) break
    }
    importMsg.value = `✓ Listo — ${totalImportados} documentos importados`
    await cargar()
  } catch (e) {
    importMsg.value = 'Error al importar: ' + (e.response?.data?.error || e.message)
  } finally {
    importando.value = false
    importProgreso.value = ''
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function tipoDoc(t) {
  return { 1: 'Boleta', 2: 'NC', 5: 'Factura', 3: 'Nota Venta', 4: 'Liquidación' }[t] || 'Doc'
}
function fmtFecha(f) {
  if (!f) return '—'
  return new Date(String(f).slice(0, 10) + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
const fmtNum = v => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(Number(v) || 0)
const clp = v => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

onMounted(cargar)
</script>
