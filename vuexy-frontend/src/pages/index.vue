<template>
  <div>
    <!-- Header con filtros -->
    <VCard class="mb-6" title="Dashboard Financiero 📊">
      <VCardText>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-select
              v-model="mes"
              :items="meses"
              label="Mes"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="anio"
              :items="anios"
              label="Año"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-btn 
              color="primary" 
              @click="cargarResumen" 
              :loading="loading"
              block
            >
              Actualizar Dashboard
            </v-btn>
          </v-col>
        </v-row>
      </VCardText>
    </VCard>

    <!-- Alert de error -->
    <v-alert v-if="error" type="error" class="mb-4">
      {{ error }}
    </v-alert>

    <!-- Cards de resumen -->
    <v-row class="mb-6">
      <v-col cols="12" md="3">
        <VCard color="success" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h6 font-weight-bold">Total Ventas</div>
            <div class="text-h4 mt-2">
              ${{ formatNumber(totalVentas) }}
            </div>
            <div class="text-caption">
              {{ cantidadVentas }} documentos
            </div>
          </VCardText>
        </VCard>
      </v-col>

      <v-col cols="12" md="3">
        <VCard color="error" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h6 font-weight-bold">Total Compras</div>
            <div class="text-h4 mt-2">
              ${{ formatNumber(totalCompras) }}
            </div>
            <div class="text-caption">
              {{ cantidadCompras }} documentos
            </div>
          </VCardText>
        </VCard>
      </v-col>

      <v-col cols="12" md="3">
        <VCard color="warning" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h6 font-weight-bold">IVA por Pagar</div>
            <div class="text-h4 mt-2">
              ${{ formatNumber(ivaAPagar) }}
            </div>
            <div class="text-caption">
              Ventas - Compras
            </div>
          </VCardText>
        </VCard>
      </v-col>

      <v-col cols="12" md="3">
        <VCard color="info" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h6 font-weight-bold">Margen Bruto</div>
            <div class="text-h4 mt-2">
              ${{ formatNumber(margenBruto) }}
            </div>
            <div class="text-caption">
              {{ porcentajeMargen }}% margen
            </div>
          </VCardText>
        </VCard>
      </v-col>
    </v-row>

    <!-- Detalles por cliente/proveedor -->
    <v-row>
      <v-col cols="12" md="6">
        <VCard title="Top 10 Clientes del Mes" class="h-100">
          <v-data-table
            :items="topClientes"
            :headers="headersClientes"
            class="elevation-0"
            :items-per-page="10"
            :loading="loadingVentas"
          >
            <template #item.total="{ item }">
              ${{ formatNumber(item.total || 0) }}
            </template>
          </v-data-table>
        </VCard>
      </v-col>

      <v-col cols="12" md="6">
        <VCard title="Top 10 Compras del Mes" class="h-100">
          <v-data-table
            :items="topCompras"
            :headers="headersCompras"
            class="elevation-0"
            :items-per-page="10"
            :loading="loadingCompras"
          >
            <template #item.total="{ item }">
              ${{ formatNumber(item.total || 0) }}
            </template>
          </v-data-table>
        </VCard>
      </v-col>
    </v-row>

    <!-- Detalles IVA -->
    <VCard class="mt-6" title="Resumen Tributario">
      <VCardText>
        <v-row>
          <v-col cols="12" md="4">
            <div class="text-subtitle-1 mb-2">📈 IVA Ventas (Débito Fiscal)</div>
            <div class="text-h5 text-success">
              ${{ formatNumber(ivaVentas) }}
            </div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-subtitle-1 mb-2">📉 IVA Compras (Crédito Fiscal)</div>
            <div class="text-h5 text-error">
              ${{ formatNumber(ivaCompras) }}
            </div>
          </v-col>
          <v-col cols="12" md="4">
            <div class="text-subtitle-1 mb-2">💰 IVA Resultante</div>
            <div class="text-h5" :class="ivaAPagar >= 0 ? 'text-warning' : 'text-success'">
              ${{ formatNumber(Math.abs(ivaAPagar)) }}
              <small>{{ ivaAPagar >= 0 ? '(A Pagar)' : '(A Favor)' }}</small>
            </div>
          </v-col>
        </v-row>
      </VCardText>
    </VCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/axiosInstance'

// ✅ Estado reactivo
const mes = ref(new Date().getMonth() + 1)
const anio = ref(new Date().getFullYear())
const loading = ref(false)
const loadingVentas = ref(false)
const loadingCompras = ref(false)
const error = ref('')

// ✅ Datos - estructura según los endpoints reales
const datosVentas = ref(null)   // Objeto con total_mes, cantidad, clientes
const datosCompras = ref(null)  // Objeto con total_mes, cantidad, proveedores

// ✅ Opciones para selects
const meses = [
  { title: 'Enero', value: 1 },
  { title: 'Febrero', value: 2 },
  { title: 'Marzo', value: 3 },
  { title: 'Abril', value: 4 },
  { title: 'Mayo', value: 5 },
  { title: 'Junio', value: 6 },
  { title: 'Julio', value: 7 },
  { title: 'Agosto', value: 8 },
  { title: 'Septiembre', value: 9 },
  { title: 'Octubre', value: 10 },
  { title: 'Noviembre', value: 11 },
  { title: 'Diciembre', value: 12 },
]

const anios = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

// ✅ Headers para tablas
const headersClientes = [
  { title: 'Cliente', key: 'cliente', width: '60%' },
  { title: 'Docs', key: 'cantidad', width: '20%' },
  { title: 'Total', key: 'total', width: '20%' },
]

const headersCompras = [
  { title: 'Proveedor', key: 'proveedor', width: '60%' },
  { title: 'Docs', key: 'cantidad', width: '20%' },
  { title: 'Total', key: 'total', width: '20%' },
]

// ✅ ENDPOINT CORRECTO PARA VENTAS (dashboardventas/index.vue)
const cargarVentas = async () => {
  loadingVentas.value = true
  console.log('🔍 DEBUG VENTAS - Endpoint: /api/dashboard/ventas-mensuales')
  console.log('📅 Parámetros:', { mes: mes.value, anio: anio.value })
  
  try {
    const response = await api.get('/api/dashboard/ventas-mensuales', {
      params: {
        mes: mes.value,
        anio: anio.value,
      }
    })
    
    console.log('📈 Respuesta ventas completa:', response.data)
    
    datosVentas.value = {
      total_mes: response.data.total_mes || 0,
      cantidad: response.data.cantidad || 0,
      clientes: response.data.clientes || []
    }
    
    console.log('✅ Datos ventas procesados:', datosVentas.value)
    
  } catch (err) {
    console.error('❌ Error al cargar ventas:', err)
    console.error('❌ Error.response:', err.response?.data)
    error.value = 'Error al cargar datos de ventas: ' + (err.response?.data?.message || err.message)
  } finally {
    loadingVentas.value = false
  }
}

// ✅ ENDPOINT CORRECTO PARA COMPRAS (comprasmensuales/index.vue)
const cargarCompras = async () => {
  loadingCompras.value = true
  console.log('🔍 DEBUG COMPRAS - Endpoint: /api/compras-terceros-mensuales')
  console.log('📅 Parámetros:', { mes: mes.value, anio: anio.value })
  
  try {
    const response = await api.get('/api/compras-terceros-mensuales', {
      params: {
        mes: mes.value,
        anio: anio.value,
      }
    })
    
    console.log('📉 Respuesta compras completa:', response.data)
    
    datosCompras.value = {
      total_mes: response.data.total_mes || 0,
      cantidad: response.data.cantidad || 0,
      proveedores: response.data.proveedores || {}
    }
    
    console.log('✅ Datos compras procesados:', datosCompras.value)
    
  } catch (err) {
    console.error('❌ Error al cargar compras:', err)
    console.error('❌ Error.response:', err.response?.data)
    error.value = 'Error al cargar datos de compras: ' + (err.response?.data?.message || err.message)
  } finally {
    loadingCompras.value = false
  }
}

// ✅ Función principal
const cargarResumen = async () => {
  loading.value = true
  error.value = ''
  
  try {
    await Promise.all([
      cargarVentas(),
      cargarCompras()
    ])
  } catch (err) {
    console.error('❌ Error general:', err)
  } finally {
    loading.value = false
  }
}

// ✅ Computed para totales - usando la estructura correcta
const totalVentas = computed(() => {
  return datosVentas.value?.total_mes || 0
})

const cantidadVentas = computed(() => {
  return datosVentas.value?.cantidad || 0
})

const totalCompras = computed(() => {
  return datosCompras.value?.total_mes || 0
})

const cantidadCompras = computed(() => {
  return datosCompras.value?.cantidad || 0
})

// ✅ IVA calculado (asumiendo IVA incluido en los totales)
const ivaVentas = computed(() => {
  // Si el total incluye IVA (total / 1.19 * 0.19)
  return Math.round(totalVentas.value * 0.19 / 1.19)
})

const ivaCompras = computed(() => {
  // IVA de las compras
  return Math.round(totalCompras.value * 0.19 / 1.19)
})

const ivaAPagar = computed(() => {
  return ivaVentas.value - ivaCompras.value
})

const margenBruto = computed(() => {
  return totalVentas.value - totalCompras.value
})

const porcentajeMargen = computed(() => {
  if (!totalVentas.value) return 0
  return ((margenBruto.value / totalVentas.value) * 100).toFixed(1)
})

// ✅ Top clientes (del array de clientes)
const topClientes = computed(() => {
  if (!datosVentas.value?.clientes) return []
  return datosVentas.value.clientes
    .sort((a, b) => (b.total || 0) - (a.total || 0))
    .slice(0, 10)
})

// ✅ Top proveedores - CORREGIDO para arrays
const topCompras = computed(() => {
  if (!datosCompras.value?.proveedores) return []
  
  console.log('🔍 DEBUG tipo de proveedores:', Array.isArray(datosCompras.value.proveedores))
  console.log('🔍 DEBUG proveedores:', datosCompras.value.proveedores)
  
  // ✅ Si es un array, úsalo directamente
  if (Array.isArray(datosCompras.value.proveedores)) {
    const proveedoresArray = datosCompras.value.proveedores
      .map(proveedor => {
        console.log(`📦 Proveedor del array:`, proveedor)
        return {
          proveedor: proveedor.proveedor || proveedor.nombre || 'Sin nombre',
          cantidad: proveedor.cantidad || 0,
          total: proveedor.total || 0
        }
      })
      .sort((a, b) => b.total - a.total)
      .slice(0, 10)
    
    console.log('✅ Proveedores procesados desde array:', proveedoresArray)
    return proveedoresArray
  }
  
  // ✅ Si es un objeto, úsalo como antes
  const proveedoresArray = Object.entries(datosCompras.value.proveedores)
    .map(([nombreProveedor, data]) => {
      console.log(`📦 Procesando proveedor objeto: "${nombreProveedor}":`, data)
      return {
        proveedor: nombreProveedor,
        cantidad: data.cantidad || 0,
        total: data.total || 0
      }
    })
    .sort((a, b) => b.total - a.total)
    .slice(0, 10)
  
  console.log('✅ Proveedores procesados desde objeto:', proveedoresArray)
  return proveedoresArray
})

// ✅ Función para formatear números
const formatNumber = (number) => {
  return Number(number || 0).toLocaleString('es-CL')
}

// ✅ Watch para debuggear
watch(datosVentas, (newVal) => {
  console.log('🔄 datosVentas cambió:', newVal)
  console.log('💰 totalVentas calculado:', totalVentas.value)
}, { deep: true })

watch(datosCompras, (newVal) => {
  console.log('🔄 datosCompras cambió:', newVal)
  console.log('💰 totalCompras calculado:', totalCompras.value)
}, { deep: true })

// ✅ Cargar datos al inicio
onMounted(() => {
  cargarResumen()
})
</script>

<style scoped>
.v-card {
  transition: all 0.3s ease;
}

.v-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
</style>