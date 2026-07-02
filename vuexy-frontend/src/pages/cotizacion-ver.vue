<template>
  <v-container>
    <v-card class="mb-4">
      <v-card-title class="d-flex align-center flex-wrap gap-2">
        Cotización #{{ cotizacion?.id }}
        <v-btn
          class="ml-2"
          color="secondary"
          :loading="generandoPDF"
          :disabled="!cotizacion?.id"
          prepend-icon="mdi-file-pdf-box"
          @click="descargarPDF"
        >
          {{ generandoPDF ? 'Generando...' : 'Descargar PDF' }}
        </v-btn>

        <!-- Botones de cambio de estado -->
        <template v-if="cotizacion?.estado?.nombre === 'Evaluación'">
          <v-btn color="green" class="ml-2" :loading="loadingEstado" @click="cambiarEstado('Aprobada')">
            <v-icon start>mdi-check-circle</v-icon> Aprobar
          </v-btn>
          <v-btn color="red" class="ml-2" :loading="loadingEstado" @click="cambiarEstado('Rechazada')">
            <v-icon start>mdi-close-circle</v-icon> Rechazar
          </v-btn>
        </template>
        <template v-if="cotizacion?.estado?.nombre === 'Aprobada'">
          <v-btn color="red" class="ml-2" :loading="loadingEstado" @click="cambiarEstado('Rechazada')">
            <v-icon start>mdi-close-circle</v-icon> Rechazar
          </v-btn>
        </template>

        <v-spacer />
        <v-btn icon @click="volver">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <strong>Cliente:</strong> {{ cotizacion?.cliente?.nombre }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Vendedor:</strong> {{ cotizacion?.vendedor?.name }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Fecha:</strong> {{ cotizacion?.fecha }}
          </v-col>
          <v-col cols="12" sm="6">
            <strong>Estado:</strong>
            <v-chip :color="getEstadoColor(cotizacion?.estado?.nombre)">
              {{ cotizacion?.estado?.nombre || '—' }}
            </v-chip>
          </v-col>
          <v-col cols="12">
            <strong>Observaciones:</strong><br />
            {{ cotizacion?.observaciones || '—' }}
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title>Ventanas</v-card-title>
      <v-data-table
        :headers="headers"
        :items="cotizacion?.ventanas || []"
        :items-per-page="5"
        class="elevation-1"
      >
        <template #item.tipo_ventana_id="{ item }">
          {{ item.tipo_ventana?.nombre || '—' }}
        </template>

          <!-- Template para mostrar producto + proveedor en la columna de vidrio -->
        <template #item.producto_vidrio_proveedor_id="{ item }">
          {{ item.producto_vidrio_proveedor?.producto?.nombre || '—' }}
          <span v-if="item.producto_vidrio_proveedor?.proveedor">
            ({{ item.producto_vidrio_proveedor.proveedor.nombre }})
          </span>
        </template>

          <template #item.costo="{ item }">
            {{ clp(item.costo) }}
          </template>

          <template #item.precio_unitario="{ item }">
            {{ clp(item.precio_unitario) }}
          </template>

        <template #item.precio_total="{ item }">
          {{ clp(item.precio_total) }}
        </template>
      </v-data-table>
      <v-row justify="end" class="px-6 pb-4">
        <v-col cols="12" sm="4" class="text-right">
          <v-alert type="success" variant="tonal" border="start" border-color="green">
            <strong>Total general:</strong>
            {{ clp(cotizacion?.total_general) }}
          </v-alert>
        </v-col>
      </v-row>
    </v-card>

    <!-- Tabla de Productos -->
    <v-card v-if="productosDetalle.length > 0" class="mt-4">
      <v-card-title>Productos</v-card-title>
      <v-data-table
        :headers="headersProductos"
        :items="productosDetalle"
        :items-per-page="5"
        class="elevation-1"
      >
        <template #item.precio_unitario="{ item }">
          {{ clp(item.precio_unitario) }}
        </template>

        <template #item.total="{ item }">
          {{ clp(item.total) }}
        </template>
      </v-data-table>
    </v-card>

    <!-- ── Ventanas WINPERFIL ─────────────────────────────────────── -->
    <v-card v-if="winperfilDetalles.length > 0" class="mt-4">
      <v-card-title class="d-flex align-center gap-2 pa-4">
        <v-icon color="deep-purple">mdi-window-open</v-icon>
        Ventanas WINPERFIL
        <v-chip size="small" color="deep-purple" variant="tonal" class="ml-1">
          {{ winperfilDetalles.length }} ítem{{ winperfilDetalles.length !== 1 ? 's' : '' }}
        </v-chip>
        <v-chip v-if="cotizacion?.winperfil_precio_lock" size="small" color="amber-darken-2" variant="tonal" class="ml-1">
          <v-icon start size="14">mdi-lock</v-icon> Precio ajustado
        </v-chip>
      </v-card-title>

      <!-- ── Ajuste de precio (distribución proporcional) ── -->
      <v-card-text class="pb-0">
        <v-alert
          v-if="cotizacion?.winperfil_precio_lock"
          type="info" variant="tonal" density="compact" class="mb-3"
        >
          <v-icon size="16" class="mr-1">mdi-lock-check</v-icon>
          Precio ajustado manualmente — la sincronización con Winperfil ya no lo modificará.
        </v-alert>

        <v-row dense align="center" class="bg-grey-lighten-4 rounded pa-2">
          <v-col cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">Neto actual</div>
            <div class="font-weight-bold">{{ clp(netoWinperfil) }}</div>
          </v-col>
          <v-col cols="6" sm="3">
            <div class="text-caption text-medium-emphasis">Total (c/IVA)</div>
            <div class="font-weight-bold">{{ clp(cotizacion?.total) }}</div>
          </v-col>
          <v-col cols="7" sm="3">
            <v-text-field
              v-model.number="ajuste.total"
              label="Nuevo total"
              type="number"
              density="compact"
              variant="outlined"
              hide-details
              prefix="$"
            />
          </v-col>
          <v-col cols="5" sm="2">
            <v-select
              v-model="ajuste.tipo"
              :items="[{ title: 'Neto', value: 'neto' }, { title: 'Bruto', value: 'bruto' }]"
              label="Tipo"
              density="compact"
              variant="outlined"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="1">
            <v-btn
              color="deep-purple"
              size="small"
              block
              :loading="ajuste.loading"
              :disabled="!ajuste.total || ajuste.total <= 0"
              @click="aplicarAjustePrecio"
            >
              Aplicar
            </v-btn>
          </v-col>
        </v-row>
        <div class="text-caption text-medium-emphasis mt-1">
          El nuevo total se reparte proporcionalmente entre las {{ winperfilDetalles.length }} ventanas.
        </div>
      </v-card-text>

      <v-card-text>
        <v-row>
          <v-col
            v-for="(det, i) in winperfilDetalles"
            :key="i"
            cols="12" sm="6" md="4"
          >
            <v-card variant="outlined" class="h-100">
              <!-- Gráfico SVG de la ventana -->
              <div
                class="pa-3 text-center bg-grey-lighten-5"
                style="min-height:160px; display:flex; align-items:center; justify-content:center;"
              >
                <img
                  v-if="det.winperfil_grafico"
                  :src="det.winperfil_grafico.trim()"
                  style="max-width:100%; max-height:200px; object-fit:contain; cursor:pointer;"
                  alt="Vista de ventana"
                  :title="det.descripcion"
                  @click="svgAmpliado = det.winperfil_grafico.trim(); dialogSvg = true"
                />
                <v-icon v-else size="56" color="grey-lighten-2">mdi-window-maximize</v-icon>
              </div>

              <v-card-text class="pa-3">
                <div class="text-subtitle-2 font-weight-bold mb-1" style="line-height:1.3">
                  {{ det.descripcion }}
                </div>
                <div v-if="det.ancho_mm && det.alto_mm" class="text-caption text-medium-emphasis mb-2">
                  <v-icon size="13" class="mr-1">mdi-arrow-expand-horizontal</v-icon>
                  {{ Number(det.ancho_mm).toLocaleString('es-CL') }} × {{ Number(det.alto_mm).toLocaleString('es-CL') }} mm
                </div>
                <v-divider class="mb-2" />
                <div class="d-flex justify-space-between text-caption">
                  <span class="text-medium-emphasis">
                    Cant: <strong class="text-high-emphasis">{{ det.cantidad }}</strong>
                  </span>
                  <strong>{{ clp(det.total) }}</strong>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Dialog para ampliar SVG -->
    <v-dialog v-model="dialogSvg" max-width="800">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          Gráfico de ventana
          <v-btn icon variant="text" @click="dialogSvg = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-card-text class="text-center pa-6">
          <img
            v-if="svgAmpliado"
            :src="svgAmpliado"
            style="max-width:100%; max-height:70vh; object-fit:contain;"
            alt="Vista ampliada"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/axiosInstance'
import { svgDataUriToPng } from '@/composables/useSvgToPng'

const clp = (n) => '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(n) || 0)


const route = useRoute()
const router = useRouter()
const cotizacionId = route.query.id
const apiBase = import.meta.env.VITE_API_BASE_URL
const cotizacion = ref(null)

const headers = [
  { title: 'Tipo de ventana', value: 'tipo_ventana_id' },
  { title: 'Ancho (mm)', value: 'ancho' },
  { title: 'Alto (mm)', value: 'alto' },
  { title: 'Cantidad', value: 'cantidad' },
  { title: 'Color', value: 'color.nombre' },
  { title: 'Vidrio', value: 'producto_vidrio_proveedor_id' },
  // { title: 'Vidrio', value: 'producto_vidrio_proveedor.producto.nombre' },
  // { title: 'Vidrio Proveedor', value: 'producto_vidrio_proveedor.proveedor.nombre' },
  { title: 'Costo', value: 'costo' },
  { title: 'Precio Unitario', value: 'precio_unitario' },
  { title: 'Precio Total', value: 'precio_total' },

]

const headersProductos = [
  { title: 'Descripción', value: 'descripcion' },
  { title: 'Cantidad', value: 'cantidad' },
  { title: 'Precio Unitario', value: 'precio_unitario' },
  { title: 'Total', value: 'total' },
]

// Computed para filtrar solo los productos (tipo_item = 'producto')
const productosDetalle = computed(() => {
  if (!cotizacion.value?.detalles) return []
  return cotizacion.value.detalles.filter(d => d.tipo_item === 'producto')
})

// Computed para items Winperfil
const winperfilDetalles = computed(() => {
  if (!cotizacion.value?.detalles) return []
  return cotizacion.value.detalles.filter(d => d.tipo_item === 'winperfil')
})

// Dialog para ampliar SVG
const dialogSvg  = ref(false)
const svgAmpliado = ref('')

// ── Ajuste de precio Winperfil ──────────────────────────────────
const ajuste = ref({ total: null, tipo: 'bruto', loading: false })

const netoWinperfil = computed(() =>
  winperfilDetalles.value.reduce((s, d) => s + Number(d.total || 0), 0),
)

async function aplicarAjustePrecio() {
  if (!ajuste.value.total || ajuste.value.total <= 0) return
  ajuste.value.loading = true
  try {
    const { data } = await api.patch(`/api/cotizaciones/${cotizacion.value.id}/ajustar-precio`, {
      total: ajuste.value.total,
      tipo:  ajuste.value.tipo,
    })
    // Recargar la cotización con los precios recalculados
    const { data: fresca } = await api.get(`/api/cotizaciones/${cotizacion.value.id}`)
    cotizacion.value = fresca
    ajuste.value.total = null
    alert(data.message)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al ajustar el precio')
  } finally {
    ajuste.value.loading = false
  }
}

const getEstadoColor = (estado) => {
  switch (estado) {
    case 'Evaluación': return 'grey'
    case 'Aprobada': return 'green'
    case 'Rechazada': return 'red'
    default: return 'blue'
  }
}

const loadingEstado = ref(false)

const cambiarEstado = async (nuevoEstado) => {
  if (!confirm(`¿Confirmas cambiar el estado a "${nuevoEstado}"?`)) return
  loadingEstado.value = true
  try {
    const { data } = await api.patch(`/api/cotizaciones/${cotizacion.value.id}/estado`, { estado: nuevoEstado })
    cotizacion.value.estado = data.estado
    alert(data.message)
  } catch (err) {
    alert(err.response?.data?.message || 'Error al cambiar el estado.')
  } finally {
    loadingEstado.value = false
  }
}

const volver = () => {
  router.push({ name: 'cotizaciones' })
}

/**
 * Convierte los SVGs de Winperfil a PNG usando canvg y los guarda en la BD.
 * Solo procesa los que aún no tienen winperfil_grafico_png (primera vez).
 * Corre en background sin bloquear la UI.
 */
async function guardarPngsEnBackground(cot) {
  const sinPng = (cot.detalles || []).filter(
    d => d.tipo_item === 'winperfil' && d.winperfil_grafico && !d.winperfil_grafico_png
  )
  if (!sinPng.length) return

  const graficos = {}
  for (const det of sinPng) {
    try {
      graficos[det.id] = await svgDataUriToPng(det.winperfil_grafico.trim())
      // Actualizar también el objeto local para que el display sea inmediato
      const local = cotizacion.value?.detalles?.find(d => d.id === det.id)
      if (local) local.winperfil_grafico_png = graficos[det.id]
    } catch (e) {
      console.warn('[guardarPngs] error en detalle', det.id, e)
    }
  }

  if (!Object.keys(graficos).length) return

  try {
    await api.post(`/api/cotizaciones/${cot.id}/guardar-graficos-png`, { graficos })
    console.info(`[guardarPngs] ${Object.keys(graficos).length} PNGs guardados en BD`)
  } catch (e) {
    console.warn('[guardarPngs] no se pudieron guardar en BD:', e)
  }
}

const generandoPDF = ref(false)

const descargarPDF = async () => {
  generandoPDF.value = true
  try {
    // Los PNGs están guardados en BD (por guardarPngsEnBackground al cargar).
    // El endpoint GET los usa directamente — no necesita conversión browser.
    const response = await api.get(
      `/api/cotizaciones/${cotizacion.value.id}/pdf`,
      { responseType: 'blob' }
    )
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `cotizacion_${cotizacion.value.id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch (error) {
    console.error('Error al descargar PDF:', error)
    alert('Error al descargar el PDF.')
  } finally {
    generandoPDF.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/cotizaciones/${cotizacionId}`)
    cotizacion.value = data

    // Auto-guardar PNGs en BD si hay SVGs sin PNG convertido todavía.
    // Se hace en background (sin bloquear la UI) la primera vez que se abre la cotización.
    guardarPngsEnBackground(data)
  } catch (error) {
    console.error('Error al cargar cotización:', error)
  }
})
</script>
