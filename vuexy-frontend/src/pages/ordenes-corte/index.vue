<template>
  <VContainer fluid class="pa-4">
    <div class="d-flex align-center gap-3 mb-4">
      <VIcon icon="mdi-content-cut" size="30" color="info" />
      <div>
        <h1 class="text-h5 font-weight-bold">Órdenes de Corte</h1>
        <p class="text-caption text-grey mt-1">Ventas con vidrios · reimprimibles para el taller</p>
      </div>
      <VSpacer />
      <VTextField
        v-model="busqueda"
        prepend-inner-icon="mdi-magnify"
        label="Buscar cliente o N° doc"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:280px"
        @update:model-value="cargar"
      />
    </div>

    <VCard variant="outlined">
      <VDataTable
        :headers="headers"
        :items="ordenes"
        :loading="loading"
        density="compact"
        items-per-page="25"
      >
        <template #item.numero="{ item }">
          <span class="font-weight-bold font-monospace">{{ item.numero }}</span>
        </template>
        <template #item.fecha="{ item }">{{ fmtFecha(item.fecha) }}</template>
        <template #item.tipo="{ item }">
          <VChip size="x-small" :color="item.tipo === 'Boleta' ? 'secondary' : 'info'" variant="tonal">
            {{ item.tipo }} {{ item.doc_numero ? '#' + item.doc_numero : '' }}
          </VChip>
        </template>
        <template #item.cliente="{ item }">{{ item.cliente || 'Consumidor Final' }}</template>
        <template #item.total_piezas="{ item }">
          <VChip size="x-small" color="info" variant="tonal">{{ item.total_piezas }} pieza{{ item.total_piezas === 1 ? '' : 's' }}</VChip>
        </template>
        <template #item.acciones="{ item }">
          <div class="d-flex gap-1 justify-end">
            <VBtn size="x-small" color="info" variant="tonal" prepend-icon="mdi-printer" @click="imprimir(item)">Reimprimir</VBtn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <VIcon size="40" class="mb-2">mdi-content-cut</VIcon>
            <p>Sin órdenes de corte. Se generan al emitir una Venta Express con vidrios.</p>
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

const ordenes  = ref([])
const loading  = ref(false)
const busqueda = ref('')
const snack    = ref({ show: false, color: 'success', msg: '' })

const headers = [
  { title: 'N°',        key: 'numero' },
  { title: 'Fecha',     key: 'fecha' },
  { title: 'Documento', key: 'tipo' },
  { title: 'Cliente',   key: 'cliente' },
  { title: 'Piezas',    key: 'total_piezas' },
  { title: '',          key: 'acciones', sortable: false, align: 'end' },
]

async function cargar() {
  loading.value = true
  try {
    const { data } = await api.get('/api/ordenes-corte', { params: { buscar: busqueda.value || undefined } })
    ordenes.value = Array.isArray(data) ? data : []
  } catch {
    ordenes.value = []
  } finally {
    loading.value = false
  }
}

function imprimir(o) {
  const filas = (o.piezas || []).map((v, i) => `
    <tr>
      <td style="text-align:center">${i + 1}</td>
      <td>${v.producto}</td>
      <td style="text-align:center">${v.ancho ?? '—'}</td>
      <td style="text-align:center">${v.alto ?? '—'}</td>
      <td style="text-align:center">${v.piezas}</td>
      <td style="text-align:center">${v.pulido ? 'Sí' : '—'}</td>
    </tr>`).join('')

  const html = `
    <html><head><title>Orden de Corte ${o.numero}</title>
    <style>
      body{font-family:Arial,Helvetica,sans-serif;color:#222;padding:24px}
      h1{color:#6a1b9a;margin:0 0 2px;font-size:22px}
      .sub{color:#666;font-size:13px;margin-bottom:16px}
      table{width:100%;border-collapse:collapse;margin-top:8px}
      th,td{border:1px solid #ccc;padding:8px 10px;font-size:14px}
      th{background:#f3e5f5;text-align:left}
      .tot{margin-top:14px;font-size:12px;color:#666}
    </style></head><body>
      <h1>Vialum — Orden de Corte</h1>
      <div class="sub">${o.numero} · ${o.tipo} ${o.doc_numero ? 'N° ' + o.doc_numero : ''} · ${o.cliente || 'Consumidor Final'} · ${fmtFecha(o.fecha)}</div>
      <table>
        <thead><tr><th style="width:36px">#</th><th>Vidrio</th><th style="width:90px">Ancho (mm)</th><th style="width:90px">Alto (mm)</th><th style="width:70px">Piezas</th><th style="width:70px">Pulido</th></tr></thead>
        <tbody>${filas}</tbody>
      </table>
      <p class="tot">Total de piezas a cortar: ${o.total_piezas}</p>
    </body></html>`

  const w = window.open('', '_blank')
  if (!w) { snack.value = { show: true, color: 'warning', msg: 'Permite las ventanas emergentes para imprimir' }; return }
  w.document.write(html); w.document.close(); w.focus()
  setTimeout(() => w.print(), 300)
}

function fmtFecha(f) {
  if (!f) return '—'
  return new Date(String(f).slice(0, 10) + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(cargar)
</script>
