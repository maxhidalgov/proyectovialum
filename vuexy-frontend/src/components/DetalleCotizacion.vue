<template>
  <v-card class="ma-0">
    <v-card-title class="d-flex align-center justify-space-between">
      <div>
        <h2>Detalle de Cotización #{{ cotizacion?.id }}</h2>
        <v-chip :color="getColorEstado()" size="small" class="mt-2">
          {{ cotizacion?.estado?.nombre }}
        </v-chip>
      </div>
      <v-btn
        icon="mdi-close"
        variant="text"
        @click="$emit('cerrar')"
      />
    </v-card-title>
    <v-divider />

    <v-card-text class="pa-6">
      <v-row>
        <!-- Información del cliente -->
        <v-col cols="12" md="6">
          <v-card variant="outlined">
            <v-card-title class="text-h6">
              <v-icon class="me-2">mdi-account</v-icon>
              Información del Cliente
            </v-card-title>
            <v-card-text>
              <div class="text-body-1 mb-2">
                <strong>{{ cotizacion?.cliente?.nombre }}</strong>
              </div>
              <div class="text-body-2 text-medium-emphasis mb-1">
                📧 {{ cotizacion?.cliente?.email || 'Sin email' }}
              </div>
              <div class="text-body-2 text-medium-emphasis mb-1">
                📱 {{ cotizacion?.cliente?.telefono || 'Sin teléfono' }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                🏠 {{ cotizacion?.cliente?.direccion || 'Sin dirección' }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Información de la cotización -->
        <v-col cols="12" md="6">
          <v-card variant="outlined">
            <v-card-title class="text-h6">
              <v-icon class="me-2">mdi-file-document</v-icon>
              Datos de la Cotización
            </v-card-title>
            <v-card-text>
              <div class="text-body-2 mb-1">
                <strong>Número:</strong> #{{ cotizacion?.id }}
              </div>
              <div class="text-body-2 mb-1">
                <strong>Fecha:</strong> {{ formatearFecha(cotizacion?.fecha) }}
              </div>
              <div class="text-body-2 mb-1">
                <strong>Vendedor:</strong> {{ cotizacion?.vendedor?.nombre || 'No asignado' }}
              </div>
              <div class="text-body-2 mb-1">
                <strong>Estado:</strong> 
                <v-chip :color="getColorEstado()" size="x-small" class="ms-1">
                  {{ cotizacion?.estado?.nombre }}
                </v-chip>
              </div>
              <div class="text-body-2">
                <strong>Total:</strong> 
                <span class="text-success text-h6 ms-1">
                  ${{ cotizacion?.total?.toLocaleString() }}
                </span>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Lista de ventanas -->
      <v-row class="mt-4">
        <v-col cols="12">
          <v-card variant="outlined">
            <v-card-title class="text-h6">
              <v-icon class="me-2">mdi-window-closed-variant</v-icon>
              Ventanas Cotizadas ({{ cotizacion?.ventanas?.length || 0 }})
            </v-card-title>
            <v-card-text>
              <v-data-table
                :headers="headersVentanas"
                :items="cotizacion?.ventanas || []"
                item-value="id"
                class="elevation-0"
                density="comfortable"
              >
                <!-- Tipo de ventana -->
                <template #item.tipo_ventana="{ item }">
                  <div>
                    <div class="font-weight-medium">
                      {{ item.tipoVentana?.nombre || item.tipo_ventana?.nombre }}
                    </div>
                    <div class="text-caption text-medium-emphasis" v-if="mostrarDetallesVentana(item)">
                      {{ mostrarDetallesVentana(item) }}
                    </div>
                  </div>
                </template>

                <!-- Dimensiones -->
                <template #item.dimensiones="{ item }">
                  <div class="text-body-2">
                    <div>{{ item.ancho }}mm × {{ item.alto }}mm</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ calcularMetrosCuadrados(item.ancho, item.alto) }} m²
                    </div>
                  </div>
                </template>

                <!-- Material y color -->
                <template #item.material_color="{ item }">
                  <div class="text-body-2">
                    <div>{{ item.color?.nombre || 'Sin color' }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ item.productoVidrioProveedor?.producto?.nombre || 'Sin vidrio' }}
                    </div>
                  </div>
                </template>

                <!-- Cantidad -->
                <template #item.cantidad="{ item }">
                  <v-chip size="small" color="primary" variant="outlined">
                    {{ item.cantidad }}
                  </v-chip>
                </template>

                <!-- Precio unitario -->
                <template #item.precio_unitario="{ item }">
                  <div class="font-weight-medium">
                    ${{ (item.precio_unitario || item.precio || 0).toLocaleString() }}
                  </div>
                </template>

                <!-- Total -->
                <template #item.total="{ item }">
                  <div class="font-weight-bold text-success">
                    ${{ calcularTotalVentana(item).toLocaleString() }}
                  </div>
                </template>

                <!-- Imagen (si existe) -->
                <template #item.imagen="{ item }">
                  <v-avatar v-if="item.imagen" size="40" class="cursor-pointer" @click="verImagen(item.imagen)">
                    <v-img :src="getImagenUrl(item.imagen)" cover />
                  </v-avatar>
                  <v-icon v-else color="grey-lighten-1">mdi-image-off</v-icon>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Resumen financiero -->
      <v-row class="mt-4">
        <v-col cols="12" md="8">
          <v-card variant="outlined" v-if="cotizacion?.observaciones">
            <v-card-title class="text-h6">
              <v-icon class="me-2">mdi-note-text</v-icon>
              Observaciones
            </v-card-title>
            <v-card-text>
              <p class="text-body-2">{{ cotizacion.observaciones }}</p>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="outlined" color="success" class="text-center">
            <v-card-title class="text-h6">
              <v-icon class="me-2">mdi-calculator</v-icon>
              Resumen Total
            </v-card-title>
            <v-card-text>
              <div class="text-body-2 d-flex justify-space-between mb-2">
                <span>Subtotal:</span>
                <span>${{ calcularSubtotal().toLocaleString() }}</span>
              </div>
              <div class="text-body-2 d-flex justify-space-between mb-2" v-if="calcularDescuento() > 0">
                <span>Descuento:</span>
                <span class="text-error">-${{ calcularDescuento().toLocaleString() }}</span>
              </div>
              <v-divider class="my-3" />
              <div class="text-h5 font-weight-bold text-success d-flex justify-space-between">
                <span>Total:</span>
                <span>${{ (cotizacion?.total || 0).toLocaleString() }}</span>
              </div>
              <div class="text-caption text-medium-emphasis mt-2">
                {{ cotizacion?.ventanas?.length || 0 }} ventanas
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-card-text>

    <!-- Dialog para ver imagen -->
    <v-dialog v-model="mostrarImagen" max-width="800px">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          Imagen de Ventana
          <v-btn icon="mdi-close" variant="text" @click="mostrarImagen = false" />
        </v-card-title>
        <v-card-text class="text-center">
          <v-img :src="imagenSeleccionada" max-height="500" contain />
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  cotizacion: Object
})

const emit = defineEmits(['cerrar'])

const mostrarImagen = ref(false)
const imagenSeleccionada = ref('')

// Headers para la tabla de ventanas
const headersVentanas = [
  { title: 'Tipo', key: 'tipo_ventana', sortable: false },
  { title: 'Dimensiones', key: 'dimensiones', sortable: false },
  { title: 'Material/Color', key: 'material_color', sortable: false },
  { title: 'Cantidad', key: 'cantidad', sortable: true },
  { title: 'Precio Unit.', key: 'precio_unitario', sortable: true },
  { title: 'Total', key: 'total', sortable: false },
  { title: 'Imagen', key: 'imagen', sortable: false }
]

// Métodos
const getColorEstado = () => {
  const estado = props.cotizacion?.estado?.nombre?.toLowerCase()
  const colores = {
    'evaluación': 'warning',
    'aprobada': 'success', 
    'rechazada': 'error',
    'facturada': 'info',
    'pagada': 'primary'
  }
  return colores[estado] || 'grey'
}

const formatearFecha = (fecha) => {
  if (!fecha) return '-'
  return new Date(fecha).toLocaleDateString('es-CL')
}

const mostrarDetallesVentana = (ventana) => {
  const detalles = []
  
  if (ventana.hojas_totales) {
    detalles.push(`${ventana.hojas_totales} hojas`)
  }
  if (ventana.hojas_moviles) {
    detalles.push(`${ventana.hojas_moviles} móviles`)
  }
  
  return detalles.join(', ')
}

const calcularMetrosCuadrados = (ancho, alto) => {
  if (!ancho || !alto) return '0.00'
  return ((ancho * alto) / 1000000).toFixed(2)
}

const calcularTotalVentana = (ventana) => {
  const precio = ventana.precio_unitario || ventana.precio || 0
  const cantidad = ventana.cantidad || 1
  return precio * cantidad
}

const calcularSubtotal = () => {
  return props.cotizacion?.ventanas?.reduce((sum, v) => sum + calcularTotalVentana(v), 0) || 0
}

const calcularDescuento = () => {
  // Si hay un descuento total en la cotización
  return props.cotizacion?.descuento_total || 0
}

const verImagen = (nombreImagen) => {
  imagenSeleccionada.value = getImagenUrl(nombreImagen)
  mostrarImagen.value = true
}

const getImagenUrl = (nombreImagen) => {
  if (!nombreImagen) return ''
  
  // Primero intentar desde el almacenamiento público local
  return `/storage/imagenes_ventanas/${nombreImagen}`
  
  // Si usas FTP, cambiar por la URL de tu servidor
  // return `https://tu-servidor.com/imagenes_ventanas/${nombreImagen}`
}
</script>