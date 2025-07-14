<template>
  <v-container fluid>
    <v-row class="mb-4">
      <v-col cols="6" md="3">
        <v-select
          v-model="mesSeleccionado"
          :items="meses"
          item-title="text"
          item-value="value"
          label="Mes"
          dense
          outlined
        />
      </v-col>
      <v-col cols="6" md="3">
        <v-select
          v-model="anioSeleccionado"
          :items="anios"
          label="Año"
          dense
          outlined
        />
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="6">
        <v-card class="pa-4" elevation="2">
          <v-card-title class="text-h6">Total Ventas del Mes</v-card-title>
          <v-card-text class="text-h5 font-weight-bold">
            {{ formatoPesos(totalMes) }}
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card class="pa-4" elevation="2">
          <v-card-title class="text-h6">Documentos Emitidos</v-card-title>
          <v-card-text class="text-h5 font-weight-bold">
            {{ cantidadDocs }}
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card class="pa-4 mt-4" elevation="2">
          <v-card-title class="text-h6">Ventas Diarias (Gráfico)</v-card-title>
          <v-card-text>
            <canvas ref="chartCanvas" height="100"></canvas>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import api from '@/axiosInstance'
import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Title,
} from 'chart.js'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Title)

const totalMes = ref(0)
const cantidadDocs = ref(0)
const chartCanvas = ref(null)
let chartInstance = null

const formatoPesos = valor => typeof valor === 'number' ? '$' + valor.toLocaleString('es-CL') : '$0'

const meses = [
  { text: 'Enero', value: 1 },
  { text: 'Febrero', value: 2 },
  { text: 'Marzo', value: 3 },
  { text: 'Abril', value: 4 },
  { text: 'Mayo', value: 5 },
  { text: 'Junio', value: 6 },
  { text: 'Julio', value: 7 },
  { text: 'Agosto', value: 8 },
  { text: 'Septiembre', value: 9 },
  { text: 'Octubre', value: 10 },
  { text: 'Noviembre', value: 11 },
  { text: 'Diciembre', value: 12 },
]

const anios = [2023, 2024, 2025]
const mesSeleccionado = ref(new Date().getMonth() + 1)
const anioSeleccionado = ref(new Date().getFullYear())


const cargarDatos = async () => {
  try {
    const res = await api.get('/api/dashboard/ventas-mensuales', {
      params: {
        mes: mesSeleccionado.value,
        anio: anioSeleccionado.value,
      },
    })

    console.log('Respuesta API:', res.data)

    totalMes.value = res.data.total_mes ?? 0
    cantidadDocs.value = res.data.cantidad ?? 0

    const labels = res.data.labels || []
    const diarias = res.data.diarias || []

    if (chartInstance) chartInstance.destroy()

    chartInstance = new Chart(chartCanvas.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Ventas Diarias',
            data: diarias,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => '$' + value.toLocaleString('es-CL'),
            },
          },
        },
      },
    })
  } catch (error) {
    console.error('Error al cargar dashboard:', error)
  }
}

onMounted(cargarDatos)
watch([mesSeleccionado, anioSeleccionado], cargarDatos)
</script>