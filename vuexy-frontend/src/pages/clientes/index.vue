<template>
    <div>
      <v-row class="mb-4">
        <v-col>
          <h1 class="text-h4 mb-2">👥 Clientes</h1>
          <p class="text-medium-emphasis">Clientes sincronizados desde Bsale</p>
        </v-col>
        <v-col cols="auto">
          <v-btn 
            @click="sincronizarClientes" 
            color="primary" 
            prepend-icon="mdi-sync"
            :loading="sincronizando"
          >
            Sincronizar desde Bsale
          </v-btn>
        </v-col>
      </v-row>
      
      <v-card>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="busqueda"
                label="Buscar cliente"
                placeholder="Buscar por nombre, RUT, email..."
                prepend-inner-icon="mdi-magnify"
                clearable
                outlined
                dense
                @input="filtrarClientes"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filtroTipo"
                :items="[
                  { title: 'Todos', value: null },
                  { title: 'Empresas', value: 'empresa' },
                  { title: 'Personas', value: 'persona' }
                ]"
                label="Tipo de cliente"
                outlined
                dense
                @update:modelValue="filtrarClientes"
              ></v-select>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <ClienteTable :clientes="clientesFiltrados" :loading="loading" />
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted, computed } from 'vue'
  import { fetchClientesLocales } from '@/api/clientes'
  import ClienteTable from '../ClienteTable.vue'
  import api from '@/axiosInstance'
  
  const clientes = ref([])
  const clientesFiltrados = ref([])
  const loading = ref(false)
  const sincronizando = ref(false)
  const busqueda = ref('')
  const filtroTipo = ref(null)

  const sincronizarClientes = async () => {
    try {
      sincronizando.value = true
      // Llamar al comando de sincronización (requiere configuración en el backend)
      await api.post('/api/clientes/sincronizar-bsale')
      alert('✅ Clientes sincronizados correctamente')
      await loadClientes()
    } catch (error) {
      console.error('Error sincronizando:', error)
      alert('❌ Error al sincronizar clientes. Por favor, verifica la consola.')
    } finally {
      sincronizando.value = false
    }
  }

  const loadClientes = async () => {
    loading.value = true
    const data = await fetchClientesLocales()
    console.log('Clientes locales:', data)

    if (Array.isArray(data)) {
      clientes.value = data
      clientesFiltrados.value = data
    } else {
      console.warn('⚠️ Los datos no son un array válido:', data)
      clientes.value = []
      clientesFiltrados.value = []
    }

    loading.value = false
  }

  const filtrarClientes = () => {
    let resultado = [...clientes.value]
    
    // Filtro por búsqueda
    if (busqueda.value) {
      const termino = busqueda.value.toLowerCase()
      resultado = resultado.filter(c => 
        c.razon_social?.toLowerCase().includes(termino) ||
        c.first_name?.toLowerCase().includes(termino) ||
        c.last_name?.toLowerCase().includes(termino) ||
        c.identification?.toLowerCase().includes(termino) ||
        c.email?.toLowerCase().includes(termino)
      )
    }
    
    // Filtro por tipo
    if (filtroTipo.value) {
      resultado = resultado.filter(c => c.tipo_cliente === filtroTipo.value)
    }
    
    clientesFiltrados.value = resultado
  }

  // Cargar clientes al montar el componente
  onMounted(() => {
    loadClientes()
  })
  </script>
  