<template>
  <div>
    <!-- Header -->
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-btn icon="mdi-arrow-left" variant="text" :to="{ name: 'produccion' }" />
          <div>
            <h1 class="text-h5 font-weight-bold">Hoja de Cortes</h1>
            <div v-if="data" class="text-body-2 text-medium-emphasis">
              Cotización #{{ data.cotizacion.id }} — {{ data.cotizacion.cliente }} —
              {{ data.cotizacion.fecha }}
            </div>
          </div>
        </div>
      </v-col>
      <v-col cols="auto" class="d-print-none">
        <v-btn
          prepend-icon="mdi-refresh"
          variant="tonal"
          color="secondary"
          class="mr-2"
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
    <v-skeleton-loader v-if="loading" type="article, table, article, table" />

    <!-- Error -->
    <v-alert v-else-if="error" type="error" rounded="lg">{{ error }}</v-alert>

    <!-- Sin datos -->
    <v-alert v-else-if="data && data.grupos.length === 0" type="info" rounded="lg">
      Esta cotización no tiene ventanas con perfiles configurados.
    </v-alert>

    <!-- Contenido -->
    <template v-else-if="data">      <!-- Aviso ventanas sin soporte -->
      <v-alert
        v-if="data.ventanas_omitidas && data.ventanas_omitidas.length > 0"
        type="warning"
        rounded="lg"
        class="mb-4"
        variant="tonal"
      >
        <div class="font-weight-bold mb-1">
          {{ data.ventanas_omitidas.length }} ventana{{ data.ventanas_omitidas.length > 1 ? 's' : '' }}
          sin hoja de cortes (tipo no soportado aún):
        </div>
        <ul class="mt-1 pl-4">
          <li v-for="v in data.ventanas_omitidas" :key="v.ref">
            <strong>{{ v.ref }}</strong> — {{ v.tipo }} ({{ v.ancho }}×{{ v.alto }} mm)
          </li>
        </ul>
      </v-alert>      <!-- Estadísticas globales -->
      <v-row class="mb-5">
        <v-col v-for="stat in statsGlobales" :key="stat.label" cols="6" sm="3">
          <v-card rounded="lg" variant="tonal" :color="stat.color">
            <v-card-text class="text-center pa-3">
              <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
              <div class="text-caption">{{ stat.label }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Resumen de materiales -->
      <v-card rounded="lg" class="mb-6">
        <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
          <v-icon icon="mdi-clipboard-list-outline" color="primary" />
          Resumen de Materiales
        </v-card-title>
        <v-divider />
        <v-table density="compact">
          <thead>
            <tr>
              <th>Perfil / Producto</th>
              <th>Color</th>
              <th>Proveedor</th>
              <th class="text-center">Largo barra</th>
              <th class="text-center">Barras</th>
              <th class="text-center">Cortes</th>
              <th class="text-center">Aprovechamiento</th>
              <th class="text-center">Retal total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in data.grupos" :key="g.producto_id">
              <td class="font-weight-medium">{{ g.nombre }}</td>
              <td>{{ g.color }}</td>
              <td>{{ g.proveedor }}</td>
              <td class="text-center">{{ (g.largo_barra / 1000).toFixed(2) }} m</td>
              <td class="text-center font-weight-bold">{{ g.total_barras }}</td>
              <td class="text-center">{{ g.total_cortes }}</td>
              <td class="text-center">
                <v-chip
                  size="small"
                  variant="tonal"
                  :color="aprovechamientoGrupo(g) >= 80 ? 'success' : 'warning'"
                >
                  {{ aprovechamientoGrupo(g) }}%
                </v-chip>
              </td>
              <td class="text-center text-caption text-medium-emphasis">
                {{ retalTotalGrupo(g).toLocaleString('es-CL') }} mm
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Grupos por proveedor -->
      <div v-for="(perfiles, proveedor) in gruposPorProveedor" :key="proveedor" class="mb-8">
        <div class="d-flex align-center gap-2 mb-3">
          <v-icon icon="mdi-factory" color="primary" />
          <h2 class="text-h6 font-weight-bold">{{ proveedor }}</h2>
          <v-divider class="ml-2" />
        </div>

        <!-- Tarjeta por perfil -->
        <v-card
          v-for="grupo in perfiles"
          :key="grupo.producto_id"
          rounded="lg"
          class="mb-5"
        >
          <!-- Encabezado del perfil -->
          <v-card-title class="pa-4 pb-3 d-flex flex-wrap justify-space-between gap-2">
            <div class="d-flex align-center gap-2">
              <span class="font-weight-bold">{{ grupo.nombre }}</span>
              <v-chip size="small" variant="tonal" color="secondary">{{ grupo.color }}</v-chip>
            </div>
            <div class="d-flex align-center gap-3 text-body-2 text-medium-emphasis">
              <span>
                <v-icon size="16" class="mr-1">mdi-ruler</v-icon>
                {{ grupo.largo_barra.toLocaleString('es-CL') }} mm / barra
              </span>
              <v-chip size="small" color="primary" variant="outlined">
                {{ grupo.total_barras }} barra{{ grupo.total_barras !== 1 ? 's' : '' }}
              </v-chip>
              <v-chip size="small" color="info" variant="outlined">
                {{ grupo.total_cortes }} cortes
              </v-chip>
            </div>
          </v-card-title>
          <v-divider />

          <v-card-text class="pa-4">
            <!-- Cada barra -->
            <div
              v-for="barra in grupo.barras"
              :key="barra.numero"
              class="mb-6"
            >
              <!-- Encabezado de barra -->
              <div class="d-flex align-center gap-3 mb-2">
                <v-chip size="small" color="primary" label>
                  Barra {{ barra.numero }}/{{ grupo.total_barras }}
                </v-chip>
                <span class="text-caption text-medium-emphasis">
                  Uso: {{ barra.uso_mm.toLocaleString('es-CL') }} mm
                  ({{ Math.round(barra.uso_mm / grupo.largo_barra * 100) }}%)
                  &middot; Virutas: {{ barra.virutas_mm }} mm
                  &middot; Retal: {{ barra.retal_mm.toLocaleString('es-CL') }} mm
                </span>
              </div>

              <!-- Diagrama visual de la barra -->
              <div class="barra-wrap mb-3">
                <div class="barra-bg">
                  <div
                    v-for="(corte, ci) in barra.cortes"
                    :key="ci"
                    class="barra-seg"
                    :style="{
                      width: segWidth(corte.largo_mm, grupo.largo_barra),
                      backgroundColor: getHex(corte.ventana_ref),
                    }"
                    :title="`${corte.ventana_ref} | ${corte.posicion} | ${corte.largo_mm} mm`"
                  >
                    <span v-if="corte.largo_mm / grupo.largo_barra > 0.07" class="seg-label">
                      {{ corte.largo_mm }}
                    </span>
                  </div>
                  <!-- Retal al final -->
                  <div
                    v-if="barra.retal_mm > 0"
                    class="barra-retal"
                    :style="{ width: segWidth(barra.retal_mm, grupo.largo_barra) }"
                    :title="`Retal: ${barra.retal_mm} mm`"
                  />
                </div>
                <!-- Leyenda de ventanas -->
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <span
                    v-for="ref in ventanasEnBarra(barra)"
                    :key="ref"
                    class="leyenda-item"
                    :style="{ backgroundColor: getHex(ref) }"
                  >
                    {{ ref }}
                  </span>
                </div>
              </div>

              <!-- Tabla de cortes -->
              <v-table density="compact" class="rounded cortes-tabla">
                <thead>
                  <tr>
                    <th class="text-center" style="width:36px">#</th>
                    <th>Medida</th>
                    <th class="text-center">Ang. Izq.</th>
                    <th class="text-center">Ang. Der.</th>
                    <th>Posición</th>
                    <th>Ventana</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(corte, ci) in barra.cortes" :key="ci">
                    <td class="text-center text-caption text-medium-emphasis">{{ ci + 1 }}</td>
                    <td class="font-weight-bold">{{ corte.largo_mm.toLocaleString('es-CL') }} mm</td>
                    <td class="text-center">{{ corte.angulo_izq }}°</td>
                    <td class="text-center">{{ corte.angulo_der }}°</td>
                    <td class="text-body-2">{{ corte.posicion }}</td>
                    <td>
                      <span
                        class="leyenda-item"
                        :style="{ backgroundColor: getHex(corte.ventana_ref) }"
                      >
                        {{ corte.ventana_ref }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </div>
          </v-card-text>
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

// ─── Colores por ventana ───────────────────────────────────────────────────
const HEX_PALETTE = [
  '#4CAF50', '#2196F3', '#FF9800', '#9C27B0',
  '#F44336', '#00BCD4', '#FF5722', '#009688',
  '#795548', '#607D8B', '#E91E63', '#3F51B5',
]
const ventanaHex = {} // base ref → hex

function buildColors(grupos) {
  let idx = 0
  grupos.forEach(g =>
    g.barras.forEach(b =>
      b.cortes.forEach(c => {
        const base = c.ventana_ref.split('.')[0]
        if (!ventanaHex[base]) {
          ventanaHex[base] = HEX_PALETTE[idx % HEX_PALETTE.length]
          idx++
        }
      })
    )
  )
}

function getHex(ref) {
  const base = ref.split('.')[0]
  return ventanaHex[base] ?? '#9E9E9E'
}

// ─── Helpers de layout ─────────────────────────────────────────────────────
function segWidth(mm, largoBarra) {
  return (mm / largoBarra * 100).toFixed(2) + '%'
}

function ventanasEnBarra(barra) {
  const seen = new Set()
  barra.cortes.forEach(c => seen.add(c.ventana_ref.split('.')[0]))
  return [...seen]
}

function imprimir() {
  window.print()
}

function aprovechamientoGrupo(g) {
  const usoTotal = g.barras.reduce((s, b) => s + b.uso_mm, 0)
  const dispTotal = g.total_barras * g.largo_barra
  return dispTotal > 0 ? Math.round(usoTotal / dispTotal * 100) : 0
}

function retalTotalGrupo(g) {
  return g.barras.reduce((s, b) => s + b.retal_mm, 0)
}

// ─── Computed ──────────────────────────────────────────────────────────────
const gruposPorProveedor = computed(() => {
  if (!data.value) return {}
  return data.value.grupos.reduce((acc, g) => {
    const p = g.proveedor || 'Sin proveedor'
    if (!acc[p]) acc[p] = []
    acc[p].push(g)
    return acc
  }, {})
})

const statsGlobales = computed(() => {
  if (!data.value) return []
  const grupos       = data.value.grupos
  const totalBarras  = grupos.reduce((s, g) => s + g.total_barras, 0)
  const totalCortes  = grupos.reduce((s, g) => s + g.total_cortes, 0)
  const totalUso     = grupos.reduce((s, g) => s + g.barras.reduce((sb, b) => sb + b.uso_mm, 0), 0)
  const totalDisp    = grupos.reduce((s, g) => s + g.total_barras * g.largo_barra, 0)
  const pct          = totalDisp > 0 ? Math.round(totalUso / totalDisp * 100) : 0
  return [
    { label: 'Perfiles',        value: grupos.length, color: 'primary'                         },
    { label: 'Barras totales',  value: totalBarras,   color: 'secondary'                       },
    { label: 'Cortes totales',  value: totalCortes,   color: 'info'                            },
    { label: 'Aprovechamiento', value: pct + '%',     color: pct >= 80 ? 'success' : 'warning' },
  ]
})

// ─── Carga de datos ────────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  error.value   = null
  data.value    = null
  Object.keys(ventanaHex).forEach(k => delete ventanaHex[k])

  try {
    const res = await api.get(`/api/cotizaciones/${route.params.id}/hoja-cortes?_t=${Date.now()}`)
    data.value = res.data
    buildColors(res.data.grupos)
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar la hoja de cortes.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

// Re-fetch si el usuario navega entre distintas cotizaciones sin desmontar el componente
watch(() => route.params.id, fetchData)
</script>

<style scoped>
/* Diagrama de barra */
.barra-bg {
  display: flex;
  height: 30px;
  border-radius: 6px;
  overflow: hidden;
  background: #e0e0e0;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

.barra-seg {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 1px;
  border-right: 2px solid rgba(0, 0, 0, 0.12);
  overflow: hidden;
}

.seg-label {
  font-size: 10px;
  color: #fff;
  text-shadow: 0 0 4px rgba(0, 0, 0, 0.7);
  white-space: nowrap;
  padding: 0 3px;
  overflow: hidden;
}

.barra-retal {
  background: repeating-linear-gradient(
    45deg,
    #e0e0e0,
    #e0e0e0 4px,
    #bdbdbd 4px,
    #bdbdbd 8px
  );
  flex-shrink: 0;
}

/* Etiqueta de ventana */
.leyenda-item {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  color: #fff;
  text-shadow: 0 0 3px rgba(0, 0, 0, 0.5);
  line-height: 1.5;
}

/* Tabla de cortes */
.cortes-tabla :deep(th),
.cortes-tabla :deep(td) {
  padding: 5px 10px !important;
  white-space: nowrap;
}

/* Impresión */
@media print {
  .d-print-none { display: none !important; }
  .v-card { box-shadow: none !important; border: 1px solid #ddd !important; }
  .v-skeleton-loader { display: none !important; }
}
</style>
