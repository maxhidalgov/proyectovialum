<template>
    <v-card>
      <v-card-title>
        Cotizaciones
        <v-spacer />
        <v-btn color="primary" @click="router.push({ name: 'cotizador' })">Nueva</v-btn>
      </v-card-title>
  
      <v-data-table
        :headers="headers"
        :items="cotizaciones"
        :items-per-page="10"
        class="elevation-1"
      >
        <template #item.cliente="{ item }">
          {{ item.cliente?.razon_social || '—' }}
        </template>
  
        <template #item.vendedor="{ item }">
          {{ item.vendedor?.name || '—' }}
        </template>
  
        <template #item.estado="{ item }">
          <v-chip :color="getEstadoColor(item.estado?.nombre)">
            {{ item.estado?.nombre || '—' }}
          </v-chip>
        </template>
  
        <template #item.acciones="{ item }">
          <v-btn icon @click="verCotizacion(item)">
            <v-icon>mdi-eye</v-icon>
          </v-btn>
          <v-btn
            icon
            @click="editarCotizacion(item)"
            :disabled="item.estado?.nombre === 'Aprobada'"
          >
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon @click="descargarPDF(item.id)">
            <v-icon>mdi-file-pdf-box</v-icon>
            </v-btn>
            <v-btn icon @click="duplicarCotizacion(item)">
            <v-icon>mdi-content-copy</v-icon>
            </v-btn>

        </template>
      </v-data-table>
    </v-card>
  </template>
  
  <script setup>
  import { ref, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import api from '@/axiosInstance'
  
  const router = useRouter()
  const cotizaciones = ref([])
  
  const headers = [
    { title: 'ID', value: 'id' },
    { title: 'Cliente', value: 'cliente' },
    { title: 'Vendedor', value: 'vendedor' },
    { title: 'Fecha', value: 'fecha' },
    { title: 'Estado', value: 'estado' },
    { title: 'Acciones', value: 'acciones', sortable: false },
  ]
  
  const getEstadoColor = (estadoNombre) => {
    switch (estadoNombre) {
      case 'Evaluación': return 'grey'
      case 'Aprobada': return 'green'
      case 'Rechazada': return 'red'
      default: return 'blue'
    }
  }
  
  const verCotizacion = (item) => {
    router.push(`/cotizacion-ver?id=${item.id}`)
  }
  
  const editarCotizacion = (item) => {
    router.push(`/cotizador?id=${item.id}`)
    }

    const duplicarCotizacion = async (item) => {
  if (!confirm(`¿Deseas duplicar la cotización #${item.id}?`)) return

  try {
    const res = await api.post(`/api/cotizaciones/${item.id}/duplicar`)
    alert('Cotización duplicada con éxito')
    router.push(`/cotizador?id=${res.data.id}`)
  } catch (error) {
    console.error(error)
    alert('Error al duplicar la cotización')
  }
}

const descargarPDF = (cotizacionId) => {
  const baseURL = window.location.hostname === 'localhost' 
    ? 'http://localhost:8000' 
    : 'https://proyectovialum-production.up.railway.app'
  window.open(`${baseURL}/api/cotizaciones/${cotizacionId}/pdf`, '_blank')
}
  
  onMounted(async () => {
    try {
      const res = await api.get('/api/cotizaciones')
      cotizaciones.value = Array.isArray(res.data) ? res.data : []
    } catch (error) {
      console.error('Error al cargar cotizaciones:', error)
      cotizaciones.value = []
    }
  })
  </script>
  