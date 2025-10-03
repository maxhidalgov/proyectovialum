<template>
  <v-dialog v-model="localMostrar" max-width="600px" persistent>
    <v-card>
      <v-card-title class="text-h6">
        <v-icon class="me-2">mdi-account-plus</v-icon>
        Crear Cliente en BSALE
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-form ref="formRef" @submit.prevent="crearCliente">
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.nombre"
                label="Nombre *"
                :rules="[v => !!v || 'Requerido']"
                variant="outlined"
                prepend-inner-icon="mdi-account"
                required
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.apellido"
                label="Apellido"
                variant="outlined"
                prepend-inner-icon="mdi-account"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.empresa"
                label="Empresa"
                variant="outlined"
                prepend-inner-icon="mdi-office-building"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.rut"
                label="RUT"
                variant="outlined"
                prepend-inner-icon="mdi-card-account-details"
                placeholder="12345678-9"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.giro"
                label="Giro"
                variant="outlined"
                prepend-inner-icon="mdi-briefcase"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.email"
                label="Email"
                type="email"
                variant="outlined"
                prepend-inner-icon="mdi-email"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.telefono"
                label="Teléfono"
                variant="outlined"
                prepend-inner-icon="mdi-phone"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.direccion"
                label="Dirección"
                variant="outlined"
                prepend-inner-icon="mdi-map-marker"
              />
            </v-col>
            <v-col cols="12">
              <v-switch
                v-model="form.enviar_email"
                label="Enviar email de confirmación"
                color="primary"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          variant="text"
          @click="cerrarModal"
          :disabled="loading"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          @click="crearCliente"
          :loading="loading"
        >
          <v-icon start>mdi-account-plus</v-icon>
          Crear Cliente
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/axiosInstance'

const props = defineProps({
  mostrar: Boolean
})

const emit = defineEmits(['update:mostrar', 'cliente-creado'])

const localMostrar = ref(props.mostrar)
watch(() => props.mostrar, val => { localMostrar.value = val })
watch(localMostrar, val => { emit('update:mostrar', val) })

const loading = ref(false)
const formRef = ref()

const form = ref({
  nombre: '',
  apellido: '',
  empresa: '',
  rut: '',
  giro: '',
  email: '',
  telefono: '',
  direccion: '',
  enviar_email: false
})

const crearCliente = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  try {
    loading.value = true
    
    const payload = {
      nombre: form.value.nombre,
      apellido: form.value.apellido,
      empresa: form.value.empresa,
      rut: form.value.rut,
      giro: form.value.giro,
      email: form.value.email,
      telefono: form.value.telefono,
      direccion: form.value.direccion,
      enviar_email: form.value.enviar_email
    }
    
    const { data } = await api.post('/api/bsale/crear-cliente', payload)
    
    if (data.success) {
      emit('cliente-creado', data.cliente)
      cerrarModal()
      
      // TODO: Mostrar toast de éxito
      alert('Cliente creado exitosamente')
    }
  } catch (error) {
    console.error('Error creando cliente:', error)
    
    const mensaje = error.response?.data?.message || 'Error al crear cliente'
    alert(`❌ ${mensaje}`)
  } finally {
    loading.value = false
  }
}

const cerrarModal = () => {
  localMostrar.value = false
  form.value = {
    nombre: '',
    apellido: '',
    empresa: '',
    rut: '',
    giro: '',
    email: '',
    telefono: '',
    direccion: '',
    enviar_email: false
  }
}
</script>