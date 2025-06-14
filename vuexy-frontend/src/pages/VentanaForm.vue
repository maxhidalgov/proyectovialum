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
          :color="isChanged('ancho') ? 'warning' : undefined"
          :append-inner-icon="isChanged('ancho') ? 'mdi-pencil' : ''"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-text-field
          v-model="ventana.alto"
          label="Alto (mm)"
          type="number"
          outlined
          :color="isChanged('alto') ? 'warning' : undefined"
          :append-inner-icon="isChanged('alto') ? 'mdi-pencil' : ''"
        />
      </v-col>

    <v-col cols="12" sm="3">
      <v-text-field
        v-model="ventana.cantidad"
        label="Cantidad"
        type="number"
        min="1"
        :rules="[v => v > 0 || 'Debe ser mayor a 0']"
      />
    </v-col>

      <v-col cols="12" sm="3">
        <v-select
          v-model="ventana.color_id"
          :items="colores"
          item-title="nombre"
          item-value="id"
          label="Color"
          :color="isChanged('color_id') ? 'warning' : undefined"
          :append-inner-icon="isChanged('color_id') ? 'mdi-pencil' : ''"
        />
      </v-col>

      <v-col cols="12" sm="3">
        <!-- Tipo de vidrio -->
        <v-select
          v-model="ventana.tipo_vidrio_id"
          :items="tiposVidrio"
          item-title="nombre"
          item-value="id"
          label="Tipo de vidrio"
          :color="isChanged('tipo_vidrio_id') ? 'warning' : undefined"
          :append-inner-icon="isChanged('tipo_vidrio_id') ? 'mdi-pencil' : ''"
        />
      </v-col>

      <v-col cols="12" sm="3">
      <!-- Producto de vidrio -->
      <v-select
        v-model="ventana.producto_vidrio_proveedor_id"
        :items="productosVidrio"
        item-title="nombre"
        item-value="id"
        label="Producto de vidrio"
        :color="isChanged('producto_vidrio_proveedor_id') ? 'warning' : undefined"
        :append-inner-icon="isChanged('producto_vidrio_proveedor_id') ? 'mdi-pencil' : ''"
      />
      </v-col>

      <!-- Sliding específica -->
      <template v-if="ventana.tipo_ventana_id === 3 || ventana.tipo_ventana_id === 46">
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
        <strong>Costo unitario:</strong> {{ ventana.costo_unitario?.toLocaleString('es-CL') }} —
        <strong>Costo total:</strong> {{ ventana.costo_total?.toLocaleString('es-CL') }} —
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
import { can } from '@/@layouts/plugins/casl'

const isChanged = (campo) => {
  return props.ventana.original?.[campo] !== props.ventana[campo]
}

const props = defineProps({
  ventana: Object,
  tiposVentana: Array,
  colores: Array,
  tiposVidrio: Array,
  productosVidrio: Array // <- aquí lo estás recibiendo como 'productosVidrioFiltrados'
})

const margenVenta = 0.45

const recalcularCosto = debounce(async () => {
  const productoSeleccionado = props.productosVidrio.find(
  p => p.id === props.ventana.producto_vidrio_proveedor_id
)

const payload = {
  // ...
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

  try {
    console.log(payload)
    const { data } = await api.post('/api/cotizador/calcular-materiales', payload)
    const cantidad = Number(props.ventana.cantidad) || 1

  props.ventana.costo_unitario = data.costo_unitario
  props.ventana.costo_total = data.costo_total
  props.ventana.precio = Math.ceil(data.costo_unitario / (1 - margenVenta)) * cantidad
  props.ventana.materiales = data.materiales


  } catch (error) {
    console.error('❌ Error al calcular materiales', error)
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
