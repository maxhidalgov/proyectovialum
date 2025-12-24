<template>
  <v-dialog v-model="localMostrar" max-width="1200px" persistent>
    <v-card class="pa-4">
      <v-card-title class="text-h5">Editar ventana</v-card-title>
      <v-divider class="mb-4" />

      <v-form ref="formRef" @submit.prevent="onGuardar">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="ventanaLocal.tipo"
              :items="tiposVentanaFiltrados"
              item-title="nombre"
              item-value="id"
              label="Tipo de ventana"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.ancho"
              label="Ancho (mm)"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.alto"
              label="Alto (mm)"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventanaLocal.cantidad"
              label="Cantidad"
              type="number"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.material"
              :items="materiales"
              item-title="nombre"
              item-value="id"
              label="Material"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.color"
              :items="colores"
              item-title="nombre"
              item-value="id"
              label="Color"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.tipoVidrio"
              :items="tiposVidrio"
              item-title="nombre"
              item-value="id"
              label="Tipo de vidrio"
              outlined
              color="primary"
              disabled
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventanaLocal.productoVidrioProveedor"
              :items="productosVidrioDisponibles"
              item-title="nombre"
              item-value="id"
              label="Producto de vidrio"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>

        <!-- Vista previa -->
        <v-row>
          <v-col cols="12">
            <VentanaEditor
              v-if="ventanaLocal.tipo === 2"
              :ancho="ventanaLocal.ancho"
              :alto="ventanaLocal.alto"
              :color-marco="colores.find(c => c.id === ventanaLocal.color)?.nombre || 'blanco'"
              :material="ventanaLocal.material"
              :tipoVidrio="ventanaLocal.tipoVidrio"
              :productoVidrioProveedor="ventanaLocal.productoVidrioProveedor"
            />
          </v-col>
        </v-row>

        <!-- Mostrar costos -->
        <v-row>
          <v-col cols="12" md="4">
            <v-alert v-if="ventanaLocal.costo_total_unitario" type="info" variant="outlined">
              <strong>Costo unitario:</strong> ${{ ventanaLocal.costo_total_unitario }}
            </v-alert>
          </v-col>
          <v-col cols="12" md="4">
            <v-alert v-if="ventanaLocal.costo_total" type="info" variant="tonal">
              <strong>Costo total:</strong> ${{ ventanaLocal.costo_total }}
            </v-alert>
          </v-col>
          <v-col cols="12" md="4">
            <v-alert v-if="ventanaLocal.precio" type="success" variant="tonal">
              <strong>Precio de venta:</strong> ${{ ventanaLocal.precio }}
            </v-alert>
          </v-col>
        </v-row>

        <!-- Detalle de materiales -->
        <v-row v-if="ventanaLocal.materiales && ventanaLocal.materiales.length">
          <v-col cols="12">
            <v-card variant="outlined">
              <v-card-title class="d-flex align-center">
                <span>Detalle de materiales</span>
                <v-spacer />
                <v-btn 
                  color="success" 
                  variant="tonal" 
                  size="small"
                  @click="descargarMateriales"
                >
                  <v-icon left>mdi-download</v-icon>
                  Descargar Excel
                </v-btn>
              </v-card-title>
              <v-data-table
                :headers="[
                  { title: 'Material', key: 'nombre' },
                  { title: 'Proveedor', key: 'proveedor' },
                  { title: 'Cantidad', key: 'cantidad' },
                  { title: 'Unidad', key: 'unidad' },
                  { title: 'Costo unitario', key: 'costo_unitario' },
                  { title: 'Costo total', key: 'costo_total' }
                ]"
                :items="ventanaLocal.materiales"
                class="mt-2"
                dense
                :items-per-page="10"
                :items-per-page-options="[5, 10, 25, 50, { value: -1, title: 'Todos' }]"
              >
                <template #item.costo_unitario="{ item }">
                  ${{ item.costo_unitario }}
                </template>
                <template #item.costo_total="{ item }">
                  ${{ item.costo_total }}
                </template>
              </v-data-table>
            </v-card>
          </v-col>
        </v-row>

        <v-card-actions class="justify-end mt-4">
          <v-btn color="secondary" variant="text" @click="cerrar">Cancelar</v-btn>
          <v-btn color="primary" type="submit">Guardar cambios</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import VentanaEditor from '@/components/VistaVentanaFijaS60.vue'
import api from '@/axiosInstance'

const props = defineProps({
  mostrar: Boolean,
  ventana: Object,
  materiales: Array,
  colores: Array,
  tiposVidrio: Array,
  tiposVentana: Array,
  productosVidrio: Array
})

const emit = defineEmits(['update:mostrar', 'guardar'])

const localMostrar = ref(props.mostrar)
const ventanaLocal = ref({})

watch(() => props.mostrar, (val) => {
  localMostrar.value = val
  if (val && props.ventana) {
    // Simplemente clonar - datos YA vienen completos
    ventanaLocal.value = { ...props.ventana }
    console.log('✅ EDITAR MODAL - Ventana recibida:', ventanaLocal.value)
  }
})

watch(localMostrar, (val) => emit('update:mostrar', val))

const tiposVentanaFiltrados = computed(() => {
  return props.tiposVentana.filter(t => t.material_id === ventanaLocal.value.material)
})

const productosVidrioDisponibles = computed(() => {
  if (!ventanaLocal.value.tipoVidrio) return []
  
  return props.productosVidrio
    .filter(p => p.tipo_producto_id === ventanaLocal.value.tipoVidrio)
    .flatMap(p => {
      if (!p.colores_por_proveedor) return []
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Sin proveedor'})`
      }))
    })
})

const cerrar = () => {
  localMostrar.value = false
}

const onGuardar = () => {
  emit('guardar', ventanaLocal.value)
  cerrar()
}

const margenVenta = 0.45

async function recalcularCostos() {
  if (
    ventanaLocal.value.tipo &&
    ventanaLocal.value.ancho &&
    ventanaLocal.value.alto &&
    ventanaLocal.value.cantidad &&
    ventanaLocal.value.color &&
    ventanaLocal.value.productoVidrioProveedor
  ) {
    try {
      console.log('🔍 EDITAR MODAL - Buscando relación para ID:', ventanaLocal.value.productoVidrioProveedor)
      console.log('🔍 EDITAR MODAL - productosVidrio disponibles:', props.productosVidrio?.length || 0)
      
      const todasRelaciones = props.productosVidrio
        .filter(p => p.colores_por_proveedor && Array.isArray(p.colores_por_proveedor))
        .flatMap(p => p.colores_por_proveedor.map(cpp => ({
          id: cpp.id,
          producto_id: p.id,
          proveedor_id: cpp.proveedor_id
        })))
      
      console.log('🔍 EDITAR MODAL - Total relaciones encontradas:', todasRelaciones.length)
      console.log('🔍 EDITAR MODAL - IDs disponibles:', todasRelaciones.map(r => r.id))
      
      const relacion = todasRelaciones.find(p => parseInt(p.id) === parseInt(ventanaLocal.value.productoVidrioProveedor))

      if (!relacion) {
        console.error('❌ No se encontró la relación producto-proveedor para ID:', ventanaLocal.value.productoVidrioProveedor)
        console.error('❌ Todas las relaciones:', todasRelaciones)
        return
      }
      
      console.log('✅ EDITAR MODAL - Relación encontrada:', relacion)

      const payload = {
        tipo_ventana_id: ventanaLocal.value.tipo,
        tipo: ventanaLocal.value.tipo,
        ancho: ventanaLocal.value.ancho,
        alto: ventanaLocal.value.alto,
        cantidad: ventanaLocal.value.cantidad,
        color_id: ventanaLocal.value.color,
        color: ventanaLocal.value.color,
        producto_vidrio_proveedor_id: ventanaLocal.value.productoVidrioProveedor,
        producto_id: relacion.producto_id,
        proveedor_id: relacion.proveedor_id,
        productoVidrio: relacion.producto_id,
        proveedorVidrio: relacion.proveedor_id,
        tipoVidrio: ventanaLocal.value.tipoVidrio,
      }

      console.log('💰 EDITAR MODAL - Recalculando costos:', payload)
      
      const { data } = await api.post('/api/cotizador/calcular-materiales', payload)
      
      console.log('✅ EDITAR MODAL - Respuesta:', data)
      
      ventanaLocal.value.costo_total_unitario = data.costo_unitario
      ventanaLocal.value.costo_total = data.costo_unitario * ventanaLocal.value.cantidad
      ventanaLocal.value.precio = Math.ceil(ventanaLocal.value.costo_total / (1 - margenVenta))
      ventanaLocal.value.materiales = data.materiales

      console.log('💵 EDITAR MODAL - Costos actualizados:')
      console.log('   - costo_total_unitario:', ventanaLocal.value.costo_total_unitario)
      console.log('   - costo_total:', ventanaLocal.value.costo_total)
      console.log('   - precio:', ventanaLocal.value.precio)
    } catch (e) {
      console.error('❌ Error en recalcularCostos:', e)
      ventanaLocal.value.costo_total_unitario = 0
      ventanaLocal.value.costo_total = 0
      ventanaLocal.value.precio = 0
      ventanaLocal.value.materiales = []
    }
  }
}

watch(
  () => [
    ventanaLocal.value.ancho,
    ventanaLocal.value.alto,
    ventanaLocal.value.cantidad,
    ventanaLocal.value.color,
    ventanaLocal.value.productoVidrioProveedor
  ],
  recalcularCostos,
  { deep: true }
)

// Función para descargar materiales como CSV/Excel
const descargarMateriales = () => {
  if (!ventanaLocal.value.materiales || ventanaLocal.value.materiales.length === 0) {
    alert('No hay materiales para descargar')
    return
  }

  // Crear CSV
  const headers = ['Material', 'Proveedor', 'Cantidad', 'Unidad', 'Costo Unitario', 'Costo Total']
  const rows = ventanaLocal.value.materiales.map(m => [
    m.nombre || '',
    m.proveedor || '',
    m.cantidad || 0,
    m.unidad || '',
    m.costo_unitario || 0,
    m.costo_total || 0
  ])

  // Construir CSV
  let csvContent = headers.join(',') + '\n'
  rows.forEach(row => {
    csvContent += row.map(cell => {
      // Escapar comas y comillas
      const cellStr = String(cell).replace(/"/g, '""')
      return cellStr.includes(',') ? `"${cellStr}"` : cellStr
    }).join(',') + '\n'
  })

  // Crear blob y descargar
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  const tipoVentana = props.tiposVentana.find(t => t.id === ventanaLocal.value.tipo)?.nombre || 'ventana'
  const filename = `materiales_${tipoVentana}_${new Date().toISOString().split('T')[0]}.csv`
  
  link.setAttribute('href', url)
  link.setAttribute('download', filename)
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
</script>
