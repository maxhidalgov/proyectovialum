<template>
  <div>
    <!-- Header con botón volver -->
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-btn
            icon="mdi-arrow-left"
            variant="text"
            :to="{ name: 'clientes' }"
          />
          <div>
            <h1 class="text-h5 font-weight-bold">{{ nombreCliente }}</h1>
            <div class="d-flex align-center gap-2 mt-1">
              <v-chip v-if="cliente" size="small" :color="cliente.tipo_cliente === 'empresa' ? 'primary' : 'secondary'" variant="tonal">
                {{ cliente.tipo_cliente === 'empresa' ? 'Empresa' : 'Persona natural' }}
              </v-chip>
              <v-chip v-if="cliente?.bsale_id" size="small" color="success" variant="outlined">
                Bsale #{{ cliente.bsale_id }}
              </v-chip>
              <v-chip v-else-if="cliente" size="small" variant="outlined">Local</v-chip>
            </div>
          </div>
        </div>
      </v-col>
    </v-row>

    <v-skeleton-loader v-if="loading" type="article, table" />

    <template v-else-if="cliente">
      <v-row>
        <!-- Info del cliente -->
        <v-col cols="12" md="4">
          <v-card rounded="lg">
            <v-card-title class="pa-4 pb-2">
              <v-icon icon="mdi-account-circle-outline" class="mr-2" />
              Información
            </v-card-title>
            <v-divider />
            <v-list density="compact" class="pa-2">
              <v-list-item v-if="cliente.identification" prepend-icon="mdi-card-account-details-outline" title="RUT">
                <template #append>
                  <span class="text-body-2">{{ cliente.identification }}</span>
                </template>
              </v-list-item>
              <v-list-item v-if="cliente.email" prepend-icon="mdi-email-outline" title="Email">
                <template #append>
                  <a :href="`mailto:${cliente.email}`" class="text-body-2 text-primary">{{ cliente.email }}</a>
                </template>
              </v-list-item>
              <v-list-item v-if="cliente.phone" prepend-icon="mdi-phone-outline" title="Teléfono">
                <template #append>
                  <span class="text-body-2">{{ cliente.phone }}</span>
                </template>
              </v-list-item>
              <v-list-item v-if="cliente.giro" prepend-icon="mdi-briefcase-outline" title="Giro">
                <template #append>
                  <span class="text-body-2">{{ cliente.giro }}</span>
                </template>
              </v-list-item>
              <v-divider v-if="cliente.address || cliente.ciudad || cliente.comuna" class="my-1" />
              <v-list-item v-if="cliente.address" prepend-icon="mdi-map-marker-outline" title="Dirección">
                <template #append>
                  <span class="text-body-2">{{ cliente.address }}</span>
                </template>
              </v-list-item>
              <v-list-item v-if="cliente.ciudad" prepend-icon="mdi-city-variant-outline" title="Ciudad">
                <template #append>
                  <span class="text-body-2">{{ cliente.ciudad }}</span>
                </template>
              </v-list-item>
              <v-list-item v-if="cliente.comuna" prepend-icon="mdi-home-city-outline" title="Comuna">
                <template #append>
                  <span class="text-body-2">{{ cliente.comuna }}</span>
                </template>
              </v-list-item>
            </v-list>
          </v-card>

          <!-- Descuento en productos de lista -->
          <v-card class="mt-4" rounded="lg">
            <v-card-text class="d-flex align-center gap-3">
              <v-icon icon="mdi-sale" color="warning" />
              <div class="flex-grow-1">
                <div class="text-body-2 font-weight-medium">Descuento en productos</div>
                <div class="text-caption text-medium-emphasis">Se aplica automáticamente al vender/cotizar productos de lista (no ventanas)</div>
              </div>
              <v-text-field
                v-model.number="descuento"
                type="number"
                min="0"
                max="100"
                suffix="%"
                density="compact"
                variant="outlined"
                hide-details
                style="max-width:110px"
              />
              <v-btn size="small" color="primary" :loading="guardandoDesc" @click="guardarDescuento">Guardar</v-btn>
            </v-card-text>
          </v-card>

          <!-- Resumen -->
          <v-card class="mt-4" rounded="lg">
            <v-card-title class="pa-4 pb-2">
              <v-icon icon="mdi-chart-bar" class="mr-2" />
              Resumen
            </v-card-title>
            <v-divider />
            <v-card-text>
              <v-row>
                <v-col cols="6">
                  <div class="text-center">
                    <div class="text-h4 font-weight-bold">{{ cotizaciones.length }}</div>
                    <div class="text-caption text-medium-emphasis">Cotizaciones</div>
                  </div>
                </v-col>
                <v-col cols="6">
                  <div class="text-center">
                    <div class="text-h4 font-weight-bold text-success">{{ cotizacionesAprobadas }}</div>
                    <div class="text-caption text-medium-emphasis">Aprobadas</div>
                  </div>
                </v-col>
                <v-col cols="12">
                  <v-divider class="my-2" />
                  <div class="text-center">
                    <div class="text-h6 font-weight-bold">{{ formatCLP(totalFacturado) }}</div>
                    <div class="text-caption text-medium-emphasis">Total en cotizaciones</div>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Cotizaciones -->
        <v-col cols="12" md="8">
          <v-card rounded="lg">
            <v-card-title class="pa-4 pb-2 d-flex align-center justify-space-between">
              <div>
                <v-icon icon="mdi-file-document-multiple-outline" class="mr-2" />
                Cotizaciones
              </div>
              <v-chip size="small" variant="tonal" color="primary">{{ cotizaciones.length }}</v-chip>
            </v-card-title>
            <v-divider />
            <v-data-table
              :headers="headersCotizaciones"
              :items="cotizaciones"
              :items-per-page="10"
              no-data-text="Sin cotizaciones registradas"
            >
              <template #item.id="{ item }">
                <span class="font-weight-medium">#{{ item.id }}</span>
              </template>
              <template #item.fecha="{ item }">
                {{ item.fecha ? new Date(item.fecha).toLocaleDateString('es-CL') : '—' }}
              </template>
              <template #item.estado="{ item }">
                <v-chip
                  size="small"
                  :color="getEstadoColor(item.estado?.nombre)"
                  variant="tonal"
                >
                  {{ item.estado?.nombre || '—' }}
                </v-chip>
              </template>
              <template #item.total="{ item }">
                {{ item.total ? formatCLP(item.total) : '—' }}
              </template>
              <template #item.vendedor="{ item }">
                <span class="text-body-2">{{ item.vendedor?.name || '—' }}</span>
              </template>
              <template #item.acciones="{ item }">
                <v-btn
                  icon="mdi-eye-outline"
                  size="small"
                  variant="text"
                  color="primary"
                  :to="{ name: 'cotizacion-ver', query: { id: item.id } }"
                />
              </template>
            </v-data-table>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <v-alert v-else type="error" variant="tonal">No se encontró el cliente.</v-alert>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/axiosInstance'

const route = useRoute()
const cliente = ref(null)
const loading = ref(true)
const descuento = ref(0)
const guardandoDesc = ref(false)

async function guardarDescuento() {
  guardandoDesc.value = true
  try {
    await api.patch(`/api/clientes/${route.params.id}/descuento`, { descuento_productos: descuento.value })
    if (cliente.value) cliente.value.descuento_productos = descuento.value
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar el descuento')
  } finally {
    guardandoDesc.value = false
  }
}

const cotizaciones = computed(() => cliente.value?.cotizaciones ?? [])

const nombreCliente = computed(() => {
  if (!cliente.value) return 'Cliente'
  return cliente.value.razon_social ||
    `${cliente.value.first_name || ''} ${cliente.value.last_name || ''}`.trim() ||
    'Sin nombre'
})

const cotizacionesAprobadas = computed(() =>
  cotizaciones.value.filter(c => c.estado?.nombre === 'Aprobada').length
)

const totalFacturado = computed(() =>
  cotizaciones.value.reduce((sum, c) => sum + (parseFloat(c.total) || 0), 0)
)

const formatCLP = (n) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n)

const getEstadoColor = (nombre) => {
  const mapa = {
    'Evaluación':    'grey',
    'Aprobada':      'success',
    'Rechazada':     'error',
    'Anulada':       'error',
    'Enviada':       'orange',
    'Facturada':     'teal',
    'En Producción': 'blue',
    'Entregada':     'purple',
  }
  return mapa[nombre] || 'default'
}

const headersCotizaciones = [
  { title: '#',        value: 'id',       sortable: true },
  { title: 'Fecha',    value: 'fecha',    sortable: true },
  { title: 'Estado',   value: 'estado',   sortable: false },
  { title: 'Total',    value: 'total',    sortable: true },
  { title: 'Vendedor', value: 'vendedor', sortable: false },
  { title: '',         value: 'acciones', sortable: false, align: 'end' },
]

onMounted(async () => {
  try {
    const res = await api.get(`/api/clientes/${route.params.id}`)
    cliente.value = res.data
    descuento.value = Number(res.data?.descuento_productos ?? 0)
  } catch {
    cliente.value = null
  } finally {
    loading.value = false
  }
})
</script>
