<template>
  <v-card class="pa-4 mb-4" outlined>
    <v-row dense>
      <v-col cols="12" sm="6">
        <v-select
          v-model="ventana.tipo_ventana_id"
          :items="tiposVentana"
          item-title="nombre"
          item-value="id"
          label="Tipo de ventana"
        />
      </v-col>

      <v-col cols="6" sm="3">
        <v-text-field
          v-model="ventana.ancho"
          label="Ancho (mm)"
          type="number"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-text-field
          v-model="ventana.alto"
          label="Alto (mm)"
          type="number"
          outlined
          color="primary"
        />
      </v-col>

      <v-col cols="12" sm="3">
        <v-select
          v-model="ventana.color_id"
          :items="colores"
          item-title="nombre"
          item-value="id"
          label="Color"
        />
      </v-col>

      <v-col cols="12" sm="3">
        <v-select
          v-model="ventana.tipo_vidrio_id"
          :items="tiposVidrio"
          item-title="nombre"
          item-value="id"
          label="Tipo de vidrio"
        />
      </v-col>

      <v-col cols="12" sm="3">
        <v-select
          v-model="ventana.producto_vidrio_proveedor_id"
          :items="productosVidrio"
          item-title="nombre"
          item-value="id"
          label="Producto de vidrio"
        />
      </v-col>

      <!-- Sliding específica -->
      <template v-if="ventana.tipo === 3">
        <v-col cols="6" sm="3">
          <v-select
            v-model="ventana.hojas_totales"
            :items="[2, 3, 4, 6]"
            label="Hojas totales"
            outlined
            color="primary"
          />
        </v-col>
        <v-col cols="6" sm="3">
          <v-select
            v-model="ventana.hojas_moviles"
            :items="[1, 2, 3, 4]"
            label="Hojas móviles"
            :disabled="!ventana.hojas_totales"
            :rules="[v => !v || v <= ventana.hojas_totales || 'No puede exceder total']"
            outlined
            color="primary"
          />
        </v-col>
      </template>

      <v-col cols="12" v-if="ventana.costo_total">
        <v-alert type="info" variant="outlined">
          <strong>Costo:</strong> {{ ventana.costo_total?.toLocaleString('es-CL') }} —
          <strong>Precio:</strong> {{ ventana.precio?.toLocaleString('es-CL') }}
        </v-alert>
      </v-col>
    </v-row>
  </v-card>
</template>

<script setup>
import { watch } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'

const props = defineProps({
  ventana: Object,
  tiposVentana: Array,
  colores: Array,
  tiposVidrio: Array,
  productosVidrio: Array
})

const margenVenta = 0.45

const recalcularCosto = debounce(async () => {
  const productoSeleccionado = props.productosVidrio.find(
    p => p.id === props.ventana.producto_vidrio_proveedor_id
  )

  if (!productoSeleccionado) return

  const payload = {
    tipo: props.ventana.tipo_ventana_id,
    ancho: props.ventana.ancho,
    alto: props.ventana.alto,
    material: 2, // o el material correspondiente
    color: props.ventana.color_id,
    tipoVidrio: props.ventana.tipo_vidrio_id,
    productoVidrioProveedor: props.ventana.producto_vidrio_proveedor_id,
    productoVidrio: productoSeleccionado.producto_id,
    proveedorVidrio: productoSeleccionado.proveedor_id,
    hojas_totales: props.ventana.hojas_totales || 2,
    hojas_moviles: props.ventana.tipo_ventana_id === 3 ? props.ventana.hojas_moviles : undefined,
  }

  try {
    const { data } = await api.post('/api/cotizador/calcular-materiales', payload)
    props.ventana.costo_total = data.costo_total
    props.ventana.precio = Math.ceil(data.costo_total / (1 - margenVenta))
    props.ventana.materiales = data.materiales

    // ✅ También puedes actualizar en BD si lo deseas:
    if (props.ventana.id) {
      await api.put(`/api/ventanas/${props.ventana.id}`, {
        ...props.ventana,
        costo: props.ventana.costo_total,
        precio: props.ventana.precio,
      })
    }
  } catch (error) {
    console.error('❌ Error al calcular materiales', error)
  }
}, 800)

watch(
  () => [
    props.ventana.tipo_ventana_id,
    props.ventana.ancho,
    props.ventana.alto,
    props.ventana.color_id,
    props.ventana.tipo_vidrio_id,
    props.ventana.producto_vidrio_proveedor_id
  ],
  () => {
    if (
      props.ventana.tipo_ventana_id &&
      props.ventana.ancho &&
      props.ventana.alto &&
      props.ventana.producto_vidrio_proveedor_id
    ) {
      recalcularCosto()
    }
  },
  { deep: true }
)
</script>
