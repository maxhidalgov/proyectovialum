<template>
    <div>
      <v-card>
        <v-card-title>Lista de Productos</v-card-title>
        <v-data-table :items="productos" :headers="headers" class="elevation-1">
          <template #item.actions="{ item }">
            <v-btn icon @click="$emit('editar', item)">
              <v-icon>mdi-pencil</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted } from 'vue'
  import axios from 'axios'
  
  const productos = ref([])
  const headers = [
    { title: 'Nombre', key: 'nombre' },
    { title: 'Tipo', key: 'tipo' },
    { title: 'Acciones', key: 'actions', sortable: false }
  ]
  
  const cargarProductos = async () => {
    const response = await axios.get('/api/productos')
    productos.value = response.data
  }
  
  onMounted(cargarProductos)
  </script>
  