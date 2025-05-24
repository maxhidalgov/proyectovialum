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
              v-model="nuevaVentana.tipo_ventana_id"
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

          <template v-if="nuevaVentana.tipo_ventana_id === 3">
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
              v-model="nuevaVentana.color_id"
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
              v-model="nuevaVentana.tipo_vidrio_id"
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
              v-model="nuevaVentana.producto_vidrio_proveedor_id"
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
  tipo_ventana_id: null,  // antes: tipo
  ancho: null,
  alto: null,
  material: null,
  color: null,
  tipo_vidrio_id: null,  // antes: tipoVidrio
  producto_vidrio_proveedor_id: null,  // antes: productoVidrioProveedor
  hojas_totales: 2,
  hojas_moviles: 2,
})

const tiposVentanaFiltrados = computed(() => {
  return props.tiposVentana?.filter(t => t.material_id === nuevaVentana.value.material) || []
})

const productosVidrioFiltrados = computed(() => {
  const tipo = nuevaVentana.value.tipo_vidrio_id
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
  const productoVidrio = props.productosVidrio
    .flatMap(p =>
      p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        producto: p,
        proveedor: cpp.proveedor,
        ...cpp
      }))
    )
    .find(p => p.id === nuevaVentana.value.producto_vidrio_proveedor_id)

 emit('agregar', {
  ...nuevaVentana.value,
  producto_vidrio_proveedor: productoVidrio,
  tipo_ventana: props.tiposVentana.find(t => t.id === nuevaVentana.value.tipo_ventana_id),
  color_obj: props.colores.find(c => c.id === nuevaVentana.value.color),
})

  modalAgregarVentana.value = false
}

watch(() => props.modelValue, val => {
  if (!val) {
    Object.assign(nuevaVentana.value, {
      tipo_ventana_id: null,
      ancho: null,
      alto: null,
      material: null,
      color: null,
      tipo_vidrio_id: null,
      producto_vidrio_proveedor_id: null,
      hojas_totales: 2,
      hojas_moviles: 2
    })
  }
})
</script>
