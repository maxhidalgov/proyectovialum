<template>
  <div>
    <div class="d-flex align-center mb-4" style="gap:12px">
      <VIcon size="28" color="primary">mdi-warehouse</VIcon>
      <div>
        <h2 class="text-h5 mb-0">Inventario</h2>
        <div class="text-caption text-medium-emphasis">Stock de productos que entran y salen sin modificación</div>
      </div>
    </div>

    <VSnackbar v-model="snack.show" :color="snack.color" location="top right" :timeout="3000">
      {{ snack.text }}
    </VSnackbar>

    <VTabs v-model="tab" class="mb-4">
      <VTab value="stock">Stock actual</VTab>
      <VTab value="config">Configurar productos</VTab>
    </VTabs>

    <!-- ── STOCK ───────────────────────────────────────────────────────────── -->
    <div v-if="tab === 'stock'">
      <VCard class="mb-4">
        <VCardText class="d-flex align-center" style="gap:12px">
          <VTextField
            v-model="buscarStock"
            label="Buscar producto"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-magnify"
            clearable
            @update:model-value="cargarStock"
            style="max-width:360px"
          />
          <VBtn variant="tonal" :loading="loadingStock" @click="cargarStock">Actualizar</VBtn>
        </VCardText>
      </VCard>

      <VCard>
        <VDataTable :headers="headersStock" :items="stock" :items-per-page="25" density="compact" :loading="loadingStock">
          <template #item.stock="{ item }">
            <VChip :color="item.stock > 0 ? 'success' : 'error'" variant="tonal" size="small">
              {{ formatNum(item.stock) }}
            </VChip>
          </template>
          <template #item.acciones="{ item }">
            <VBtn size="x-small" variant="tonal" color="primary" @click="abrirAjuste(item)">
              <VIcon start size="14">mdi-pencil</VIcon>Ajustar
            </VBtn>
          </template>
          <template #no-data>
            <div class="pa-6 text-center text-medium-emphasis">
              No hay productos con control de stock. Actívalos en la pestaña "Configurar productos".
            </div>
          </template>
          <template #bottom>
            <div class="pa-3 text-caption text-medium-emphasis">{{ stock.length }} productos</div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- ── CONFIG ──────────────────────────────────────────────────────────── -->
    <div v-if="tab === 'config'">
      <VCard class="mb-4">
        <VCardText class="d-flex align-center" style="gap:12px">
          <VTextField
            v-model="buscarProd"
            label="Buscar producto"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-magnify"
            clearable
            @update:model-value="cargarProductos"
            style="max-width:360px"
          />
          <span class="text-caption text-medium-emphasis">Prende el interruptor de los productos que quieres controlar</span>
        </VCardText>
      </VCard>

      <VCard>
        <VDataTable :headers="headersProd" :items="productos" :items-per-page="25" density="compact" :loading="loadingProd">
          <template #item.controla_stock="{ item }">
            <VSwitch
              :model-value="!!item.controla_stock"
              color="success"
              hide-details
              density="compact"
              @update:model-value="toggle(item, $event)"
            />
          </template>
          <template #bottom>
            <div class="pa-3 text-caption text-medium-emphasis">{{ productos.length }} productos</div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- Dialog Ajustar stock -->
    <VDialog v-model="dialogAjuste" max-width="440">
      <VCard v-if="ajusteItem">
        <VCardTitle>Ajustar stock</VCardTitle>
        <VCardText>
          <div class="mb-3 font-weight-medium">{{ ajusteItem.nombre }}</div>
          <div class="text-caption text-medium-emphasis mb-3">Stock actual: {{ formatNum(ajusteItem.stock) }}</div>
          <VTextField
            v-model.number="cantidadNueva"
            type="number"
            label="Cantidad contada (real)"
            variant="outlined"
            density="compact"
            min="0"
          />
          <VTextField v-model="notaAjuste" label="Nota (opcional)" variant="outlined" density="compact" hide-details />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogAjuste = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="guardandoAjuste" @click="guardarAjuste">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from '@/axiosInstance'

const tab = ref('stock')
const snack = ref({ show: false, text: '', color: 'success' })

// Stock
const stock = ref([])
const loadingStock = ref(false)
const buscarStock = ref('')

const headersStock = [
  { title: 'Producto', value: 'nombre' },
  { title: 'Stock', value: 'stock', align: 'center' },
  { title: '', value: 'acciones', align: 'end', sortable: false },
]

async function cargarStock() {
  loadingStock.value = true
  try {
    const { data } = await axios.get('/api/inventario/stock', { params: { buscar: buscarStock.value || undefined } })
    stock.value = data
  } catch (e) {
    snack.value = { show: true, text: 'Error al cargar stock', color: 'error' }
  } finally {
    loadingStock.value = false
  }
}

// Ajuste
const dialogAjuste = ref(false)
const ajusteItem = ref(null)
const cantidadNueva = ref(0)
const notaAjuste = ref('')
const guardandoAjuste = ref(false)

function abrirAjuste(item) {
  ajusteItem.value = item
  cantidadNueva.value = Number(item.stock) || 0
  notaAjuste.value = ''
  dialogAjuste.value = true
}

async function guardarAjuste() {
  guardandoAjuste.value = true
  try {
    await axios.post('/api/inventario/set-stock', {
      producto_id: ajusteItem.value.producto_id,
      color_id: ajusteItem.value.color_id,
      cantidad: cantidadNueva.value,
      nota: notaAjuste.value || undefined,
    })
    dialogAjuste.value = false
    snack.value = { show: true, text: 'Stock actualizado', color: 'success' }
    await cargarStock()
  } catch (e) {
    snack.value = { show: true, text: e.response?.data?.message || 'Error al guardar', color: 'error' }
  } finally {
    guardandoAjuste.value = false
  }
}

// Config productos
const productos = ref([])
const loadingProd = ref(false)
const buscarProd = ref('')

const headersProd = [
  { title: 'Producto', value: 'nombre' },
  { title: 'Controla stock', value: 'controla_stock', align: 'center', sortable: false },
]

async function cargarProductos() {
  loadingProd.value = true
  try {
    const { data } = await axios.get('/api/inventario/productos', { params: { buscar: buscarProd.value || undefined } })
    productos.value = data
  } catch (e) {
    snack.value = { show: true, text: 'Error al cargar productos', color: 'error' }
  } finally {
    loadingProd.value = false
  }
}

async function toggle(item, val) {
  try {
    await axios.patch(`/api/inventario/productos/${item.id}`, { controla_stock: val })
    item.controla_stock = val
    snack.value = { show: true, text: val ? 'Activado' : 'Desactivado', color: 'success' }
  } catch (e) {
    snack.value = { show: true, text: 'No se pudo actualizar', color: 'error' }
  }
}

function formatNum(n) {
  return new Intl.NumberFormat('es-CL').format(Number(n) || 0)
}

cargarStock()
cargarProductos()
</script>
