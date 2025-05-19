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
          <strong>Costo:</strong> ${{ ventana.costo_total }} —
          <strong>Precio:</strong> ${{ ventana.precio }}
        </v-alert>
      </v-col>
    </v-row>
  </v-card>
</template>

<script setup>
defineProps({
  ventana: Object,
  tiposVentana: Array,
  colores: Array,
  tiposVidrio: Array,
  productosVidrio: Array
})
</script>
