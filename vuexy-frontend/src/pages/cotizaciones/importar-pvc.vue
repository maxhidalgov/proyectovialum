<template>
  <v-container fluid>
    <v-card>
      <!-- Header -->
      <v-card-title class="d-flex align-center gap-3 pa-5">
        <v-btn icon variant="text" @click="$router.push('/cotizaciones')">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <div>
          <h2 class="text-h5">{{ modoEdicion ? 'Editar cotización WINPERFIL' : 'Importar cotización WINPERFIL' }}</h2>
          <p class="text-subtitle-2 text-grey mt-1">{{ modoEdicion ? 'Modifica los datos y guarda los cambios' : 'Sube el PDF exportado desde WINPERFIL y el sistema extraerá los datos automáticamente' }}</p>
        </div>
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-6">

        <!-- ── PASO 1: ZONA DE UPLOAD ─────────────────────────── -->
        <div v-if="!parseado" class="text-center py-10">
          <div
            class="upload-zone rounded-lg pa-10 d-flex flex-column align-center justify-center"
            :class="{ 'drag-over': arrastrando }"
            @dragover.prevent="arrastrando = true"
            @dragleave="arrastrando = false"
            @drop.prevent="onDrop"
            @click="$refs.inputPdf.click()"
          >
            <v-icon size="64" color="primary" class="mb-4">mdi-file-pdf-box</v-icon>
            <p class="text-h6 mb-1">Arrastra el PDF aquí o haz clic para seleccionarlo</p>
            <p class="text-body-2 text-grey">Presupuesto exportado desde WINPERFIL (.pdf, máx. 20MB)</p>
            <v-btn color="primary" class="mt-4" prepend-icon="mdi-upload" :loading="parseando">
              Seleccionar PDF
            </v-btn>
          </div>
          <input ref="inputPdf" type="file" accept=".pdf" class="d-none" @change="onFileSelected" />

          <!-- Error de parseo -->
          <v-alert v-if="errorParse" type="error" class="mt-4" variant="tonal">
            {{ errorParse }}
          </v-alert>
        </div>

        <!-- ── PASO 2: FORM + PREVIEW ─────────────────────────── -->
        <div v-else>

          <!-- Aviso de revisión -->
          <v-alert type="info" variant="tonal" class="mb-5" icon="mdi-information-outline">
            Los datos fueron extraídos automáticamente. Revisa y corrige si es necesario antes de guardar.
          </v-alert>

          <v-row>
            <!-- Columna izquierda: formulario -->
            <v-col cols="12" md="6">
              <v-form ref="formRef" @submit.prevent="guardar">

                <!-- Cliente -->
                <v-autocomplete
                  v-model="form.cliente"
                  v-model:search="busquedaCliente"
                  :items="clientesBuscados"
                  :loading="buscandoClientes"
                  item-title="razon_social"
                  item-value="id"
                  label="Cliente *"
                  placeholder="Buscar por nombre o RUT..."
                  prepend-inner-icon="mdi-account-search"
                  variant="outlined"
                  density="compact"
                  return-object
                  no-filter
                  hide-no-data
                  :hint="parsed.cliente_nombre ? `Nombre en PDF: ${parsed.cliente_nombre}` : ''"
                  persistent-hint
                  class="mb-3"
                  :rules="[v => !!v || 'Selecciona un cliente']"
                  @update:search="onBuscarCliente"
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props" :subtitle="item.raw.identification" />
                  </template>
                </v-autocomplete>

                <!-- Fecha + Nº Presupuesto -->
                <v-row dense class="mb-1">
                  <v-col cols="6">
                    <v-text-field
                      v-model="form.fecha"
                      label="Fecha *"
                      type="date"
                      variant="outlined"
                      density="compact"
                      :rules="[v => !!v || 'Requerida']"
                    />
                  </v-col>
                  <v-col cols="6">
                    <v-text-field
                      v-model="form.numero_presupuesto"
                      label="Nº Presupuesto WINPERFIL"
                      prepend-inner-icon="mdi-pound"
                      variant="outlined"
                      density="compact"
                    />
                  </v-col>
                </v-row>

                <!-- Observaciones -->
                <v-textarea
                  v-model="form.observaciones"
                  label="Observaciones"
                  variant="outlined"
                  density="compact"
                  rows="2"
                  auto-grow
                  class="mb-4"
                />

                <v-divider class="mb-4" />

                <!-- Tabla items -->
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-subtitle-2 font-weight-bold">Ventanas / Items</span>
                  <v-btn size="x-small" variant="tonal" color="primary" prepend-icon="mdi-plus" @click="agregarItem">
                    Agregar fila
                  </v-btn>
                </div>

                <v-table density="compact" class="border rounded mb-3">
                  <thead>
                    <tr class="bg-grey-lighten-4">
                      <th>Descripción</th>
                      <th class="text-center" style="width:60px">Cant.</th>
                      <th class="text-right" style="width:120px">Precio unit.</th>
                      <th class="text-right" style="width:110px">Total</th>
                      <th style="width:36px" class="text-center">Gráf.</th>
                      <th style="width:36px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, i) in form.items" :key="i">
                      <td class="py-1 pr-1">
                        <v-text-field
                          v-model="item.descripcion"
                          variant="plain"
                          density="compact"
                          hide-details
                          :rules="[v => !!v || '']"
                        />
                        <div v-if="item.ancho_mm && item.alto_mm" class="text-caption text-medium-emphasis pl-1" style="line-height:1">
                          {{ Number(item.ancho_mm).toLocaleString('es-CL') }} × {{ Number(item.alto_mm).toLocaleString('es-CL') }} mm
                        </div>
                      </td>
                      <td class="py-1 px-1 text-center">
                        <v-text-field
                          v-model.number="item.cantidad"
                          type="number" min="1"
                          variant="plain" density="compact" hide-details
                          style="max-width:55px" class="mx-auto"
                          @input="recalcular(item)"
                        />
                      </td>
                      <td class="py-1 px-1">
                        <v-text-field
                          v-model.number="item.precio_unitario"
                          type="number" min="0"
                          variant="plain" density="compact" hide-details
                          class="text-right"
                          @input="recalcular(item)"
                        />
                      </td>
                      <td class="py-1 px-2 text-right text-no-wrap font-weight-medium text-caption">
                        {{ fmt(item.total) }}
                      </td>
                      <td class="py-1 text-center">
                        <v-btn
                          v-if="item.winperfil_grafico"
                          icon variant="text" size="x-small" color="deep-purple"
                          title="Ver gráfico de ventana"
                          @click="svgPreview = item.winperfil_grafico.trim(); svgPreviewDesc = item.descripcion; dialogSvgPreview = true"
                        >
                          <v-icon size="16">mdi-image-outline</v-icon>
                        </v-btn>
                        <v-icon v-else size="16" color="grey-lighten-2">mdi-image-off-outline</v-icon>
                      </td>
                      <td class="py-1 text-center">
                        <v-btn icon variant="text" size="x-small" color="error"
                          :disabled="form.items.length === 1" @click="quitarItem(i)">
                          <v-icon size="16">mdi-close</v-icon>
                        </v-btn>
                      </td>
                    </tr>
                  </tbody>
                </v-table>

                <!-- Totales -->
                <v-card variant="tonal" color="primary" class="pa-3 mb-5">
                  <div class="d-flex justify-space-between text-caption mb-1">
                    <span>Total neto:</span><strong>{{ fmt(totalNeto) }}</strong>
                  </div>
                  <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                    <span>IVA 19%:</span><span>{{ fmt(totalNeto * 0.19) }}</span>
                  </div>
                  <v-divider class="my-1" />
                  <div class="d-flex justify-space-between text-subtitle-2">
                    <span>Total con IVA:</span><strong>{{ fmt(totalNeto * 1.19) }}</strong>
                  </div>
                </v-card>

                <!-- Acciones -->
                <div class="d-flex gap-3 justify-end">
                  <v-btn variant="text" @click="reiniciar">Subir otro PDF</v-btn>
                  <v-btn color="primary" type="submit" :loading="guardando" prepend-icon="mdi-content-save">
                    Guardar cotización
                  </v-btn>
                </div>

              </v-form>
            </v-col>

            <!-- Columna derecha: preview PDF -->
            <v-col cols="12" md="6">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-subtitle-2 font-weight-bold">
                  <v-icon size="18" class="mr-1">mdi-file-pdf-box</v-icon>
                  Vista previa del PDF
                </span>
                <v-chip size="small" color="success" variant="tonal">{{ archivoNombre }}</v-chip>
              </div>
              <iframe
                :src="pdfObjectUrl"
                class="pdf-preview rounded border"
                type="application/pdf"
              />
            </v-col>
          </v-row>
        </div>
      </v-card-text>
    </v-card>

    <!-- Overlay parseando -->
    <v-overlay v-model="parseando" class="align-center justify-center" persistent>
      <v-card class="pa-6 text-center" width="280">
        <v-progress-circular indeterminate color="primary" size="48" class="mb-4" />
        <p class="text-subtitle-1">Leyendo PDF WINPERFIL...</p>
        <p class="text-caption text-grey">Extrayendo datos automáticamente</p>
      </v-card>
    </v-overlay>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="top">
      {{ snackbar.message }}
    </v-snackbar>

    <!-- Dialog preview gráfico SVG -->
    <v-dialog v-model="dialogSvgPreview" max-width="800">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center pa-4">
          <span class="text-subtitle-1">
            <v-icon start color="deep-purple">mdi-window-open</v-icon>
            {{ svgPreviewDesc || 'Gráfico de ventana' }}
          </span>
          <v-btn icon variant="text" @click="dialogSvgPreview = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-card-text class="text-center pa-6 bg-grey-lighten-5">
          <img
            v-if="svgPreview"
            :src="svgPreview"
            style="max-width:100%; max-height:65vh; object-fit:contain;"
            alt="Vista de ventana"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/axiosInstance'

const router   = useRouter()
const route    = useRoute()
const formRef  = ref(null)
const inputPdf = ref(null)

// Modo edición
const editId        = ref(null)
const modoEdicion   = ref(false)

// Estado
const parseando     = ref(false)
const parseado      = ref(false)
const guardando     = ref(false)
const arrastrando   = ref(false)
const errorParse    = ref('')
const archivoNombre = ref('')
const pdfObjectUrl  = ref('')
const archivoPdf    = ref(null)   // File object original

// Datos parseados (para mostrar hint de cliente)
const parsed = ref({ cliente_nombre: '' })

// Formulario
const form = ref({
  cliente:            null,
  fecha:              '',
  numero_presupuesto: '',
  observaciones:      '',
  items:              [],
})

// ── Modo edición: cargar cotización existente ────────────────────
onMounted(async () => {
  if (route.query.edit) {
    editId.value      = route.query.edit
    modoEdicion.value = true
    parseando.value   = true
    try {
      const { data } = await api.get(`/api/cotizaciones/${editId.value}`)
      const cot = data.cotizacion ?? data

      // Pre-rellenar formulario
      form.value.fecha              = cot.fecha
      form.value.numero_presupuesto = ''
      form.value.observaciones      = cot.observaciones || ''
      form.value.items              = (cot.detalles || [])
        .filter(d => d.tipo_item === 'winperfil')
        .map(d => ({
          descripcion:        d.descripcion,
          cantidad:           Number(d.cantidad),
          precio_unitario:    Number(d.precio_unitario),
          total:              Number(d.cantidad) * Number(d.precio_unitario),
          winperfil_grafico:  d.winperfil_grafico || null,
          ancho_mm:           d.ancho_mm || null,
          alto_mm:            d.alto_mm || null,
        }))
      if (!form.value.items.length) form.value.items.push(itemVacio())

      // Cargar cliente
      if (cot.cliente) {
        const c = cot.cliente
        form.value.cliente = {
          id:           c.id,
          razon_social: c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim(),
          identification: c.identification || '',
        }
        clientesBuscados.value = [form.value.cliente]
      }

      // PDF adjunto
      if (cot.adjunto_winperfil) {
        pdfObjectUrl.value  = cot.adjunto_winperfil
        archivoNombre.value = 'PDF adjunto en R2'
      }

      parseado.value = true
    } catch (e) {
      errorParse.value = 'No se pudo cargar la cotización.'
    } finally {
      parseando.value = false
    }
  }
})

// Búsqueda cliente
const buscandoClientes  = ref(false)
const clientesBuscados  = ref([])
const busquedaCliente   = ref('')
let debounceTimer = null

function onBuscarCliente(val) {
  clearTimeout(debounceTimer)
  if (!val || val.length < 2) { clientesBuscados.value = []; return }
  debounceTimer = setTimeout(() => buscarClientes(val), 300)
}

async function buscarClientes(q) {
  buscandoClientes.value = true
  try {
    const { data } = await api.get(`/api/clientes/buscar?q=${encodeURIComponent(q)}`)
    clientesBuscados.value = data.map(c => ({
      id:           c.id,
      razon_social: c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Sin nombre',
      identification: c.identification || '',
    }))
  } catch { clientesBuscados.value = [] }
  finally  { buscandoClientes.value = false }
}

// ── Upload ──────────────────────────────────────────────────────
function onDrop(e) {
  arrastrando.value = false
  const file = e.dataTransfer.files[0]
  if (file?.type === 'application/pdf') procesarArchivo(file)
}

function onFileSelected(e) {
  const file = e.target.files[0]
  if (file) procesarArchivo(file)
}

async function procesarArchivo(file) {
  archivoPdf.value    = file
  archivoNombre.value = file.name
  pdfObjectUrl.value  = URL.createObjectURL(file)
  errorParse.value    = ''
  parseando.value     = true

  try {
    const fd = new FormData()
    fd.append('pdf', file)
    const { data } = await api.post('/api/cotizaciones/parse-winperfil', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    parsed.value = data

    // Pre-rellenar form
    form.value.fecha              = data.fecha || new Date().toISOString().split('T')[0]
    form.value.numero_presupuesto = data.numero_presupuesto || ''
    form.value.items              = (data.items || []).map(i => ({ ...i }))

    // Si no hay items parseados, agregar uno vacío
    if (!form.value.items.length) form.value.items.push(itemVacio())

    // Buscar cliente automáticamente si hay nombre
    if (data.cliente_nombre) {
      busquedaCliente.value = data.cliente_nombre
      await buscarClientes(data.cliente_nombre)
      if (clientesBuscados.value.length === 1) {
        form.value.cliente = clientesBuscados.value[0]
      }
    }

    parseado.value = true
  } catch (e) {
    errorParse.value = e.response?.data?.error || 'No se pudo leer el PDF. Intenta de nuevo.'
    pdfObjectUrl.value = ''
  } finally {
    parseando.value = false
    if (inputPdf.value) inputPdf.value.value = ''
  }
}

// ── Items ────────────────────────────────────────────────────────
function itemVacio() {
  return { descripcion: '', cantidad: 1, precio_unitario: 0, total: 0, winperfil_grafico: null, ancho_mm: null, alto_mm: null }
}
function agregarItem()   { form.value.items.push(itemVacio()) }
function quitarItem(i)   { form.value.items.splice(i, 1) }
function recalcular(item) { item.total = (Number(item.cantidad) || 0) * (Number(item.precio_unitario) || 0) }

const totalNeto = computed(() =>
  form.value.items.reduce((s, i) => s + (i.total || 0), 0)
)

// ── Guardar ──────────────────────────────────────────────────────
async function guardar() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  if (!form.value.cliente?.id) { mostrarSnack('Selecciona un cliente', 'error'); return }
  if (form.value.items.some(i => !i.descripcion?.trim())) {
    mostrarSnack('Todos los items deben tener descripción', 'error'); return
  }

  guardando.value = true
  try {
    const fd = new FormData()
    fd.append('cliente_id',          form.value.cliente.id)
    fd.append('fecha',               form.value.fecha)
    fd.append('numero_presupuesto',  form.value.numero_presupuesto)
    fd.append('observaciones',       form.value.observaciones)
    if (archivoPdf.value) fd.append('pdf', archivoPdf.value)

    form.value.items.forEach((item, i) => {
      fd.append(`items[${i}][descripcion]`,     item.descripcion)
      fd.append(`items[${i}][cantidad]`,         item.cantidad)
      fd.append(`items[${i}][precio_unitario]`,  item.precio_unitario)
    })

    if (modoEdicion.value) {
      await api.post(`/api/cotizaciones/${editId.value}/actualizar-winperfil`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      mostrarSnack('Cotización actualizada correctamente', 'success')
    } else {
      await api.post('/api/cotizaciones/importar-winperfil', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      mostrarSnack('Cotización importada correctamente', 'success')
    }

    setTimeout(() => router.push('/cotizaciones'), 1200)
  } catch (e) {
    mostrarSnack(e.response?.data?.message || 'Error al guardar', 'error')
  } finally {
    guardando.value = false
  }
}

function reiniciar() {
  parseado.value      = false
  pdfObjectUrl.value  = ''
  archivoPdf.value    = null
  archivoNombre.value = ''
  errorParse.value    = ''
  form.value          = { cliente: null, fecha: '', numero_presupuesto: '', observaciones: '', items: [] }
  parsed.value        = { cliente_nombre: '' }
}

// ── SVG Preview dialog ───────────────────────────────────────────
const dialogSvgPreview = ref(false)
const svgPreview       = ref('')
const svgPreviewDesc   = ref('')

// ── Helpers ──────────────────────────────────────────────────────
const snackbar = ref({ show: false, message: '', color: 'success' })
function mostrarSnack(message, color = 'success') { snackbar.value = { show: true, message, color } }

function fmt(val) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
  }).format(val || 0)
}
</script>

<style scoped>
.upload-zone {
  border: 2px dashed rgba(var(--v-theme-primary), 0.4);
  cursor: pointer;
  transition: all 0.2s;
  min-height: 280px;
}
.upload-zone:hover,
.drag-over {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.05);
}
.pdf-preview {
  width: 100%;
  height: 680px;
  border: none;
}
</style>
