<template>
  <v-dialog v-model="localMostrar" max-width="800px" persistent>
    <v-card>
      <v-card-title class="text-h5 d-flex align-center">
        <v-icon color="primary" class="me-2">mdi-file-document-plus</v-icon>
        Generar Documento Electrónico
      </v-card-title>
      <v-divider />

      <v-card-text>
        <!-- Información de la cotización -->
        <v-alert color="info" variant="outlined" class="mb-4">
          <div class="d-flex justify-space-between align-center">
            <div>
              <strong>Cotización #{{ cotizacion?.numero }}</strong><br>
              <span class="text-caption">Cliente: {{ cotizacion?.cliente?.nombre }}</span>
            </div>
            <div class="text-end">
              <div class="text-h6 text-success">${{ cotizacion?.total?.toLocaleString() }}</div>
              <span class="text-caption">{{ cotizacion?.ventanas?.length }} ventanas</span>
            </div>
          </div>
        </v-alert>

        <v-form ref="formRef" @submit.prevent="generarDocumento">
          <v-row>
            <!-- Tipo de documento -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.tipo_documento"
                :items="tiposDocumento"
                item-title="nombre"
                item-value="id"
                label="Tipo de documento *"
                :rules="[v => !!v || 'Requerido']"
                variant="outlined"
                prepend-inner-icon="mdi-file-document"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ item.raw.nombre }}</v-list-item-title>
                    <v-list-item-subtitle>{{ item.raw.descripcion }}</v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <!-- Método de pago -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.metodo_pago"
                :items="metodosPago"
                item-title="nombre"
                item-value="id"
                label="Método de pago *"
                :rules="[v => !!v || 'Requerido']"
                variant="outlined"
                prepend-inner-icon="mdi-credit-card"
              />
            </v-col>

            <!-- Condiciones de pago -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.condiciones_pago"
                :items="condicionesPago"
                item-title="nombre"
                item-value="valor"
                label="Condiciones de pago *"
                :rules="[v => !!v || 'Requerido']"
                variant="outlined"
                prepend-inner-icon="mdi-calendar-clock"
              />
            </v-col>

            <!-- Fecha de vencimiento (solo si no es contado) -->
            <v-col cols="12" md="6" v-if="form.condiciones_pago !== 'contado'">
              <v-text-field
                v-model="form.fecha_vencimiento"
                type="date"
                label="Fecha de vencimiento"
                variant="outlined"
                prepend-inner-icon="mdi-calendar"
                :min="fechaMinima"
              />
            </v-col>

            <!-- Cliente en BSALE -->
            <v-col cols="12">
              <v-autocomplete
                v-model="form.cliente_bsale_id"
                :items="clientesBsale"
                :search="searchCliente"
                item-title="nombre_completo"
                item-value="id"
                label="Cliente en BSALE *"
                :rules="[v => !!v || 'Requerido']"
                variant="outlined"
                prepend-inner-icon="mdi-account"
                @update:search="buscarClientes"
                no-data-text="No se encontraron clientes"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ item.raw.nombre_completo }}</v-list-item-title>
                    <v-list-item-subtitle>
                      {{ item.raw.empresa }} • {{ item.raw.rut }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>

                <template #append>
                  <v-btn
                    icon="mdi-plus"
                    variant="text"
                    size="small"
                    @click="mostrarCrearCliente = true"
                  />
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Observaciones -->
            <v-col cols="12">
              <v-textarea
                v-model="form.observaciones"
                label="Observaciones (opcional)"
                variant="outlined"
                rows="3"
                prepend-inner-icon="mdi-note-text"
                counter="500"
                maxlength="500"
              />
            </v-col>
          </v-row>

          <!-- Preview del documento -->
          <v-card variant="outlined" class="mt-4" v-if="form.tipo_documento && form.cliente_bsale_id">
            <v-card-title class="text-subtitle-1">
              <v-icon class="me-2">mdi-eye</v-icon>
              Preview del documento
            </v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="6">
                  <div class="text-body-2">
                    <strong>{{ getTipoDocumentoNombre(form.tipo_documento) }}</strong><br>
                    <strong>Cliente:</strong> {{ getClienteSeleccionado()?.nombre_completo }}<br>
                    <strong>RUT:</strong> {{ getClienteSeleccionado()?.rut }}<br>
                    <strong>Método de pago:</strong> {{ getMetodoPagoNombre(form.metodo_pago) }}<br>
                    <strong>Condiciones:</strong> {{ getCondicionesPagoNombre(form.condiciones_pago) }}
                  </div>
                </v-col>
                <v-col cols="12" md="6">
                  <div class="text-body-2">
                    <strong>Items:</strong> {{ cotizacion?.ventanas?.length || 0 }} ventanas<br>
                    <strong>Subtotal:</strong> ${{ calcularSubtotal().toLocaleString() }}<br>
                    <strong>IVA (19%):</strong> ${{ calcularIVA().toLocaleString() }}<br>
                    <div class="text-h6 text-success mt-2">
                      <strong>Total: ${{ calcularTotal().toLocaleString() }}</strong>
                    </div>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          color="secondary"
          variant="text"
          @click="cerrarModal"
          :disabled="loading"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          @click="generarDocumento"
          :loading="loading"
        >
          <v-icon start>mdi-file-document-plus</v-icon>
          Generar Documento
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Modal crear cliente -->
    <CrearClienteBsale
      v-model:mostrar="mostrarCrearCliente"
      @cliente-creado="onClienteCreado"
    />
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import api from '@/axiosInstance'
import CrearClienteBsale from '@/components/CrearClienteBsale.vue'

const props = defineProps({
  mostrar: Boolean,
  cotizacion: Object
})

const emit = defineEmits(['update:mostrar', 'documento-creado', 'actualizar-cotizacion'])

const localMostrar = ref(props.mostrar)
watch(() => props.mostrar, val => { localMostrar.value = val })
watch(localMostrar, val => { emit('update:mostrar', val) })

const loading = ref(false)
const mostrarCrearCliente = ref(false)
const searchCliente = ref('')

// Formulario
const form = ref({
  tipo_documento: null,
  metodo_pago: null,
  condiciones_pago: 'contado',
  fecha_vencimiento: null,
  cliente_bsale_id: null,
  observaciones: ''
})

// Datos
const tiposDocumento = ref([
  { id: 1, nombre: 'Factura Afecta', descripcion: 'Documento tributario con IVA' },
  { id: 8, nombre: 'Boleta Afecta', descripcion: 'Para consumidores finales con IVA' },
  { id: 9, nombre: 'Guía de Despacho', descripcion: 'Solo traslado de mercadería' },
  { id: 12, nombre: 'Nota de Venta', descripcion: 'Documento interno de venta' }
])

const metodosPago = ref([
  { id: 1, nombre: 'Efectivo' },
  { id: 2, nombre: 'Transferencia Bancaria' },
  { id: 3, nombre: 'Cheque' },
  { id: 4, nombre: 'Tarjeta de Crédito' },
  { id: 5, nombre: 'Tarjeta de Débito' }
])

const condicionesPago = ref([
  { nombre: 'Contado', valor: 'contado' },
  { nombre: '30 días', valor: '30_dias' },
  { nombre: '60 días', valor: '60_dias' },
  { nombre: '90 días', valor: '90_dias' }
])

const clientesBsale = ref([])

// Computed
const fechaMinima = computed(() => {
  return new Date().toISOString().split('T')[0]
})

// Métodos
const buscarClientes = async (search = '') => {
  try {
    const { data } = await api.get('/api/bsale/clientes', {
      params: { search, limit: 50 }
    })
    
    if (data.success) {
      clientesBsale.value = (data.clientes.items || []).map(cliente => ({
        ...cliente,
        nombre_completo: `${cliente.firstName} ${cliente.lastName}`.trim(),
        rut: cliente.code || 'Sin RUT',
        empresa: cliente.company || 'Particular'
      }))
    }
  } catch (error) {
    console.error('Error buscando clientes:', error)
  }
}

const generarDocumento = async () => {
  try {
    loading.value = true
    
    const payload = {
      cotizacion_id: props.cotizacion.id,
      tipo_documento: form.value.tipo_documento,
      metodo_pago: form.value.metodo_pago,
      condiciones_pago: form.value.condiciones_pago,
      fecha_vencimiento: form.value.fecha_vencimiento,
      cliente_bsale_id: form.value.cliente_bsale_id,
      observaciones: form.value.observaciones
    }
    
    console.log('Generando documento con payload:', payload)
    
    const { data } = await api.post('/api/bsale/crear-documento', payload)
    
    if (data.success) {
      emit('documento-creado', data.documento)
      emit('actualizar-cotizacion', data.cotizacion)
      cerrarModal()
      
      // TODO: Mostrar toast de éxito
      alert(`✅ Documento generado exitosamente: ${data.documento.number}`)
    }
  } catch (error) {
    console.error('Error generando documento:', error)
    
    // Mostrar error específico si viene del backend
    const mensaje = error.response?.data?.message || 'Error al generar documento'
    alert(`❌ ${mensaje}`)
  } finally {
    loading.value = false
  }
}

const onClienteCreado = (cliente) => {
  clientesBsale.value.push({
    ...cliente,
    nombre_completo: `${cliente.firstName} ${cliente.lastName}`.trim()
  })
  form.value.cliente_bsale_id = cliente.id
}

const cerrarModal = () => {
  localMostrar.value = false
  form.value = {
    tipo_documento: null,
    metodo_pago: null,
    condiciones_pago: 'contado',
    fecha_vencimiento: null,
    cliente_bsale_id: null,
    observaciones: ''
  }
}

// Helpers
const getTipoDocumentoNombre = (id) => {
  const tipo = tiposDocumento.value.find(t => t.id === id)
  return tipo?.nombre || 'Documento'
}

const getMetodoPagoNombre = (id) => {
  const metodo = metodosPago.value.find(m => m.id === id)
  return metodo?.nombre || 'No especificado'
}

const getCondicionesPagoNombre = (valor) => {
  const condicion = condicionesPago.value.find(c => c.valor === valor)
  return condicion?.nombre || 'No especificado'
}

const getClienteSeleccionado = () => {
  return clientesBsale.value.find(c => c.id === form.value.cliente_bsale_id)
}

const calcularSubtotal = () => {
  const total = props.cotizacion?.total || 0
  return Math.round(total / 1.19) // Quitar IVA
}

const calcularIVA = () => {
  return Math.round(calcularSubtotal() * 0.19)
}

const calcularTotal = () => {
  return calcularSubtotal() + calcularIVA()
}

// Lifecycle
watch(() => props.mostrar, (val) => {
  if (val) {
    buscarClientes('')
  }
})
</script>