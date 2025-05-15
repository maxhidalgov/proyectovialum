<template>
  <v-card>
    <v-card-title>Clientes desde Bsale</v-card-title>
    <v-data-table
      :headers="headers"
      :items="clientes"
      :loading="loading"
      class="elevation-1"
      loading-text="Cargando clientes..."
      no-data-text="No hay clientes"
    >
      <template #item.acciones="{ item }">
        <v-btn size="x-small" color="primary" @click="guardarCliente(item)">
          Importar
        </v-btn>
      </template>
    </v-data-table>
  </v-card>
</template>
  
  <script setup>

  import { importarCliente } from '@/api/clientes'

  const props = defineProps({
  clientes: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})
const guardarCliente = async (clienteBsale) => {
  const cliente = {
    first_name: clienteBsale.firstName,
    last_name: clienteBsale.lastName,
    email: clienteBsale.email,
    identification: clienteBsale.identification,
    phone: clienteBsale.phone,       // Nuevo
    address: clienteBsale.address,     // Nuevo
    tipo_cliente: clienteBsale.tipo_cliente ?? '',
    razon_social: clienteBsale.razon_social ?? clienteBsale.company ?? '',
    giro: clienteBsale.giro ?? clienteBsale.activity ?? '',
    ciudad: clienteBsale.ciudad ?? clienteBsale.city ?? '',
    comuna: clienteBsale.comuna ?? clienteBsale.municipality ?? '',
  }

  try {
    const res = await importarCliente(cliente)
    alert(`✅ Cliente guardado: ${res.cliente.first_name} ${res.cliente.last_name}`)
  } catch (err) {
    alert('❌ Error al guardar cliente')
  }
}
  // ✅ headers con clave "title" y "value"
  const headers = [
  { title: 'Empresa', value: 'razon_social' },
  { title: 'Contacto', value: 'firstName' },
  { title: 'Email', value: 'email' },
  { title: 'RUT', value: 'identification' },
  { title: 'Direccion', value: 'address' },
  { title: 'Acciones', value: 'acciones', sortable: false },
]
  </script>
  