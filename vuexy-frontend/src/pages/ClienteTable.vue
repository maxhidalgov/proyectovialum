<template>
  <v-card>
    <v-data-table
      :headers="headers"
      :items="clientes"
      :loading="loading"
      :items-per-page="25"
      class="elevation-1"
      loading-text="Cargando clientes..."
      no-data-text="No hay clientes sincronizados"
    >
      <!-- Razón Social / Nombre -->
      <template #item.razon_social="{ item }">
        <div>
          <div class="font-weight-medium">
            {{ item.razon_social || `${item.first_name || ''} ${item.last_name || ''}`.trim() || 'Sin nombre' }}
          </div>
          <div class="text-caption text-medium-emphasis" v-if="item.tipo_cliente">
            <v-chip size="x-small" :color="item.tipo_cliente === 'empresa' ? 'primary' : 'secondary'" variant="outlined">
              {{ item.tipo_cliente === 'empresa' ? 'Empresa' : 'Persona' }}
            </v-chip>
          </div>
        </div>
      </template>

      <!-- Contacto -->
      <template #item.email="{ item }">
        <div>
          <div v-if="item.email">{{ item.email }}</div>
          <div class="text-caption text-medium-emphasis" v-if="item.phone">
            📞 {{ item.phone }}
          </div>
        </div>
      </template>

      <!-- RUT -->
      <template #item.identification="{ item }">
        <v-chip size="small" variant="outlined">
          {{ item.identification || 'Sin RUT' }}
        </v-chip>
      </template>

      <!-- Ubicación -->
      <template #item.ubicacion="{ item }">
        <div class="text-caption">
          <div v-if="item.ciudad">{{ item.ciudad }}</div>
          <div v-if="item.comuna" class="text-medium-emphasis">{{ item.comuna }}</div>
          <div v-if="item.address" class="text-medium-emphasis">{{ item.address }}</div>
        </div>
      </template>

      <!-- Origen -->
      <template #item.bsale_id="{ item }">
        <v-chip v-if="item.bsale_id" size="x-small" color="success" variant="outlined">
          Bsale #{{ item.bsale_id }}
        </v-chip>
        <span v-else class="text-caption text-medium-emphasis">Local</span>
      </template>

      <!-- Acciones -->
      <template #item.acciones="{ item }">
        <v-btn
          icon="mdi-eye-outline"
          size="small"
          variant="text"
          color="primary"
          :to="{ name: 'clientes-id', params: { id: item.id } }"
        />
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  clientes: { type: Array, default: () => [] },
  loading:  { type: Boolean, default: false },
})

const headers = [
  { title: 'Razón Social / Nombre', value: 'razon_social', sortable: true },
  { title: 'RUT',                   value: 'identification', sortable: true },
  { title: 'Email / Teléfono',      value: 'email', sortable: true },
  { title: 'Ubicación',             value: 'ubicacion', sortable: false },
  { title: 'Origen',                value: 'bsale_id', sortable: true },
  { title: '',                      value: 'acciones', sortable: false, align: 'end' },
]
</script>
  