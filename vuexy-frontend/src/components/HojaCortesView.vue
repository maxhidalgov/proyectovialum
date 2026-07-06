<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Object, required: true }, // { cotizacion?, grupos[], ventanas_omitidas? }
})

// ─── Colores por ventana ───────────────────────────────────────────────────
const HEX_PALETTE = [
  '#4CAF50', '#2196F3', '#FF9800', '#9C27B0',
  '#F44336', '#00BCD4', '#FF5722', '#009688',
  '#795548', '#607D8B', '#E91E63', '#3F51B5',
]
const ventanaHex = {}

const _buildColors = computed(() => {
  Object.keys(ventanaHex).forEach(k => delete ventanaHex[k])
  let idx = 0
  ;(props.data?.grupos ?? []).forEach(g =>
    (g.barras ?? []).forEach(b =>
      (b.cortes ?? []).forEach(c => {
        const base = String(c.ventana_ref ?? '').split('.')[0]
        if (base && !ventanaHex[base]) {
          ventanaHex[base] = HEX_PALETTE[idx % HEX_PALETTE.length]
          idx++
        }
      }),
    ),
  )
  return true
})

function getHex(ref) {
  void _buildColors.value
  const base = String(ref ?? '').split('.')[0]
  return ventanaHex[base] ?? '#9E9E9E'
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function segWidth(mm, largoBarra) {
  return (mm / largoBarra * 100).toFixed(2) + '%'
}

// Forma del segmento según los ángulos de corte (inglete). Puntos: SI, SD, ID, II.
// 90° = borde recto; 45° = punta larga abajo en ese extremo; 135° = punta larga arriba.
function clipPath(corte) {
  const o = '8px'
  let si = '0', sd = '100%', id = '100%', ii = '0'
  const ai = Number(corte.angulo_izq)
  const ad = Number(corte.angulo_der)

  if (ai === 135) si = o                       // sup-izq se retrae
  else if (ai === 45) ii = o                   // inf-izq se retrae

  if (ad === 45) id = `calc(100% - ${o})`      // inf-der se retrae
  else if (ad === 135) sd = `calc(100% - ${o})` // sup-der se retrae

  return `polygon(${si} 0, ${sd} 0, ${id} 100%, ${ii} 100%)`
}

function ventanasEnBarra(barra) {
  const seen = new Set()
  ;(barra.cortes ?? []).forEach(c => seen.add(String(c.ventana_ref ?? '').split('.')[0]))
  return [...seen]
}

function aprovechamientoGrupo(g) {
  const usoTotal = g.barras.reduce((s, b) => s + b.uso_mm, 0)
  const dispTotal = g.total_barras * g.largo_barra
  return dispTotal > 0 ? Math.round(usoTotal / dispTotal * 100) : 0
}

function retalTotalGrupo(g) {
  return g.barras.reduce((s, b) => s + b.retal_mm, 0)
}

const gruposPorProveedor = computed(() => {
  if (!props.data) return {}
  return props.data.grupos.reduce((acc, g) => {
    const p = g.proveedor || 'Sin proveedor'
    if (!acc[p]) acc[p] = []
    acc[p].push(g)
    return acc
  }, {})
})

const statsGlobales = computed(() => {
  if (!props.data) return []
  const grupos      = props.data.grupos
  const totalBarras = grupos.reduce((s, g) => s + g.total_barras, 0)
  const totalCortes = grupos.reduce((s, g) => s + g.total_cortes, 0)
  const totalUso    = grupos.reduce((s, g) => s + g.barras.reduce((sb, b) => sb + b.uso_mm, 0), 0)
  const totalDisp   = grupos.reduce((s, g) => s + g.total_barras * g.largo_barra, 0)
  const pct         = totalDisp > 0 ? Math.round(totalUso / totalDisp * 100) : 0
  return [
    { label: 'Perfiles',        value: grupos.length, color: 'primary' },
    { label: 'Barras totales',  value: totalBarras,   color: 'secondary' },
    { label: 'Cortes totales',  value: totalCortes,   color: 'info' },
    { label: 'Aprovechamiento', value: pct + '%',     color: pct >= 80 ? 'success' : 'warning' },
  ]
})
</script>

<template>
  <div>
    <!-- Aviso ventanas sin soporte -->
    <VAlert
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
    </VAlert>

    <!-- Estadísticas globales -->
    <VRow class="mb-5">
      <VCol v-for="stat in statsGlobales" :key="stat.label" cols="6" sm="3">
        <VCard rounded="lg" variant="tonal" :color="stat.color">
          <VCardText class="text-center pa-3">
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption">{{ stat.label }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Resumen de materiales -->
    <VCard rounded="lg" class="mb-6">
      <VCardTitle class="pa-4 pb-2 d-flex align-center gap-2">
        <VIcon icon="mdi-clipboard-list-outline" color="primary" />
        Resumen de Materiales
      </VCardTitle>
      <VDivider />
      <VTable density="compact">
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
              <VChip size="small" variant="tonal" :color="aprovechamientoGrupo(g) >= 80 ? 'success' : 'warning'">
                {{ aprovechamientoGrupo(g) }}%
              </VChip>
            </td>
            <td class="text-center text-caption text-medium-emphasis">
              {{ retalTotalGrupo(g).toLocaleString('es-CL') }} mm
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Grupos por proveedor -->
    <div v-for="(perfiles, proveedor) in gruposPorProveedor" :key="proveedor" class="mb-8">
      <div class="d-flex align-center gap-2 mb-3">
        <VIcon icon="mdi-factory" color="primary" />
        <h2 class="text-h6 font-weight-bold">{{ proveedor }}</h2>
        <VDivider class="ml-2" />
      </div>

      <VCard v-for="grupo in perfiles" :key="grupo.producto_id" rounded="lg" class="mb-5">
        <VCardTitle class="pa-4 pb-3 d-flex flex-wrap justify-space-between gap-2">
          <div class="d-flex align-center gap-2">
            <span class="font-weight-bold">{{ grupo.nombre }}</span>
            <VChip size="small" variant="tonal" color="secondary">{{ grupo.color }}</VChip>
          </div>
          <div class="d-flex align-center gap-3 text-body-2 text-medium-emphasis">
            <span>
              <VIcon size="16" class="mr-1">mdi-ruler</VIcon>
              {{ grupo.largo_barra.toLocaleString('es-CL') }} mm / barra
            </span>
            <VChip size="small" color="primary" variant="outlined">
              {{ grupo.total_barras }} barra{{ grupo.total_barras !== 1 ? 's' : '' }}
            </VChip>
            <VChip size="small" color="info" variant="outlined">{{ grupo.total_cortes }} cortes</VChip>
          </div>
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-4">
          <div v-for="barra in grupo.barras" :key="barra.numero" class="mb-6">
            <div class="d-flex align-center gap-3 mb-2">
              <VChip size="small" color="primary" label>
                Barra {{ barra.numero }}/{{ grupo.total_barras }}
              </VChip>
              <span class="text-body-2 text-medium-emphasis">
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
                    clipPath: clipPath(corte),
                  }"
                  :title="`${corte.ventana_ref} | ${corte.posicion} | ${corte.largo_mm} mm (${corte.angulo_izq}°/${corte.angulo_der}°)`"
                >
                  <span v-if="corte.largo_mm / grupo.largo_barra > 0.06" class="seg-label">
                    {{ corte.largo_mm }}
                  </span>
                </div>
                <div
                  v-if="barra.retal_mm > 0"
                  class="barra-retal"
                  :style="{ width: segWidth(barra.retal_mm, grupo.largo_barra) }"
                  :title="`Retal: ${barra.retal_mm} mm`"
                />
              </div>
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
            <VTable density="compact" class="rounded cortes-tabla">
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
                    <span class="leyenda-item" :style="{ backgroundColor: getHex(corte.ventana_ref) }">
                      {{ corte.ventana_ref }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.barra-bg {
  display: flex;
  gap: 2px;
  height: 42px;
  border-radius: 6px;
  padding: 2px;
  background: #d8d8d8;
  border: 1px solid rgba(0, 0, 0, 0.15);
}

.barra-seg {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 2px;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}

.seg-label {
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  text-shadow: 0 0 4px rgba(0, 0, 0, 0.8);
  white-space: nowrap;
  padding: 0 3px;
  overflow: hidden;
  z-index: 1;
}

.barra-retal {
  background: repeating-linear-gradient(45deg, #e0e0e0, #e0e0e0 4px, #bdbdbd 4px, #bdbdbd 8px);
  flex-shrink: 0;
}

.leyenda-item {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  text-shadow: 0 0 3px rgba(0, 0, 0, 0.5);
  line-height: 1.5;
}

.cortes-tabla :deep(th) {
  padding: 7px 12px !important;
  font-size: 12px !important;
  white-space: nowrap;
}

.cortes-tabla :deep(td) {
  padding: 7px 12px !important;
  font-size: 13px !important;
  white-space: nowrap;
}

@media print {
  .d-print-none { display: none !important; }
  .v-card { box-shadow: none !important; border: 1px solid #ddd !important; }
  .barra-bg, .barra-seg, .barra-retal, .leyenda-item {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .barra-seg { box-shadow: none !important; }
}
</style>
