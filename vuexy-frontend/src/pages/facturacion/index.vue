<template>
  <div>
    <!-- Header de la página -->
    <v-card class="mb-6">
      <v-card-title class="d-flex align-center justify-space-between">
        <div>
          <h1 class="text-h4 mb-2">📄 Facturación</h1>
          <p class="text-subtitle-1 text-medium-emphasis">
            Gestiona las cotizaciones aprobadas y genera documentos electrónicos
          </p>
        </div>
        <v-chip color="primary" variant="elevated" size="large">
          {{ cotizacionesAprobadas.length }} por facturar
        </v-chip>
      </v-card-title>
    </v-card>

    <!-- Filtros y búsqueda -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filtros.busqueda"
              label="Buscar cotización..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.estado"
              :items="estadosFacturacion"
              label="Estado"
              variant="outlined"
              density="comfortable"
              clearable
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.cliente"
              :items="clientesUnicos"
              item-title="nombre"
              item-value="id"
              label="Cliente"
              variant="outlined"
              density="comfortable"
              clearable
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-btn
              color="primary"
              variant="outlined"
              block
              @click="limpiarFiltros"
            >
              Limpiar filtros
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Tabla de cotizaciones -->
    <v-card>
      <v-data-table
        v-model:items-per-page="itemsPorPagina"
        :headers="headers"
        :items="cotizacionesFiltradas"
        :loading="loading"
        class="elevation-1"
        item-value="id"
        show-expand
        expand-on-click
      >
        <!-- Número de cotización -->
        <template #item.numero="{ item }">
          <div class="d-flex align-center">
            <v-chip color="primary" variant="outlined" size="small" class="me-2">
              #{{ item.id }}
            </v-chip>
          </div>
        </template>

        <!-- Cliente -->
        <template #item.cliente="{ item }">
          <div v-if="item.cliente">
            <div class="font-weight-medium">
              {{ item.cliente.razon_social || `${item.cliente.first_name || ''} ${item.cliente.last_name || ''}`.trim() || 'Sin nombre' }}
            </div>
            <div class="text-caption text-medium-emphasis">{{ item.cliente.email || item.cliente.identification }}</div>
          </div>
          <div v-else class="text-caption text-medium-emphasis">
            Sin cliente
          </div>
        </template>

        <!-- Cliente Facturación -->
        <template #item.cliente_facturacion="{ item }">
          <div v-if="item.cliente_facturacion_id && item.cliente_facturacion">
            <!-- Solo mostrar si es diferente al cliente original -->
            <div v-if="item.cliente_facturacion_id !== item.cliente_id" class="d-flex align-center">
              <v-icon size="small" color="warning" class="me-1">mdi-alert-circle</v-icon>
              <div>
                <div class="font-weight-medium text-warning">
                  {{ item.cliente_facturacion.razon_social || `${item.cliente_facturacion.first_name || ''} ${item.cliente_facturacion.last_name || ''}`.trim() || 'Sin nombre' }}
                </div>
                <div class="text-caption">{{ item.cliente_facturacion.identification }}</div>
              </div>
            </div>
            <!-- Si es el mismo cliente, mostrar ícono de check -->
            <div v-else class="text-caption text-medium-emphasis">
              <v-icon size="small" color="success" class="me-1">mdi-check</v-icon>
              Mismo cliente
            </div>
          </div>
          <div v-else class="text-caption text-medium-emphasis">
            <v-icon size="small" color="info" class="me-1">mdi-account</v-icon>
            Mismo cliente
          </div>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <div class="font-weight-bold text-success">
            ${{ item.total?.toLocaleString() }}
          </div>
        </template>

        <!-- Estado -->
        <template #item.estado_facturacion="{ item }">
          <v-chip
            :color="getColorEstado(item.estado_facturacion)"
            :variant="item.estado_facturacion === 'aprobada' ? 'elevated' : 'outlined'"
            size="small"
          >
            {{ getTextoEstado(item.estado_facturacion) }}
          </v-chip>
        </template>

        <!-- Fecha -->
        <template #item.fecha_aprobacion="{ item }">
          <div class="text-caption">
            {{ formatearFecha(item.fecha_aprobacion) }}
          </div>
        </template>

        <!-- Acciones -->
        <template #item.acciones="{ item }">
          <div class="d-flex gap-2">
            <!-- Botón principal según estado -->
            <v-btn
              v-if="item.estado_facturacion === 'aprobada'"
              color="success"
              variant="elevated"
              size="small"
              @click="abrirModalBsale(item)"
            >
              <v-icon start>mdi-receipt</v-icon>
              Generar Documento
            </v-btn>

            <v-btn
              v-else-if="item.estado_facturacion === 'facturada'"
              color="info"
              variant="outlined"
              size="small"
              @click="verDocumento(item)"
            >
              <v-icon start>mdi-file-pdf-box</v-icon>
              Descargar Documento
            </v-btn>

            <!-- Acciones secundarias -->
            <v-menu>
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon="mdi-dots-vertical"
                  variant="text"
                  size="small"
                />
              </template>
              <v-list>
                <v-list-item @click="verDetalleCompleto(item)">
                  <v-list-item-title>Ver detalle</v-list-item-title>
                </v-list-item>
                <v-list-item @click="duplicarCotizacion(item)">
                  <v-list-item-title>Duplicar</v-list-item-title>
                </v-list-item>
                <v-divider />
                <v-list-item @click="eliminarCotizacion(item)" class="text-error">
                  <v-list-item-title>Eliminar</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </div>
        </template>

        <!-- Detalle expandible -->
        <template #expanded-row="{ columns, item }">
          <tr>
            <td :colspan="columns.length">
              <div class="pa-4">
                <v-row>
                  <v-col cols="12" md="8">
                    <h4 class="mb-3">Ventanas cotizadas ({{ item.ventanas?.length || 0 }})</h4>
                    <v-simple-table dense>
                      <template #default>
                        <thead>
                          <tr>
                            <th>Tipo</th>
                            <th>Dimensiones</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="ventana in item.ventanas" :key="ventana.id">
                            <td>{{ ventana.tipo_ventana?.nombre }}</td>
                            <td>{{ ventana.ancho }}mm × {{ ventana.alto }}mm</td>
                            <td>{{ ventana.cantidad }}</td>
                            <td>${{ ventana.precio_unitario?.toLocaleString() }}</td>
                            <td class="font-weight-bold">
                              ${{ (ventana.precio_unitario * ventana.cantidad).toLocaleString() }}
                            </td>
                          </tr>
                        </tbody>
                      </template>
                    </v-simple-table>
                  </v-col>
                  <v-col cols="12" md="4">
                    <v-card variant="outlined">
                      <v-card-title class="text-subtitle-1">Resumen</v-card-title>
                      <v-card-text>
                        <div class="d-flex justify-space-between mb-2">
                          <span>Subtotal:</span>
                          <span>${{ calcularSubtotal(item).toLocaleString() }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-2">
                          <span>Descuento:</span>
                          <span class="text-error">-${{ calcularDescuento(item).toLocaleString() }}</span>
                        </div>
                        <v-divider class="my-2" />
                        <div class="d-flex justify-space-between font-weight-bold">
                          <span>Total:</span>
                          <span class="text-success">${{ item.total?.toLocaleString() }}</span>
                        </div>
                      </v-card-text>
                    </v-card>
                  </v-col>
                </v-row>
              </div>
            </td>
          </tr>
        </template>

        <!-- Footer con paginación -->
        <template #bottom>
          <div class="d-flex justify-space-between align-center pa-4">
            <div class="text-caption">
              Mostrando {{ cotizacionesFiltradas.length }} de {{ cotizacionesAprobadas.length }} cotizaciones
            </div>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Modal de BSALE -->
    <ModalBsale
      v-model="mostrarModalBsale"
      :cotizacion="cotizacionSeleccionada"
      @documento-generado="onDocumentoGenerado"
    />

    <!-- Modal de facturación legacy -->
    <ModalFacturacion
      v-model:mostrar="mostrarModalFacturacion"
      :cotizacion="cotizacionSeleccionada"
      @documento-creado="onDocumentoCreado"
      @actualizar-cotizacion="actualizarCotizacion"
    />

    <!-- Modal de detalle completo -->
    <v-dialog v-model="mostrarModalDetalle" max-width="1200px">
      <DetalleCotizacion
        v-if="mostrarModalDetalle"
        :cotizacion="cotizacionSeleccionada"
        @cerrar="mostrarModalDetalle = false"
      />
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'
import ModalBsale from '@/components/facturacion/ModalBsale.vue'
import ModalFacturacion from '@/components/ModalFacturacion.vue'
import DetalleCotizacion from '@/components/DetalleCotizacion.vue'

// Estado reactivo
const loading = ref(false)
const cotizacionesAprobadas = ref([])
const mostrarModalBsale = ref(false)
const mostrarModalFacturacion = ref(false)
const mostrarModalDetalle = ref(false)
const cotizacionSeleccionada = ref(null)
const itemsPorPagina = ref(10)

// Filtros
const filtros = ref({
  busqueda: '',
  estado: null,
  cliente: null
})

// Headers de la tabla
const headers = [
  { title: 'Número', key: 'numero', sortable: true },
  { title: 'Cliente', key: 'cliente', sortable: false },
  { title: 'Facturado a', key: 'cliente_facturacion', sortable: false },
  { title: 'Total', key: 'total', sortable: true },
  { title: 'Estado', key: 'estado_facturacion', sortable: true },
  { title: 'Fecha aprobación', key: 'fecha_aprobacion', sortable: true },
  { title: 'Acciones', key: 'acciones', sortable: false, width: '200px' }
]

// Estados de facturación
const estadosFacturacion = [
  { title: 'Aprobada (Por facturar)', value: 'aprobada' },
  { title: 'Facturada', value: 'facturada' },
  { title: 'Pagada', value: 'pagada' },
  { title: 'Anulada', value: 'anulada' }
]

// Computed
const clientesUnicos = computed(() => {
  const clientes = cotizacionesAprobadas.value
    .map(c => c.cliente)
    .filter((c, index, arr) => c && arr.findIndex(client => client.id === c.id) === index)
  return clientes
})

const cotizacionesFiltradas = computed(() => {
  let resultado = [...cotizacionesAprobadas.value]

  // Filtro por búsqueda
  if (filtros.value.busqueda) {
    const busqueda = filtros.value.busqueda.toLowerCase()
    resultado = resultado.filter(c => 
      c.numero?.toString().includes(busqueda) ||
      c.cliente?.razon_social?.toLowerCase().includes(busqueda) ||
      c.cliente?.first_name?.toLowerCase().includes(busqueda) ||
      c.cliente?.last_name?.toLowerCase().includes(busqueda) ||
      c.cliente?.identification?.toLowerCase().includes(busqueda) ||
      c.cliente?.email?.toLowerCase().includes(busqueda)
    )
  }

  // Filtro por estado
  if (filtros.value.estado) {
    resultado = resultado.filter(c => c.estado_facturacion === filtros.value.estado)
  }

  // Filtro por cliente
  if (filtros.value.cliente) {
    resultado = resultado.filter(c => c.cliente?.id === filtros.value.cliente)
  }

  return resultado
})

// Métodos
const cargarCotizaciones = async () => {
    try {
        loading.value = true
        console.log('🔍 Intentando cargar cotizaciones desde:', '/api/cotizaciones/aprobadas')
        
        const { data } = await api.get('/api/cotizaciones/aprobadas')
        
        console.log('✅ Respuesta recibida:', data)
        
        cotizacionesAprobadas.value = data.cotizaciones || []
        
        console.log('📊 Cotizaciones cargadas:', cotizacionesAprobadas.value.length)
        
    } catch (error) {
        console.error('❌ Error cargando cotizaciones:', error)
        console.error('📍 URL intentada:', error.config?.url)
        console.error('🔢 Status:', error.response?.status)
        console.error('📝 Mensaje:', error.response?.data)
        
        // Mostrar alert temporal para debug
        alert(`Error: ${error.response?.status} - ${error.response?.data?.message || 'Error desconocido'}`)
    } finally {
        loading.value = false
    }
    }
    const abrirModalFacturacion = (cotizacion) => {
    cotizacionSeleccionada.value = cotizacion
    mostrarModalFacturacion.value = true
}

const verDetalleCompleto = (cotizacion) => {
  cotizacionSeleccionada.value = cotizacion
  mostrarModalDetalle.value = true
}



const onDocumentoCreado = (documento) => {
  console.log('Documento creado:', documento)
  // Actualizar la cotización en la lista
  cargarCotizaciones()
  // TODO: Mostrar toast de éxito
}

const actualizarCotizacion = (cotizacion) => {
  const index = cotizacionesAprobadas.value.findIndex(c => c.id === cotizacion.id)
  if (index !== -1) {
    cotizacionesAprobadas.value[index] = cotizacion
  }
}

const duplicarCotizacion = (cotizacion) => {
  // TODO: Implementar duplicación
  console.log('Duplicar cotización:', cotizacion.id)
}

const eliminarCotizacion = async (cotizacion) => {
  if (confirm(`¿Estás seguro de eliminar la cotización #${cotizacion.numero}?`)) {
    try {
      await api.delete(`/api/cotizaciones/${cotizacion.id}`)
      cotizacionesAprobadas.value = cotizacionesAprobadas.value.filter(c => c.id !== cotizacion.id)
      // TODO: Mostrar toast de éxito
    } catch (error) {
      console.error('Error eliminando cotización:', error)
      // TODO: Mostrar toast de error
    }
  }
}

const limpiarFiltros = () => {
  filtros.value = {
    busqueda: '',
    estado: null,
    cliente: null
  }
}

// Helpers
const getColorEstado = (estado) => {
  const colores = {
    'aprobada': 'warning',
    'facturada': 'success',
    'pagada': 'primary',
    'anulada': 'error'
  }
  return colores[estado] || 'grey'
}

const getTextoEstado = (estado) => {
  const textos = {
    'aprobada': 'Por facturar',
    'facturada': 'Facturada',
    'pagada': 'Pagada',
    'anulada': 'Anulada'
  }
  return textos[estado] || estado
}

const formatearFecha = (fecha) => {
  if (!fecha) return '-'
  return new Date(fecha).toLocaleDateString('es-CL')
}

const calcularSubtotal = (cotizacion) => {
  return cotizacion.ventanas?.reduce((sum, v) => sum + (v.precio_unitario * v.cantidad), 0) || 0
}

// Métodos BSALE
const abrirModalBsale = (cotizacion) => {
  cotizacionSeleccionada.value = cotizacion
  mostrarModalBsale.value = true
}

const onDocumentoGenerado = async (documento) => {
  try {
    // Recargar la lista para reflejar el cambio de estado
    await cargarCotizaciones()
    
    // Mostrar mensaje de éxito
    console.log('Documento generado exitosamente:', documento)
    
    // TODO: Mostrar toast de éxito
    // this.$toast.success('Documento electrónico generado exitosamente')
    
  } catch (error) {
    console.error('Error recargando datos:', error)
  }
}

const verDocumento = async (cotizacion) => {
  // Si ya tenemos la URL del PDF guardada, abrirla directamente
  if (cotizacion.url_pdf_bsale) {
    window.open(cotizacion.url_pdf_bsale, '_blank')
    return
  }
  
  // Si no hay URL guardada pero hay ID del documento, intentar obtener el PDF de Bsale
  if (cotizacion.id_documento_bsale) {
    try {
      const response = await api.get(`/api/bsale/documento/${cotizacion.id_documento_bsale}/pdf`)
      if (response.data.success && response.data.pdf_url) {
        window.open(response.data.pdf_url, '_blank')
      } else {
        console.error('No se pudo obtener el PDF del documento')
      }
    } catch (error) {
      console.error('Error obteniendo PDF:', error)
    }
  }
}

const calcularDescuento = (cotizacion) => {
  return cotizacion.descuento_total || 0
}

// Lifecycle
onMounted(() => {
  cargarCotizaciones()
})
</script>