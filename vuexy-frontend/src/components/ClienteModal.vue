<!-- src/components/clientes/ClienteModal.vue -->
<template>
    <v-dialog v-model="show" max-width="600px">
      <v-card>
        <v-card-title class="text-h6">Agregar Cliente</v-card-title>
        <v-card-text>
          <v-text-field v-model="cliente.firstName" label="Nombre de contacto" />
          <v-text-field v-model="cliente.lastName" label="Apellido de contacto" />
          <v-text-field v-model="cliente.company" label="Razón Social" />
          <v-text-field v-model="cliente.code" label="RUT o Código" />
          <v-text-field v-model="cliente.email" label="Correo" />
          <v-text-field v-model="cliente.phone" label="Teléfono" />
          <v-text-field v-model="cliente.city" label="Ciudad" />
          <v-text-field v-model="cliente.municipality" label="Comuna" />
          <v-text-field v-model="cliente.address" label="Dirección" />
          <v-text-field v-model="cliente.activity" label="Giro" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="blue" @click="guardarCliente">Guardar</v-btn>
          <v-btn color="grey" @click="show = false">Cancelar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </template>
  
  <script setup>
  import { ref, defineExpose } from 'vue'
  import api from '@/axiosInstance'
  
  const show = ref(false)
  
  const cliente = ref({
    firstName: '',
    lastName: '',
    company: '',
    code: '',
    email: '',
    phone: '',
    city: '',
    municipality: '',
    address: '',
    activity: '',
  })
  
  const emit = defineEmits(['cliente-creado'])
  
  function abrir() {
    show.value = true
  }
  
  async function guardarCliente() {
    try {
      const res = await api.post('/api/bsale-clientes', cliente.value)
      emit('cliente-creado', res.data.cliente)
      show.value = false
    } catch (e) {
      console.error('❌ Error al guardar cliente:', e)
      alert('Error al guardar cliente')
    }
  }
  
  defineExpose({ abrir })
  </script>
  