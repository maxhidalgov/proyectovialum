<template>
  <v-dialog v-model="localMostrar" max-width="1200px" persistent>
    <v-card class="pa-4">
      <v-card-title class="text-h5">Editar ventana</v-card-title>
      <v-divider class="mb-4" />

      <v-form ref="formRef" @submit.prevent="onGuardar">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="ventanaLocal.tipo"
              :items="tiposVentanaFiltrados"
              item-title="nombre"
              item-value="id"
              label="Tipo de ventana"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.ancho"
              label="Ancho (mm)"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.alto"
              label="Alto (mm)"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.cantidad"
              label="Cantidad"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.material"
              :items="materiales"
              item-title="nombre"
              item-value="id"
              label="Material"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.color"
              :items="colores"
              item-title="nombre"
              item-value="id"
              label="Color"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.tipoVidrio"
              :items="tiposVidrio"
              item-title="nombre"
              item-value="id"
              label="Tipo de vidrio"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.productoVidrioProveedor"
              :items="productosVidrioDisponibles"
              item-title="nombre"
              item-value="id"
              label="Producto de vidrio"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>

        <!-- Controles adicionales según tipo de ventana -->
        <v-row v-if="[3, 46, 52, 55].includes(ventanaLocal.tipo)" dense class="mb-1">
          <!-- Hojas totales (no aplica para AL25 que siempre es 2) -->
          <v-col v-if="[3, 46, 52].includes(ventanaLocal.tipo)" cols="6" sm="3">
            <v-select
              v-model="ventanaLocal.hojas_totales"
              :items="[2, 3, 4, 6]"
              label="Hojas totales"
              variant="outlined"
              density="compact"
              color="primary"
              @update:model-value="recalcularCostos"
            />
          </v-col>
          <!-- Hojas que corren -->
          <v-col cols="6" sm="3">
            <v-select
              v-model="ventanaLocal.hojas_moviles"
              :items="ventanaLocal.tipo === 55 ? [1, 2] : [1, 2, 3, 4]"
              label="Hojas que corren"
              variant="outlined"
              density="compact"
              color="primary"
              @update:model-value="recalcularCostos"
            />
          </v-col>
          <!-- Hoja 1 al frente (no aplica AL25) -->
          <v-col v-if="[3, 46, 52].includes(ventanaLocal.tipo)" cols="6" sm="3" class="d-flex align-center">
            <v-switch
              v-model="ventanaLocal.hoja1AlFrente"
              label="Hoja 1 adelante"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="recalcularCostos"
            />
          </v-col>
          <!-- Manillón (AL25 y Corredera 98) -->
          <v-col v-if="[52, 55].includes(ventanaLocal.tipo)" cols="6" sm="3" class="d-flex align-center">
            <v-switch
              v-model="ventanaLocal.manillon"
              label="Manillón"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="recalcularCostos"
            />
          </v-col>
        </v-row>

        <!-- Vista previa -->
        <v-row>
          <v-col cols="12">
            <VentanaFijaAL42
              v-if="ventanaLocal.tipo === 1"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
            />
            <VentanaEditor
              v-else-if="ventanaLocal.tipo === 2"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
            />
            <VentanaCorredera
              v-else-if="ventanaLocal.tipo === 3"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :hojas-totales="ventanaLocal.hojas_totales"
              :hojas-moviles="ventanaLocal.hojas_moviles"
              :hoja-movil-seleccionada="ventanaLocal.hojaMovilSeleccionada"
              :orden-hoja1-al-frente="ventanaLocal.hoja1AlFrente"
            />
            <VentanaProyectante
              v-else-if="ventanaLocal.tipo === 45"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
            />
            <VistaVentanaCorrederaAndes
              v-else-if="ventanaLocal.tipo === 46"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :hojas-totales="ventanaLocal.hojas_totales"
              :hojas-moviles="ventanaLocal.hojas_moviles"
              :hoja-movil-seleccionada="ventanaLocal.hojaMovilSeleccionada"
              :orden-hoja1-al-frente="ventanaLocal.hoja1AlFrente"
            />
            <BayWindow
              v-else-if="ventanaLocal.tipo === 47"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :ancho-izquierda="ventanaLocal.ancho_izquierda"
              :ancho-centro="ventanaLocal.ancho_centro"
              :ancho-derecha="ventanaLocal.ancho_derecha"
              :tipo-ventana-izquierda="ventanaLocal.tipoVentanaIzquierda"
              :tipo-ventana-centro="ventanaLocal.tipoVentanaCentro"
              :tipo-ventana-derecha="ventanaLocal.tipoVentanaDerecha"
            />
            <VentanaAbatir
              v-else-if="ventanaLocal.tipo === 49"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :lado-inicial="ventanaLocal.ladoApertura || 'izquierda'"
            />
            <PuertaS60
              v-else-if="ventanaLocal.tipo === 50"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :lado-apertura="ventanaLocal.ladoApertura"
              :direccion-apertura="ventanaLocal.direccionApertura"
              :paso-libre="ventanaLocal.pasoLibre"
            />
            <VistaMamparaS60
              v-else-if="ventanaLocal.tipo === 51"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :hoja-activa="ventanaLocal.hojaActiva"
              :direccion-apertura="ventanaLocal.direccionApertura"
              :paso-libre="ventanaLocal.pasoLibre"
            />
            <VentanaCorredera98
              v-else-if="ventanaLocal.tipo === 52"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
              :hojas-totales="ventanaLocal.hojas_totales"
              :hojas-moviles="ventanaLocal.hojas_moviles"
              :hoja-movil-seleccionada="ventanaLocal.hojaMovilSeleccionada"
              :orden-hoja1-al-frente="ventanaLocal.hoja1AlFrente"
            />
            <VistaVentanaMonorriel
              v-else-if="ventanaLocal.tipo === 53"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :lado-apertura="ventanaLocal.ladoApertura"
            />
            <VentanaCorrederaAL25
              v-else-if="ventanaLocal.tipo === 55"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :hoja1AlFrente="ventanaLocal.hoja1AlFrente"
              :hojas-moviles="ventanaLocal.hojas_moviles || 2"
              :hoja-movil-seleccionada="ventanaLocal.hojaMovilSeleccionada || 1"
            />
            <VentanaProyectanteAL42
              v-else-if="ventanaLocal.tipo === 56"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
            />
            <VistaVentanaCompuestaAL42
              v-else-if="ventanaLocal.tipo === 57"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :filas="ventanaLocal.filas"
              :columnas="ventanaLocal.columnas"
              :altos-filas="ventanaLocal.altosFilas"
              :anchos-columnas="ventanaLocal.anchosColumnas"
              :secciones="ventanaLocal.secciones"
            />
          </v-col>
        </v-row>

        <!-- Costos -->
        <v-row dense class="mb-2 mt-2">
          <v-col cols="4">
            <div class="rounded-lg pa-3 text-center" style="background: rgba(var(--v-theme-on-surface), 0.06)">
              <div class="text-caption text-medium-emphasis mb-1">Costo unitario</div>
              <div v-if="calculando"><v-progress-linear indeterminate color="warning" height="2" rounded /></div>
              <div v-else class="text-body-2 font-weight-bold">
                {{ ventanaLocal.costo_total_unitario ? '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(ventanaLocal.costo_total_unitario) : '—' }}
              </div>
            </div>
          </v-col>
          <v-col cols="4">
            <div class="rounded-lg pa-3 text-center" style="background: rgba(var(--v-theme-on-surface), 0.06)">
              <div class="text-caption text-medium-emphasis mb-1">Costo total</div>
              <div v-if="calculando"><v-progress-linear indeterminate color="warning" height="2" rounded /></div>
              <div v-else class="text-body-2 font-weight-bold">
                {{ ventanaLocal.costo_total ? '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(ventanaLocal.costo_total) : '—' }}
              </div>
            </div>
          </v-col>
          <v-col cols="4">
            <div class="rounded-lg pa-3 text-center" style="background: rgba(var(--v-theme-success), 0.1)">
              <div class="text-caption text-medium-emphasis mb-1">Precio de venta (Neto)</div>
              <div v-if="calculando"><v-progress-linear indeterminate color="success" height="2" rounded /></div>
              <div v-else class="text-body-1 font-weight-bold text-success">
                {{ ventanaLocal.precio ? '$' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(ventanaLocal.precio) : '—' }}
              </div>
            </div>
          </v-col>
        </v-row>

        <!-- Detalle de materiales colapsable -->
        <v-row v-if="ventanaLocal.materiales && ventanaLocal.materiales.length" class="mt-1">
          <v-col cols="12">
            <div class="d-flex align-center">
              <v-btn
                variant="text"
                size="small"
                :color="mostrarDetalleMateriales ? 'primary' : 'default'"
                :prepend-icon="mostrarDetalleMateriales ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                @click="mostrarDetalleMateriales = !mostrarDetalleMateriales"
              >
                Ver detalle de materiales ({{ ventanaLocal.materiales.length }})
              </v-btn>
              <v-spacer />
              <v-btn
                v-if="mostrarDetalleMateriales"
                color="success"
                variant="tonal"
                size="small"
                @click="descargarMateriales"
              >
                <v-icon start>mdi-download</v-icon>
                Descargar Excel
              </v-btn>
            </div>
            <v-expand-transition>
              <v-card v-if="mostrarDetalleMateriales" variant="outlined" class="mt-2">
                <v-data-table
                  :headers="[
                    { title: 'Material', key: 'nombre' },
                    { title: 'Proveedor', key: 'proveedor' },
                    { title: 'Cantidad', key: 'cantidad' },
                    { title: 'Unidad', key: 'unidad' },
                    { title: 'Costo unitario', key: 'costo_unitario' },
                    { title: 'Costo total', key: 'costo_total' }
                  ]"
                  :items="ventanaLocal.materiales"
                  dense
                  :items-per-page="10"
                  :items-per-page-options="[5, 10, 25, 50, { value: -1, title: 'Todos' }]"
                >
                  <template #item.costo_unitario="{ item }">
                    ${{ item.costo_unitario }}
                  </template>
                  <template #item.costo_total="{ item }">
                    ${{ item.costo_total }}
                  </template>
                </v-data-table>
              </v-card>
            </v-expand-transition>
          </v-col>
        </v-row>

        <v-card-actions class="justify-end mt-4">
          <v-btn color="secondary" variant="text" @click="cerrar">Cancelar</v-btn>
          <v-btn
            color="primary"
            type="submit"
            :disabled="calculando || !precioActualizado"
            :loading="calculando"
          >
            Guardar cambios
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import VentanaFijaAL42 from '@/components/VistaVentanaFijaAL42.vue'
import VentanaEditor from '@/components/VistaVentanaFijaS60.vue'
import VentanaProyectante from '@/components/VistaVentanaProyectanteS60.vue'
import VentanaProyectanteAL42 from '@/components/VistaVentanaProyectanteAL42.vue'
import VentanaCorredera from '@/components/VistaVentanaCorredera.vue'
import VentanaCorredera98 from '@/components/VistaVentanaCorredera98.vue'
import VentanaCorrederaAL25 from '@/components/VistaVentanaCorrederaAL25.vue'
import VistaVentanaCompuestaAL42 from '@/components/VistaVentanaCompuestaAL42.vue'
import VistaVentanaCorrederaAndes from '@/components/VistaVentanaCorrederaAndes.vue'
import BayWindow from '@/components/VistaBayWindow.vue'
import VentanaAbatir from '@/components/VistaVentanaAbatirS60.vue'
import PuertaS60 from '@/components/VistaPuertaS60.vue'
import VistaMamparaS60 from '@/components/VistaMamparaS60.vue'
import VistaVentanaMonorriel from '@/components/VistaVentanaMonorriel.vue'
import api from '@/axiosInstance'

const props = defineProps({
  mostrar: Boolean,
  ventana: Object,
  materiales: Array,
  colores: Array,
  tiposVidrio: Array,
  tiposVentana: Array,
  productosVidrio: Array
})

const emit = defineEmits(['update:mostrar', 'guardar'])

const localMostrar = ref(props.mostrar)
const ventanaLocal = ref({})
const calculando = ref(false)
const precioActualizado = ref(false)
const mostrarDetalleMateriales = ref(false)

watch(() => props.mostrar, (val) => {
  localMostrar.value = val
  if (val && props.ventana) {
    ventanaLocal.value = { ...props.ventana }
    precioActualizado.value = false
    mostrarDetalleMateriales.value = false
    recalcularCostos()
  }
})

watch(localMostrar, (val) => emit('update:mostrar', val))

const tiposVentanaFiltrados = computed(() => {
  return props.tiposVentana.filter(t => t.material_id === ventanaLocal.value.material)
})

const productosVidrioDisponibles = computed(() => {
  if (!ventanaLocal.value.tipoVidrio) return []
  
  return props.productosVidrio
    .filter(p => p.tipo_producto_id === ventanaLocal.value.tipoVidrio)
    .flatMap(p => {
      if (!p.colores_por_proveedor) return []
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Sin proveedor'})`
      }))
    })
})

const cerrar = () => {
  localMostrar.value = false
}

const onGuardar = () => {
  if (calculando.value || !precioActualizado.value) return
  emit('guardar', ventanaLocal.value)
  cerrar()
}

const margenVenta = computed(() => {
  const mat = props.materiales?.find(m => m.id === ventanaLocal.value.material)
  return mat?.margen ?? 0.50
})

let debounceCalculo = null
let requestIdCalculo = 0

async function recalcularCostos() {
  const miRequestId = ++requestIdCalculo
  calculando.value = true
  precioActualizado.value = false

  const condicionesMet = ventanaLocal.value.tipo && ventanaLocal.value.ancho &&
    ventanaLocal.value.alto && ventanaLocal.value.cantidad &&
    ventanaLocal.value.color && ventanaLocal.value.productoVidrioProveedor

  if (!condicionesMet) {
    ventanaLocal.value.costo_total_unitario = 0
    ventanaLocal.value.costo_total = 0
    ventanaLocal.value.precio = 0
    ventanaLocal.value.materiales = []
    calculando.value = false
    return
  }

  try {
    const relacion = props.productosVidrio
      .filter(p => p.colores_por_proveedor && Array.isArray(p.colores_por_proveedor))
      .flatMap(p => p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
      })))
      .find(p => parseInt(p.id) === parseInt(ventanaLocal.value.productoVidrioProveedor))

    if (!relacion) {
      console.error('❌ Relación producto-proveedor no encontrada para ID:', ventanaLocal.value.productoVidrioProveedor)
      return
    }

    const payload = {
      tipo_ventana_id: ventanaLocal.value.tipo,
      tipo: ventanaLocal.value.tipo,
      ancho: ventanaLocal.value.ancho,
      alto: ventanaLocal.value.alto,
      cantidad: ventanaLocal.value.cantidad,
      color_id: ventanaLocal.value.color,
      color: ventanaLocal.value.color,
      producto_vidrio_proveedor_id: ventanaLocal.value.productoVidrioProveedor,
      producto_id: relacion.producto_id,
      proveedor_id: relacion.proveedor_id,
      productoVidrio: relacion.producto_id,
      proveedorVidrio: relacion.proveedor_id,
      tipoVidrio: ventanaLocal.value.tipoVidrio,
      hojas_totales: [3, 46, 52, 55].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.hojas_totales : undefined,
      hojas_moviles: [3, 46, 52, 55].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.hojas_moviles : undefined,
      hojaMovilSeleccionada: [3, 46, 52, 55].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.hojaMovilSeleccionada : undefined,
      hoja1AlFrente: [3, 46, 52].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.hoja1AlFrente : undefined,
      manillon: [52, 55].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.manillon : undefined,
      direccionApertura: ventanaLocal.value.direccionApertura,
      ladoApertura: ventanaLocal.value.ladoApertura,
      pasoLibre: [50, 51].includes(ventanaLocal.value.tipo) ? ventanaLocal.value.pasoLibre : undefined,
      hojaActiva: ventanaLocal.value.tipo === 51 ? ventanaLocal.value.hojaActiva : undefined,
      ...(ventanaLocal.value.tipo === 57 && {
        filas: ventanaLocal.value.filas,
        columnas: ventanaLocal.value.columnas,
        altos_filas: ventanaLocal.value.altosFilas,
        anchos_columnas: ventanaLocal.value.anchosColumnas,
        secciones: ventanaLocal.value.secciones,
      }),
      ...(ventanaLocal.value.tipo === 47 && {
        ancho_izquierda: ventanaLocal.value.ancho_izquierda,
        ancho_centro: ventanaLocal.value.ancho_centro,
        ancho_derecha: ventanaLocal.value.ancho_derecha,
        tipoVentanaIzquierda: ventanaLocal.value.tipoVentanaIzquierda,
        tipoVentanaCentro: ventanaLocal.value.tipoVentanaCentro,
        tipoVentanaDerecha: ventanaLocal.value.tipoVentanaDerecha,
      }),
    }

    const { data } = await api.post('/api/cotizador/calcular-materiales', payload)

    if (miRequestId !== requestIdCalculo) return

    ventanaLocal.value.costo_total_unitario = data.costo_unitario
    ventanaLocal.value.costo_total = data.costo_unitario * ventanaLocal.value.cantidad
    ventanaLocal.value.precio = Math.ceil(ventanaLocal.value.costo_total / (1 - margenVenta.value))
    ventanaLocal.value.materiales = data.materiales
    precioActualizado.value = true
  } catch (e) {
    if (miRequestId !== requestIdCalculo) return
    console.error('❌ Error en recalcularCostos:', e)
    ventanaLocal.value.costo_total_unitario = 0
    ventanaLocal.value.costo_total = 0
    ventanaLocal.value.precio = 0
    ventanaLocal.value.materiales = []
  } finally {
    if (miRequestId === requestIdCalculo) calculando.value = false
  }
}

watch(
  () => [
    ventanaLocal.value.ancho,
    ventanaLocal.value.alto,
    ventanaLocal.value.cantidad,
    ventanaLocal.value.color,
    ventanaLocal.value.productoVidrioProveedor,
  ],
  () => {
    calculando.value = true
    precioActualizado.value = false
    clearTimeout(debounceCalculo)
    debounceCalculo = setTimeout(recalcularCostos, 350)
  },
  { deep: true }
)

// Función para descargar materiales como CSV/Excel
const descargarMateriales = () => {
  if (!ventanaLocal.value.materiales || ventanaLocal.value.materiales.length === 0) {
    alert('No hay materiales para descargar')
    return
  }

  // Crear CSV
  const headers = ['Material', 'Proveedor', 'Cantidad', 'Unidad', 'Costo Unitario', 'Costo Total']
  const rows = ventanaLocal.value.materiales.map(m => [
    m.nombre || '',
    m.proveedor || '',
    m.cantidad || 0,
    m.unidad || '',
    m.costo_unitario || 0,
    m.costo_total || 0
  ])

  // Construir CSV
  let csvContent = headers.join(',') + '\n'
  rows.forEach(row => {
    csvContent += row.map(cell => {
      // Escapar comas y comillas
      const cellStr = String(cell).replace(/"/g, '""')
      return cellStr.includes(',') ? `"${cellStr}"` : cellStr
    }).join(',') + '\n'
  })

  // Crear blob y descargar
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  const tipoVentana = props.tiposVentana.find(t => t.id === ventanaLocal.value.tipo)?.nombre || 'ventana'
  const filename = `materiales_${tipoVentana}_${new Date().toISOString().split('T')[0]}.csv`
  
  link.setAttribute('href', url)
  link.setAttribute('download', filename)
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
</script>
