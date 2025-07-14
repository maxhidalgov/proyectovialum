<template>
  <v-card class="pa-4" elevation="2">
    <v-card-title class="text-h6">Compras Mensuales (Terceros)</v-card-title>

    <v-row dense class="mb-2">
      <v-col cols="6" md="4">
        <v-select
          v-model="mes"
          :items="meses"
          label="Mes"
          item-title="title"
          item-value="value"
          density="compact"
          hide-details
        />
      </v-col>
      <v-col cols="6" md="4">
        <v-select
          v-model="anio"
          :items="anios"
          label="Año"
          density="compact"
          hide-details
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-btn color="primary" @click="cargarCompras" :loading="loading">
          Consultar
        </v-btn>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" class="mb-2">
      {{ error }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="6">
        <v-alert type="info" variant="tonal" class="mb-4">
          <strong>Total Compras del Mes:</strong>
          ${{ totalMes.toLocaleString() }}
        </v-alert>
        <v-alert type="info" variant="tonal" class="mb-2">
          <strong>Cantidad de Documentos:</strong> {{ cantidad }}
        </v-alert>
      </v-col>
    </v-row>
  </v-card>

  <v-card class="mt-6" elevation="2">
    <v-card-title>Resumen por Proveedor</v-card-title>
<v-data-table
  :items="proveedoresTabla"
  :headers="headers"
  class="elevation-1"
>
  <template #headers="{ columns }">
    <tr>
      <th v-for="column in columns" :key="column.key" class="text-white text-left">
        {{ column.title }}
      </th>
    </tr>
  </template>

  <template #item.total="{ item }">
    ${{ item.total.toLocaleString() }}
  </template>
</v-data-table>
  </v-card>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/axiosInstance'

const mes = ref(new Date().getMonth() + 1)
const anio = ref(new Date().getFullYear())
const headers = [
  { title: 'Proveedor', key: 'proveedor' },
  { title: 'Documentos', key: 'cantidad' },
  { title: 'Total Comprado', key: 'total' },
]

const meses = [
  { title: 'Enero', value: 1 },
  { title: 'Febrero', value: 2 },
  { title: 'Marzo', value: 3 },
  { title: 'Abril', value: 4 },
  { title: 'Mayo', value: 5 },
  { title: 'Junio', value: 6 },
  { title: 'Julio', value: 7 },
  { title: 'Agosto', value: 8 },
  { title: 'Septiembre', value: 9 },
  { title: 'Octubre', value: 10 },
  { title: 'Noviembre', value: 11 },
  { title: 'Diciembre', value: 12 },
]

const anios = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const totalMes = ref(0)
const cantidad = ref(0)
const loading = ref(false)
const error = ref('')
const resultado = ref({})
const respuesta = ref({})
const labels = ref([])
const diarias = ref([])

const cargarCompras = async () => {
  loading.value = true
  error.value = ''

  try {
    const res = await api.get('/api/dashboard/compras-terceros-mensuales', {
      params: {
        mes: mes.value,
        anio: anio.value,
        proveedor_id: proveedorSeleccionado.value,
      }
    })

    const data = res.data
    totalMes.value = data.total_mes
    cantidad.value = data.cantidad
    labels.value = data.labels
    diarias.value = data.diarias
    respuesta.value = data

  } catch (err) {
    error.value = 'Error al cargar las compras: ' + err.message
  } finally {
    loading.value = false
  }
}



const proveedoresTabla = computed(() => {
  if (!respuesta.value?.proveedores) return [];
  return Object.entries(respuesta.value.proveedores).map(([proveedor, data]) => ({
    proveedor, // ahora es el nombre como "VIDRIERÍA CHILE LTDA."
    ...data,
  }));
});



onMounted(() => {
  cargarCompras()
})
</script>
