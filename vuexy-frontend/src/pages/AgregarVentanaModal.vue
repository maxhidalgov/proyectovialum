<template>
  <v-dialog v-model="modalAgregarVentana" max-width="900px">
    <v-card>
      <v-card-title class="text-h6">Agregar Ventana</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="nuevaVentana.material"
              :items="materiales"
              item-title="nombre"
              item-value="id"
              label="Material"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="nuevaVentana.tipo"
              :items="tiposVentanaFiltrados"
              item-title="nombre"
              item-value="id"
              label="Tipo de ventana"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="nuevaVentana.ancho" label="Ancho (mm)" type="number" outlined color="primary" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="nuevaVentana.alto" label="Alto (mm)" type="number" outlined color="primary" />
          </v-col>

          <template v-if="nuevaVentana.tipo === 3">
            <v-col cols="6" sm="3">
              <v-select v-model="nuevaVentana.hojas_totales" :items="[2, 3, 4, 6]" label="Hojas totales" outlined color="primary" />
            </v-col>
            <v-col cols="6" sm="3">
              <v-select
                v-model="nuevaVentana.hojas_moviles"
                :items="[1, 2, 3, 4]"
                label="Hojas móviles"
                :disabled="!nuevaVentana.hojas_totales"
                :rules="[v => !v || v <= nuevaVentana.hojas_totales || 'No puede exceder total']"
                outlined
                color="primary"
              />
            </v-col>
          </template>

          <v-col cols="12" sm="6">
            <v-select
              v-model="nuevaVentana.color"
              :items="colores"
              item-title="nombre"
              item-value="id"
              label="Color"
              outlined
              color="primary"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              v-model="nuevaVentana.tipoVidrio"
              :items="tiposVidrio"
              item-title="nombre"
              item-value="id"
              label="Tipo de vidrio"
              outlined
              color="primary"
            />
          </v-col>

          <v-col cols="12">
            <v-select
              v-model="nuevaVentana.productoVidrioProveedor"
              :items="productosVidrioFiltrados"
              item-title="nombre"
              item-value="id"
              label="Producto de vidrio"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn text @click="modalAgregarVentana = false">Cancelar</v-btn>
        <v-btn color="primary" @click="emitirVentana">Agregar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>


<script setup>
import { computed, reactive, watch } from 'vue'
import { defineEmits, defineProps } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  materiales: Array,
  colores: Array,
  tiposVidrio: Array,
  tiposVentana: {
    type: Array,
    default: () => []
  },
  productosVidrio: Array
})

const emit = defineEmits(['update:modelValue', 'agregar'])

const modalAgregarVentana = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value)
})


const nuevaVentana = ref({
  tipo: null,
  ancho: null,
  alto: null,
  material: null,
  color: null,
  tipoVidrio: null,
  productoVidrioProveedor: null,
  hojas_totales: 2,
  hojas_moviles: 2,
})

const tiposVentanaFiltrados = computed(() => {
  return props.tiposVentana?.filter(t => t.material_id === nuevaVentana.value.material) || []
})

const productosVidrioFiltrados = computed(() => {
  const tipo = nuevaVentana.value.tipoVidrio
  return props.productosVidrio
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p =>
      p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Desconocido'})`
      }))
    )
})

const emitirVentana = () => {
  emit('agregar', { ...nuevaVentana.value })
  modalAgregarVentana.value = false
}

watch(() => props.modelValue, val => {
  if (!val) {
    Object.assign(nuevaVentana.value, {
      tipo: null,
      ancho: null,
      alto: null,
      material: null,
      color: null,
      tipoVidrio: null,
      productoVidrioProveedor: null,
      hojas_totales: 2,
      hojas_moviles: 2
    })
  }
})
</script>
