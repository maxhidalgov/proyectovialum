<template>
  <v-card variant="outlined" class="mb-4">
    <!-- Cabecera de sección -->
    <div class="d-flex align-center justify-space-between px-4 pt-3 pb-2">
      <div class="d-flex align-center gap-2">
        <v-icon color="primary" size="small">mdi-window-open</v-icon>
        <span class="text-body-2 font-weight-semibold text-medium-emphasis text-uppercase tracking-wide">
          Configuración de ventana
        </span>
      </div>
      <v-chip v-if="calculando" size="x-small" color="warning" variant="tonal">
        <v-progress-circular indeterminate size="10" width="2" class="mr-1" />
        Calculando...
      </v-chip>
      <v-chip v-else-if="ventana.precio" size="x-small" color="success" variant="tonal">
        <v-icon start size="12">mdi-check-circle</v-icon>
        Calculado
      </v-chip>
    </div>

    <v-divider />

    <div class="pa-4">
      <!-- Fila 1: Tipo de ventana + Dimensiones + Cantidad -->
      <v-row dense class="mb-3">
        <v-col cols="12" sm="5">
          <v-select
            v-model="ventana.tipo_ventana_id"
            :items="tiposVentana"
            item-title="nombre"
            item-value="id"
            label="Tipo de ventana"
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-window-open-variant"
            :color="isChanged('tipo_ventana_id') ? 'warning' : undefined"
          />
        </v-col>
        <v-col cols="5" sm="3">
          <v-text-field
            v-model="ventana.ancho"
            label="Ancho (mm)"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            :color="isChanged('ancho') ? 'warning' : undefined"
            :append-inner-icon="isChanged('ancho') ? 'mdi-pencil' : undefined"
          />
        </v-col>
        <v-col cols="5" sm="3">
          <v-text-field
            v-model="ventana.alto"
            label="Alto (mm)"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            :color="isChanged('alto') ? 'warning' : undefined"
            :append-inner-icon="isChanged('alto') ? 'mdi-pencil' : undefined"
          />
        </v-col>
        <v-col cols="2" sm="1">
          <v-text-field
            v-model.number="ventana.cantidad"
            label="Cant."
            type="number"
            min="1"
            variant="outlined"
            density="compact"
            hide-details
            :rules="[v => v > 0 || 'Debe ser mayor a 0']"
          />
        </v-col>
      </v-row>

      <!-- Fila 2: Color + Tipo vidrio + Producto vidrio -->
      <v-row dense>
        <v-col cols="12" sm="3">
          <v-select
            v-model="ventana.color_id"
            :items="colores"
            item-title="nombre"
            item-value="id"
            label="Color"
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-palette"
            :color="isChanged('color_id') ? 'warning' : undefined"
            :append-inner-icon="isChanged('color_id') ? 'mdi-pencil' : undefined"
          />
        </v-col>
        <v-col cols="12" sm="3">
          <v-select
            v-model="ventana.tipo_vidrio_id"
            :items="tiposVidrio"
            item-title="nombre"
            item-value="id"
            label="Tipo de vidrio"
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-layers"
            :color="isChanged('tipo_vidrio_id') ? 'warning' : undefined"
            :append-inner-icon="isChanged('tipo_vidrio_id') ? 'mdi-pencil' : undefined"
          />
        </v-col>
        <v-col cols="12" sm="6">
          <v-select
            v-model="ventana.producto_vidrio_proveedor_id"
            :items="productosVidrio"
            item-title="nombre"
            item-value="id"
            label="Producto de vidrio"
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-package-variant"
            :color="isChanged('producto_vidrio_proveedor_id') ? 'warning' : undefined"
            :append-inner-icon="isChanged('producto_vidrio_proveedor_id') ? 'mdi-pencil' : undefined"
          />
        </v-col>
      </v-row>

      <!-- Fila 3 (solo Sliding): Hojas -->
      <v-row v-if="ventana.tipo_ventana_id === 3 || ventana.tipo_ventana_id === 46" dense class="mt-3">
        <v-col cols="6" sm="3">
          <v-select
            v-model="ventana.hojas_totales"
            :items="[2, 3, 4, 6]"
            label="Hojas totales"
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-view-column"
          />
        </v-col>
        <v-col cols="6" sm="3">
          <v-select
            v-model="ventana.hojas_moviles"
            :items="[1, 2, 3, 4]"
            label="Hojas móviles"
            variant="outlined"
            density="compact"
            hide-details
            :disabled="!ventana.hojas_totales"
            :rules="[v => !v || v <= ventana.hojas_totales || 'No puede exceder total']"
            prepend-inner-icon="mdi-arrow-left-right"
          />
        </v-col>
      </v-row>

      <!-- Fila 4: Costos — siempre visible -->
      <v-row dense class="mt-4">
        <v-col cols="4">
          <div class="rounded-lg pa-2 bg-surface-variant text-center">
            <div class="text-caption text-medium-emphasis mb-1">Costo unitario</div>
            <div v-if="calculando" class="d-flex justify-center">
              <v-progress-linear indeterminate color="warning" height="2" rounded />
            </div>
            <div v-else class="text-body-2 font-weight-bold">
              {{ ventana.costo_unitario ? '$' + fmt(ventana.costo_unitario) : '—' }}
            </div>
          </div>
        </v-col>
        <v-col cols="4">
          <div class="rounded-lg pa-2 bg-surface-variant text-center">
            <div class="text-caption text-medium-emphasis mb-1">Costo total</div>
            <div v-if="calculando" class="d-flex justify-center">
              <v-progress-linear indeterminate color="warning" height="2" rounded />
            </div>
            <div v-else class="text-body-2 font-weight-bold">
              {{ ventana.costo_total ? '$' + fmt(ventana.costo_total) : '—' }}
            </div>
          </div>
        </v-col>
        <v-col cols="4">
          <div class="rounded-lg pa-2 text-center" style="background: rgba(var(--v-theme-success), 0.1)">
            <div class="text-caption text-medium-emphasis mb-1">Precio venta</div>
            <div v-if="calculando" class="d-flex justify-center">
              <v-progress-linear indeterminate color="success" height="2" rounded />
            </div>
            <div v-else class="text-body-1 font-weight-bold text-success">
              {{ ventana.precio ? '$' + fmt(ventana.precio) : '—' }}
            </div>
          </div>
        </v-col>
      </v-row>
    </div>
  </v-card>
</template>

<script setup>
import { ref, watch } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'

const isChanged = (campo) => {
  return props.ventana.original?.[campo] !== props.ventana[campo]
}

const fmt = (n) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(n) || 0)

const props = defineProps({
  ventana: Object,
  tiposVentana: Array,
  colores: Array,
  tiposVidrio: Array,
  productosVidrio: Array
})

const calculando = ref(false)

// Ejecutar recálculo cuando los productos se carguen Y la ventana tenga datos
watch(
  () => props.productosVidrio.length,
  (newLength) => {
    if (newLength > 0 &&
        props.ventana.tipo_ventana_id &&
        props.ventana.ancho &&
        props.ventana.alto &&
        props.ventana.producto_vidrio_proveedor_id) {
      recalcularCosto()
    }
  },
  { immediate: true }
)

const margenVenta = 0.45

const recalcularCosto = debounce(async () => {
  const productoSeleccionado = props.productosVidrio.find(
    p => p.id === props.ventana.producto_vidrio_proveedor_id
  )

  const payload = {
    tipo: props.ventana.tipo_ventana_id,
    ancho: props.ventana.ancho,
    alto: props.ventana.alto,
    cantidad: props.ventana.cantidad,
    material: 2,
    color: props.ventana.color_id,
    tipoVidrio: props.ventana.tipo_vidrio_id,
    productoVidrioProveedor: productoSeleccionado.id,
    productoVidrio: productoSeleccionado.producto_id,
    proveedorVidrio: productoSeleccionado.proveedor_id,
    hojas_totales: props.ventana.hojas_totales,
    hojas_moviles: props.ventana.tipo_ventana_id === 3 || props.ventana.tipo_ventana_id === 46 ? props.ventana.hojas_moviles : undefined,
  }

  calculando.value = true
  try {
    const { data } = await api.post('/api/cotizador/calcular-materiales', payload)
    const cantidad = Number(props.ventana.cantidad) || 1

    props.ventana.costo_unitario = data.costo_unitario
    props.ventana.costo_total = data.costo_total
    props.ventana.precio = Math.ceil(data.costo_unitario / (1 - margenVenta)) * cantidad
    props.ventana.materiales = data.materiales
  } catch (error) {
    console.error('❌ Error al calcular materiales', error)
  } finally {
    calculando.value = false
  }
}, 800)

watch(
  () => [
    props.ventana.tipo_ventana_id,
    props.ventana.ancho,
    props.ventana.alto,
    props.ventana.cantidad,
    props.ventana.color_id,
    props.ventana.tipo_vidrio_id,
    props.ventana.producto_vidrio_proveedor_id,
    props.ventana.hojas_totales,
    props.ventana.hojas_moviles
  ],
  () => {
    if (
      props.ventana.tipo_ventana_id &&
      props.ventana.ancho &&
      props.ventana.alto &&
      props.ventana.producto_vidrio_proveedor_id &&
      Number(props.ventana.cantidad) > 0
    ) {
      recalcularCosto()
    }
  },
  { deep: true }
)
</script>
