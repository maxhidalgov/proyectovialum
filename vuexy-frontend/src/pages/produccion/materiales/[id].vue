<template>
  <div>
    <!-- Header -->
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-btn icon="mdi-arrow-left" variant="text" :to="{ name: 'produccion' }" />
          <div>
            <h1 class="text-h5 font-weight-bold">Resumen de Materiales</h1>
            <div v-if="data" class="text-body-2 text-medium-emphasis">
              Cotización #{{ data.cotizacion.id }} — {{ data.cotizacion.cliente }} — {{ data.cotizacion.fecha }}
            </div>
          </div>
        </div>
      </v-col>
      <v-col cols="auto" class="d-flex gap-2 d-print-none">
        <v-btn
          prepend-icon="mdi-refresh"
          variant="tonal"
          color="secondary"
          :loading="loading"
          @click="fetchData"
        >
          Actualizar
        </v-btn>
        <v-btn prepend-icon="mdi-printer" variant="outlined" @click="imprimir">
          Imprimir
        </v-btn>
      </v-col>
    </v-row>

    <!-- Loading -->
    <v-skeleton-loader v-if="loading" type="table" />

    <!-- Error -->
    <v-alert v-else-if="error" type="error" rounded="lg">{{ error }}</v-alert>

    <!-- Sin datos -->
    <v-alert v-else-if="data && data.materiales.length === 0" type="info" rounded="lg">
      No se pudieron calcular materiales para esta cotización.
    </v-alert>

    <!-- Contenido -->
    <template v-else-if="data">

      <!-- Stats resumen -->
      <v-row class="mb-5">
        <v-col v-for="s in stats" :key="s.label" cols="6" sm="3">
          <v-card rounded="lg" variant="tonal" :color="s.color">
            <v-card-text class="text-center pa-3">
              <div class="text-h6 font-weight-bold">{{ s.value }}</div>
              <div class="text-caption">{{ s.label }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Perfiles -->
      <v-card rounded="lg" class="mb-5" v-if="perfiles.length">
        <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
          <v-icon icon="mdi-format-list-bulleted-square" color="primary" />
          Perfiles / Barras
        </v-card-title>
        <v-divider />
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Perfil</th>
              <th>Color</th>
              <th>Proveedor</th>
              <th class="text-center">Largo barra</th>
              <th class="text-center">Metros necesarios</th>
              <th class="text-center font-weight-bold">Barras a pedir</th>
              <th class="text-end">Costo estimado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in perfiles" :key="m.producto_id">
              <td class="font-weight-medium">{{ m.nombre }}</td>
              <td>
                <v-chip v-if="m.color" size="small" variant="tonal" color="secondary">{{ m.color }}</v-chip>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td>{{ m.proveedor || '—' }}</td>
              <td class="text-center text-medium-emphasis">{{ m.largo_total_m ? m.largo_total_m.toFixed(2) + ' m' : '—' }}</td>
              <td class="text-center">{{ m.cantidad.toFixed(2) }} m</td>
              <td class="text-center">
                <v-chip color="primary" variant="tonal" size="small" class="font-weight-bold">
                  {{ m.barras }}
                </v-chip>
              </td>
              <td class="text-end text-body-2">{{ formatCLP(m.costo_total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-weight-bold">
              <td colspan="5" class="text-end text-caption text-medium-emphasis">Total perfiles</td>
              <td class="text-center">
                <v-chip color="primary" size="small" class="font-weight-bold">
                  {{ perfiles.reduce((s, m) => s + (m.barras || 0), 0) }} barras
                </v-chip>
              </td>
              <td class="text-end">{{ formatCLP(perfiles.reduce((s, m) => s + m.costo_total, 0)) }}</td>
            </tr>
          </tfoot>
        </v-table>
      </v-card>

      <!-- Herrajes -->
      <v-card rounded="lg" class="mb-5" v-if="herrajes.length">
        <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
          <v-icon icon="mdi-wrench-outline" color="warning" />
          Herrajes
        </v-card-title>
        <v-divider />
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Herraje</th>
              <th>Proveedor</th>
              <th class="text-center">Cantidad</th>
              <th class="text-end">Costo estimado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in herrajes" :key="m.producto_id">
              <td class="font-weight-medium">{{ m.nombre }}</td>
              <td>{{ m.proveedor || '—' }}</td>
              <td class="text-center font-weight-bold">{{ formatCantidad(m.cantidad, m.unidad) }}</td>
              <td class="text-end text-body-2">{{ formatCLP(m.costo_total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-weight-bold">
              <td colspan="3" class="text-end text-caption text-medium-emphasis">Total herrajes</td>
              <td class="text-end">{{ formatCLP(herrajes.reduce((s, m) => s + m.costo_total, 0)) }}</td>
            </tr>
          </tfoot>
        </v-table>
      </v-card>

      <!-- Vidrios -->
      <v-card rounded="lg" class="mb-5" v-if="vidrios.length">
        <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
          <v-icon icon="mdi-square-outline" color="info" />
          Vidrios
        </v-card-title>
        <v-divider />
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Vidrio</th>
              <th>Proveedor</th>
              <th class="text-center">M²</th>
              <th class="text-end">Costo estimado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in vidrios" :key="m.producto_id">
              <td class="font-weight-medium">{{ m.nombre }}</td>
              <td>{{ m.proveedor || '—' }}</td>
              <td class="text-center font-weight-bold">{{ m.cantidad.toFixed(3) }} m²</td>
              <td class="text-end text-body-2">{{ formatCLP(m.costo_total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-weight-bold">
              <td colspan="2" class="text-end text-caption text-medium-emphasis">Total vidrio</td>
              <td class="text-center">{{ vidrios.reduce((s, m) => s + m.cantidad, 0).toFixed(3) }} m²</td>
              <td class="text-end">{{ formatCLP(vidrios.reduce((s, m) => s + m.costo_total, 0)) }}</td>
            </tr>
          </tfoot>
        </v-table>
      </v-card>

      <!-- Otros -->
      <v-card rounded="lg" class="mb-5" v-if="otros.length">
        <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
          <v-icon icon="mdi-package-variant-closed" />
          Otros
        </v-card-title>
        <v-divider />
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Tipo</th>
              <th>Proveedor</th>
              <th class="text-center">Cantidad</th>
              <th class="text-end">Costo estimado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in otros" :key="m.producto_id">
              <td class="font-weight-medium">{{ m.nombre }}</td>
              <td class="text-caption text-medium-emphasis">{{ m.tipo_producto || '—' }}</td>
              <td>{{ m.proveedor || '—' }}</td>
              <td class="text-center font-weight-bold">{{ formatCantidad(m.cantidad, m.unidad) }}</td>
              <td class="text-end text-body-2">{{ formatCLP(m.costo_total) }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Total -->
      <div class="d-flex justify-end mt-2 d-print-none">
        <v-card rounded="lg" min-width="260">
          <v-list density="compact" class="pa-2">
            <v-list-item>
              <template #prepend><span class="text-body-2 text-medium-emphasis">Costo total estimado</span></template>
              <template #append><span class="font-weight-bold">{{ formatCLP(costoTotal) }}</span></template>
            </v-list-item>
          </v-list>
        </v-card>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/axiosInstance'

const route = useRoute()

const data    = ref(null)
const loading = ref(true)
const error   = ref(null)

// ─── Computed segmentado por unidad (más robusto que por nombre de tipo) ──
// unidad === 'm'  → perfil/barra (siempre tiene barras calculadas)
// unidad === 'm2' → vidrio/termopanel
// resto           → herraje/accesorio
const perfiles = computed(() => data.value?.materiales.filter(m => m.unidad === 'm') ?? [])
const vidrios  = computed(() => data.value?.materiales.filter(m => m.unidad === 'm2') ?? [])
const herrajes = computed(() => data.value?.materiales.filter(m => m.unidad !== 'm' && m.unidad !== 'm2') ?? [])
const otros    = computed(() => [])

const costoTotal = computed(() => data.value?.materiales.reduce((s, m) => s + (m.costo_total || 0), 0) ?? 0)

const stats = computed(() => {
  if (!data.value) return []
  const totalBarras = perfiles.value.reduce((s, m) => s + (m.barras || 0), 0)
  const totalM2     = vidrios.value.reduce((s, m) => s + m.cantidad, 0)
  return [
    { label: 'Tipos de perfil',   value: perfiles.value.length, color: 'primary'   },
    { label: 'Barras totales',    value: totalBarras,            color: 'secondary' },
    { label: 'Vidrio total (m²)', value: totalM2.toFixed(2),    color: 'info'      },
    { label: 'Costo estimado',    value: formatCLP(costoTotal.value), color: 'success' },
  ]
})

// ─── Helpers ──────────────────────────────────────────────────────────────
const formatCLP = (n) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n || 0)

function formatCantidad(cant, unidad) {
  if (unidad === 'm')  return cant.toFixed(2) + ' m'
  if (unidad === 'm2') return cant.toFixed(3) + ' m²'
  return Math.ceil(cant) + ' un'
}

// ─── Carga ────────────────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  error.value   = null
  data.value    = null
  try {
    const res = await api.get(`/api/cotizaciones/${route.params.id}/materiales?_t=${Date.now()}`)
    data.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar los materiales.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
watch(() => route.params.id, fetchData)

function imprimir() { window.print() }
</script>

<style scoped>
tfoot tr td {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-top: 8px !important;
}
@media print {
  .d-print-none { display: none !important; }
  .v-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
