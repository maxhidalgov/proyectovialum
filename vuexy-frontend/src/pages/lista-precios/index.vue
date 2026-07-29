<template>
  <v-container fluid>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <div>
          <h2 class="text-h5">Lista de Precios</h2>
          <p class="text-subtitle-2 text-grey mt-1">
            Gestiona los precios de tus productos
          </p>
        </div>
        <div class="d-flex gap-2">
          <v-btn
            color="warning"
            prepend-icon="mdi-percent"
            @click="abrirAjuste"
          >
            Ajustar costos x proveedor
          </v-btn>
          <v-btn
            color="info"
            prepend-icon="mdi-upload"
            @click="dialogImportar = true"
          >
            Importar desde PCP
          </v-btn>
          <v-btn
            color="success"
            prepend-icon="mdi-download"
            @click="exportarPrecios"
          >
            Exportar Excel
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="abrirModalNuevo"
          >
            Nuevo Precio
          </v-btn>
        </div>
      </v-card-title>

      <v-divider></v-divider>

      <!-- Filtros y búsqueda -->
      <v-card-text>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="busqueda"
              label="Buscar por nombre o código"
              prepend-inner-icon="mdi-magnify"
              clearable
              density="compact"
              variant="outlined"
              hide-details
              @update:model-value="buscarPrecios"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtroActivo"
              :items="[
                { title: 'Todos', value: null },
                { title: 'Activos', value: '1' },
                { title: 'Inactivos', value: '0' }
              ]"
              label="Estado"
              density="compact"
              variant="outlined"
              hide-details
              @update:model-value="cargarPrecios"
            />
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Tabla de precios -->
      <v-data-table
        :headers="headers"
        :items="listaPrecios"
        :loading="cargando"
        :items-per-page="15"
        :items-per-page-options="[10, 15, 25, 50, 100]"
        class="elevation-1"
      >
        <template #item.producto="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.producto?.nombre }}</div>
          </div>
        </template>

        <template #item.color_proveedor="{ item }">
          <div>
            <div class="text-caption">
              <v-chip size="x-small" color="primary" class="mr-1">
                {{ item.color?.nombre || item.producto_color_proveedor?.color?.nombre || 'Sin color' }}
              </v-chip>
            </div>
            <!-- Mostrar proveedor sugerido solo internamente (tooltip) -->
            <div v-if="item.proveedor_sugerido" class="text-caption text-grey mt-1">
              <v-tooltip location="top">
                <template #activator="{ props }">
                  <span v-bind="props" class="text-decoration-underline">
                    <v-icon size="x-small">mdi-information-outline</v-icon>
                    Proveedor sugerido
                  </span>
                </template>
                <span>{{ item.proveedor_sugerido.nombre }}</span>
              </v-tooltip>
            </div>
          </div>
        </template>

        <template #item.precio_costo="{ item }">
          <span class="font-weight-medium">${{ formatearNumero(item.precio_costo) }}</span>
        </template>

        <template #item.margen="{ item }">
          <v-chip size="small" color="info">
            {{ item.margen }}%
          </v-chip>
        </template>

        <template #item.precio_venta="{ item }">
          <span class="font-weight-bold text-success">
            ${{ formatearNumero(item.precio_venta) }}
          </span>
        </template>

        <template #item.precio_venta_iva="{ item }">
          <span class="font-weight-bold text-warning">
            ${{ formatearNumero(item.precio_venta * 1.19) }}
          </span>
        </template>

        <template #item.activo="{ item }">
          <v-chip
            :color="item.activo ? 'success' : 'error'"
            size="small"
          >
            {{ item.activo ? 'Activo' : 'Inactivo' }}
          </v-chip>
        </template>

        <template #item.vigencia="{ item }">
          <div class="text-caption">
            <div>Desde: {{ formatearFecha(item.vigencia_desde) }}</div>
            <div>Hasta: {{ formatearFecha(item.vigencia_hasta) }}</div>
          </div>
        </template>

        <template #item.acciones="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-pencil"
              size="small"
              color="primary"
              variant="text"
              @click="abrirModalEditar(item)"
            ></v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              color="error"
              variant="text"
              @click="confirmarEliminar(item)"
            ></v-btn>
          </div>
        </template>

        <template #bottom>
          <div class="text-center pa-4">
            <v-chip color="primary" class="mr-2">
              Total: {{ listaPrecios.length }} precios
            </v-chip>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Modal Agregar/Editar Precio -->
    <ModalPrecio 
      v-model="dialogPrecio" 
      :precio="precioEditando"
      :modo-edicion="modoEdicion"
      @guardado="cargarPrecios"
    />

    <!-- Modal Importar desde PCP -->
    <v-dialog v-model="dialogImportar" max-width="500px">
      <v-card>
        <v-card-title class="text-h6 bg-info">
          Importar desde Producto-Color-Proveedor
        </v-card-title>

        <v-card-text class="pt-4">
          <v-alert type="info" variant="tonal" class="mb-4">
            Esta acción importará todos los productos con color y proveedor definidos,
            creando o actualizando los precios en la lista.
          </v-alert>

          <v-text-field
            v-model.number="margenImportacion"
            label="Margen por defecto (%)"
            type="number"
            suffix="%"
            variant="outlined"
            hint="Se aplicará este margen a todos los productos importados"
            persistent-hint
          />
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            color="grey"
            variant="text"
            @click="dialogImportar = false"
          >
            Cancelar
          </v-btn>
          <v-btn
            color="info"
            variant="elevated"
            :loading="importando"
            @click="importarDesdePCP"
          >
            Importar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Modal Confirmar Eliminar -->
    <v-dialog v-model="dialogEliminar" max-width="400px">
      <v-card>
        <v-card-title class="text-h6 bg-error">
          Confirmar Eliminación
        </v-card-title>

        <v-card-text class="pt-4">
          <p>¿Estás seguro de que deseas eliminar este precio?</p>
          <p class="font-weight-bold mt-2">
            {{ precioAEliminar?.producto?.nombre }}
          </p>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            color="grey"
            variant="text"
            @click="dialogEliminar = false"
          >
            Cancelar
          </v-btn>
          <v-btn
            color="error"
            variant="elevated"
            :loading="eliminando"
            @click="eliminarPrecio"
          >
            Eliminar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Ajuste masivo de costos por proveedor -->
    <v-dialog v-model="ajuste.show" max-width="620px">
      <v-card>
        <v-card-title class="d-flex align-center gap-2">
          <v-icon color="warning">mdi-percent</v-icon> Ajustar costos por proveedor
        </v-card-title>
        <v-divider />
        <v-card-text class="pt-4">
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-select
                v-model="ajuste.proveedor_id"
                :items="proveedores"
                item-title="nombre"
                item-value="id"
                label="Proveedor"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="ajuste.preview = null"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="ajuste.soloCristales"
                :items="[{title:'Todos los productos', value:false}, {title:'Solo cristales / vidrios', value:true}]"
                label="Alcance"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="ajuste.preview = null"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="ajuste.porcentaje"
                type="number"
                label="Variación de costo %"
                suffix="%"
                hint="Ej: 15 sube 15%. Usa -10 para bajar 10%."
                persistent-hint
                variant="outlined"
                density="compact"
                @update:model-value="ajuste.preview = null"
              />
            </v-col>
            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="ajuste.actualizarVenta"
                color="primary"
                hide-details
                label="Subir también el precio de venta (mantener margen)"
              />
            </v-col>
          </v-row>

          <v-alert v-if="ajuste.preview" type="info" variant="tonal" density="compact" class="mt-3">
            Se cambiarán <strong>{{ ajuste.preview.total }}</strong> combinaciones de costo.
          </v-alert>

          <v-table v-if="ajuste.preview && ajuste.preview.items.length" density="compact" class="mt-2" style="max-height:260px;overflow-y:auto">
            <thead>
              <tr><th>Producto</th><th class="text-right">Costo actual</th><th class="text-right">Costo nuevo</th></tr>
            </thead>
            <tbody>
              <tr v-for="(it, i) in ajuste.preview.items" :key="i">
                <td>{{ it.producto }}</td>
                <td class="text-right">{{ clpCosto(it.costo_actual) }}</td>
                <td class="text-right font-weight-bold text-warning">{{ clpCosto(it.costo_nuevo) }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
        <v-divider />
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="ajuste.show = false">Cancelar</v-btn>
          <v-btn color="info" variant="tonal" :loading="ajuste.loadingPreview" :disabled="!ajuste.proveedor_id || !ajuste.porcentaje" @click="previewAjuste">Vista previa</v-btn>
          <v-btn color="warning" variant="elevated" :loading="ajuste.aplicando" :disabled="!ajuste.preview || !ajuste.preview.total" @click="aplicarAjuste">Aplicar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/axiosInstance'
import ModalPrecio from './ModalPrecio.vue'

// State
const listaPrecios = ref([])
const cargando = ref(false)
const importando = ref(false)
const eliminando = ref(false)
const busqueda = ref('')
const filtroActivo = ref(null)

// Modals
const dialogPrecio = ref(false)
const dialogImportar = ref(false)
const dialogEliminar = ref(false)
const modoEdicion = ref(false)
const precioEditando = ref(null)
const precioAEliminar = ref(null)
const margenImportacion = ref(45)

// Ajuste masivo de costos por proveedor
const proveedores = ref([])
const ajuste = ref({
  show: false, proveedor_id: null, soloCristales: true, porcentaje: null,
  actualizarVenta: true, preview: null, loadingPreview: false, aplicando: false,
})
const clpCosto = v => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

async function abrirAjuste() {
  ajuste.value = { show: true, proveedor_id: null, soloCristales: true, porcentaje: null, actualizarVenta: true, preview: null, loadingPreview: false, aplicando: false }
  if (!proveedores.value.length) {
    try { const { data } = await api.get('/api/proveedores'); proveedores.value = Array.isArray(data) ? data : (data.data || []) } catch { proveedores.value = [] }
  }
}

function payloadAjuste() {
  return {
    proveedor_id: ajuste.value.proveedor_id,
    porcentaje: ajuste.value.porcentaje,
    tipos: ajuste.value.soloCristales ? [1, 2, 7] : undefined,
    actualizar_venta: ajuste.value.actualizarVenta,
  }
}

async function previewAjuste() {
  ajuste.value.loadingPreview = true
  try {
    const { data } = await api.post('/api/lista-precios/ajuste-masivo/preview', payloadAjuste())
    ajuste.value.preview = data
  } catch (e) {
    alert(e.response?.data?.message || 'Error al calcular la vista previa')
  } finally {
    ajuste.value.loadingPreview = false
  }
}

async function aplicarAjuste() {
  if (!confirm(`¿Aplicar el ajuste a ${ajuste.value.preview.total} combinaciones? Esto cambia costos${ajuste.value.actualizarVenta ? ' y precios de venta' : ''}.`)) return
  ajuste.value.aplicando = true
  try {
    const { data } = await api.post('/api/lista-precios/ajuste-masivo/aplicar', payloadAjuste())
    ajuste.value.show = false
    alert(`✓ Listo — ${data.costos_actualizados} costos y ${data.precios_actualizados} precios actualizados`)
    await cargarPrecios()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al aplicar el ajuste')
  } finally {
    ajuste.value.aplicando = false
  }
}

// Headers de la tabla
const headers = [
  { title: 'Producto', key: 'producto', sortable: true },
  { title: 'Color / Proveedor', key: 'color_proveedor', sortable: false },
  { title: 'Precio Costo (Neto)', key: 'precio_costo', sortable: true, align: 'end' },
  { title: 'Margen', key: 'margen', sortable: true, align: 'center' },
  { title: 'Precio Venta (Neto)', key: 'precio_venta', sortable: true, align: 'end' },
  { title: 'Precio Venta (c/IVA)', key: 'precio_venta_iva', sortable: false, align: 'end' },
  { title: 'Estado', key: 'activo', sortable: true, align: 'center' },
  { title: 'Vigencia', key: 'vigencia', sortable: false },
  { title: 'Acciones', key: 'acciones', sortable: false, align: 'center' }
]

// Methods
const cargarPrecios = async () => {
  cargando.value = true
  try {
    const params = {}
    if (filtroActivo.value !== null) {
      params.activo = filtroActivo.value
    }
    if (busqueda.value) {
      params.search = busqueda.value
    }

    const response = await api.get('/api/lista-precios', { params })
    listaPrecios.value = response.data
    console.log('📦 Datos cargados:', response.data) // Debug
    if (response.data.length > 0) {
      console.log('📦 Primer item:', response.data[0]) // Debug
    }
  } catch (error) {
    console.error('Error al cargar precios:', error)
    alert('Error al cargar los precios')
  } finally {
    cargando.value = false
  }
}

const buscarPrecios = () => {
  cargarPrecios()
}

const abrirModalNuevo = () => {
  modoEdicion.value = false
  precioEditando.value = null
  dialogPrecio.value = true
}

const abrirModalEditar = (precio) => {
  modoEdicion.value = true
  precioEditando.value = precio
  dialogPrecio.value = true
}

const confirmarEliminar = (precio) => {
  precioAEliminar.value = precio
  dialogEliminar.value = true
}

const eliminarPrecio = async () => {
  eliminando.value = true
  try {
    await api.delete(`/api/lista-precios/${precioAEliminar.value.id}`)
    alert('Precio eliminado correctamente')
    await cargarPrecios()
    dialogEliminar.value = false
  } catch (error) {
    console.error('Error al eliminar precio:', error)
    alert('Error al eliminar el precio')
  } finally {
    eliminando.value = false
  }
}

const importarDesdePCP = async () => {
  importando.value = true
  try {
    const response = await api.post('/api/lista-precios/importar', {
      margen: margenImportacion.value
    })
    
    alert(`Importación completada:\n- Creados: ${response.data.creados}\n- Actualizados: ${response.data.actualizados}\n- Total: ${response.data.total}`)
    await cargarPrecios()
    dialogImportar.value = false
  } catch (error) {
    console.error('Error al importar precios:', error)
    alert('Error al importar los precios')
  } finally {
    importando.value = false
  }
}

const exportarPrecios = async () => {
  try {
    const response = await api.get('/api/lista-precios/exportar')
    
    // Convertir a CSV
    if (response.data.length === 0) {
      alert('No hay datos para exportar')
      return
    }

    const headers = Object.keys(response.data[0])
    const csv = [
      headers.join(','),
      ...response.data.map(row => headers.map(h => row[h]).join(','))
    ].join('\n')

    // Descargar archivo
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `lista-precios-${new Date().toISOString().split('T')[0]}.csv`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error al exportar precios:', error)
    alert('Error al exportar los precios')
  }
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(numero) || 0)
}

const formatearFecha = (fecha) => {
  if (!fecha) return '-'
  return new Date(fecha).toLocaleDateString('es-CL')
}

// Lifecycle
onMounted(() => {
  cargarPrecios()
})
</script>

<style scoped>
.gap-2 {
  gap: 0.5rem;
}

.gap-1 {
  gap: 0.25rem;
}
</style>
