<template>
    <div>
      <v-btn @click="loadClientes" color="primary" class="mb-4">
        Cargar Clientes desde Bsale
      </v-btn>
      <v-btn color="success" class="mb-4" @click="importarTodos">
        Importar TODOS los clientes a la base
      </v-btn>
      <ClienteTable :clientes="clientes" :loading="loading" />
    </div>
  </template>
  
  <script setup>
  import { ref } from 'vue'
 import { importarTodosClientes, fetchBsaleClientes } from '@/api/clientes'
  import ClienteTable from '../ClienteTable.vue'
 
  
  const clientes = ref([])
  const loading = ref(false)
  


  const importarTodos = async () => {
  try {
    loading.value = true
    const res = await importarTodosClientes()
    alert(res.message)
    // Opcional: recargar tabla
    await loadClientes()
  } catch (e) {
    alert('❌ Error al importar todos los clientes')
  } finally {
    loading.value = false
  }
}

const loadClientes = async () => {
  loading.value = true
  const data = await fetchBsaleClientes()
  console.log('clientes:', data)

  if (Array.isArray(data)) {
    clientes.value = data
  } else {
    console.warn('⚠️ Los datos no son un array válido:', data)
    clientes.value = []
  }

  loading.value = false
}

   </script>
  