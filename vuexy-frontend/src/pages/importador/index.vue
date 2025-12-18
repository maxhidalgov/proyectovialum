<template>
  <v-container class="py-6" fluid>
    <v-row justify="center">
      <v-col cols="12" md="9" lg="8">
        <v-card elevation="2">
          <v-card-title class="text-h6">
            Importar datos (CSV) con previsualización
          </v-card-title>
          <v-card-subtitle class="text-body-2">
            1) Selecciona tipo · 2) Carga CSV · 3) Revisa vista previa · 4) Importa
          </v-card-subtitle>

          <v-divider />

          <v-card-text>
            <v-row dense>
              <!-- Tipo -->
              <v-col cols="12">
                <v-radio-group v-model="tipo" inline @change="resetPreview">
                  <v-radio label="Productos" value="productos" />
                  <v-radio label="Producto-Color-Proveedor" value="pcp" />
                </v-radio-group>
              </v-col>

              <!-- Ayuda columnas -->
              <v-col cols="12">
                <v-alert type="info" variant="tonal" density="comfortable" class="mb-2">
                  <div v-if="tipo === 'productos'">
                    <strong>Columnas requeridas (con cabecera):</strong>
                    <code>nombre,tipo_producto_id,largo_total,peso_por_metro,unidad_id</code>
                  </div>
                  <div v-else>
                    <strong>Elige uno de los dos formatos (con cabecera):</strong>
                    <div class="text-caption">
                      <em>Por nombres:</em>
                      <code>producto,proveedor,color,codigo_proveedor,costo,stock</code>
                    </div>
                    <div class="text-caption">
                      <em>Por IDs:</em>
                      <code>producto_id,proveedor_id,color_id,codigo_proveedor,costo,stock</code>
                    </div>
                  </div>
                </v-alert>
              </v-col>

              <!-- File input -->
              <v-col cols="12">
                <v-file-input
                  v-model="file"
                  label="Archivo CSV"
                  accept=".csv,.txt"
                  :show-size="true"
                  clearable
                  prepend-icon="mdi-file-delimited-outline"
                  :disabled="subiendo"
                  :error-messages="fileError"
                  @change="onFileSelected"
                />
              </v-col>

              <!-- Resumen / validación -->
              <v-col cols="12" v-if="preview.ready">
                <v-alert
                  v-if="!validation.ok"
                  type="warning"
                  variant="tonal"
                  class="mb-2"
                >
                  Faltan columnas requeridas:
                  <strong>{{ validation.missing.join(', ') }}</strong>
                </v-alert>

                <v-alert type="info" variant="tonal" class="mb-2">
                  Filas totales detectadas:
                  <strong>{{ preview.totalRows.toLocaleString() }}</strong>.
                  Mostrando las primeras {{ previewRows.length }}.
                </v-alert>

                <!-- Encabezados -->
                <v-chip-group class="mb-2" column>
                  <v-chip
                    v-for="(h, i) in preview.headers"
                    :key="i"
                    size="small"
                    class="ma-1"
                    color="primary"
                    variant="tonal"
                  >
                    {{ h }}
                  </v-chip>
                </v-chip-group>

                <!-- Tabla de preview -->
                <v-data-table
                  :headers="tableHeaders"
                  :items="previewRows"
                  :items-per-page="10"
                  density="compact"
                  class="mb-4"
                />

                <!-- Warnings del parser -->
                <v-alert
                  v-if="preview.warnings.length"
                  type="warning"
                  variant="tonal"
                  class="mb-2"
                >
                  Advertencias del parser (máx 10):
                  <ul class="mt-1">
                    <li v-for="(w, i) in preview.warnings.slice(0, 10)" :key="i">
                      {{ w }}
                    </li>
                  </ul>
                </v-alert>
              </v-col>

              <!-- Acciones -->
              <v-col cols="12" class="d-flex gap-2">
                <v-btn
                  color="primary"
                  :loading="subiendo"
                  :disabled="!canImport"
                  @click="importar"
                >
                  Confirmar e importar
                </v-btn>
                <v-btn variant="text" @click="limpiar" :disabled="subiendo && !resultado">
                  Limpiar
                </v-btn>
              </v-col>

              <!-- Loader -->
              <v-col cols="12" v-if="subiendo">
                <v-progress-linear indeterminate rounded />
              </v-col>

              <!-- Resultado OK -->
              <v-col cols="12" v-if="resultado?.ok || resultado?.message">
                <v-alert type="success" variant="tonal" class="mb-2">
                  <div v-if="resultado?.message">{{ resultado.message }}</div>
                  <div v-if="resultado?.ok">Importación completada.</div>
                  <div v-if="typeof resultado?.importadas !== 'undefined'">
                    Filas importadas: <strong>{{ Number(resultado.importadas).toLocaleString() }}</strong>
                  </div>
                </v-alert>
              </v-col>

              <!-- Errores de API -->
              <v-col cols="12" v-if="apiError">
                <v-alert type="error" variant="tonal">
                  {{ apiError }}
                </v-alert>
              </v-col>

              <!-- Errores por fila -->
              <v-col cols="12" v-if="Array.isArray(resultado?.errores) && resultado.errores.length">
                <v-alert type="warning" variant="tonal" class="mb-2">
                  Se detectaron errores en algunas filas (mostrando hasta 50):
                </v-alert>
                <v-list density="compact" class="border">
                  <v-list-item
                    v-for="(err, i) in resultado.errores.slice(0, 50)"
                    :key="i"
                    :title="err"
                  />
                </v-list>
              </v-col>
            </v-row>
          </v-card-text>

          <v-divider />

          <v-card-text class="text-caption">
            <strong>Notas:</strong> La previsualización no toca la base de datos. La importación real se realiza
            al confirmar. Si tu CSV es muy grande, considera dividirlo o aumentar límites de subida.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, computed } from 'vue'
import Papa from 'papaparse'
import api from '@/axiosInstance'


const tipo = ref('productos') // 'productos' | 'pcp'
const file = ref(null)
const subiendo = ref(false)
const resultado = ref(null)
const apiError = ref('')
const fileError = ref('')

const preview = ref({
  ready: false,
  headers: [],
  rows: [],        // objetos por columna (cuando header=true)
  totalRows: 0,
  warnings: [],
})

const previewRows = computed(() => preview.value.rows.slice(0, 50)) // Muestra hasta 50

const tableHeaders = computed(() =>
  preview.value.headers.map(h => ({ title: h, value: h }))
)

const endpoint = computed(() => {
  return tipo.value === 'productos'
    ? '/api/importar-productos'
    : '/api/importar-pcp'
})

const requiredColumns = computed(() => {
  if (tipo.value === 'productos') {
    return ['nombre', 'tipo_producto_id', 'largo_total', 'peso_por_metro', 'unidad_id']
  }
  // PCP: aceptamos por nombres o por IDs (que valide al menos uno de los dos sets)
  return [
    // set A (por nombres)
    ['producto', 'proveedor', 'color', 'codigo_proveedor', 'costo', 'stock'],
    // set B (por IDs)
    ['producto_id', 'proveedor_id', 'color_id', 'codigo_proveedor', 'costo', 'stock'],
  ]
})

const validation = computed(() => {
  if (!preview.value.ready) return { ok: false, missing: [] }
  const headers = preview.value.headers.map(h => String(h).toLowerCase().trim())

  if (tipo.value === 'productos') {
    const missing = requiredColumns.value.filter(col => !headers.includes(col))
    return { ok: missing.length === 0, missing }
  } else {
    // PCP: válido si cumple A o B
    const [A, B] = requiredColumns.value
    const missingA = A.filter(col => !headers.includes(col))
    const missingB = B.filter(col => !headers.includes(col))
    const ok = (missingA.length === 0) || (missingB.length === 0)
    const missing = ok ? [] : (missingA.length <= missingB.length ? missingA : missingB)
    return { ok, missing }
  }
})

const canImport = computed(() =>
  file.value && preview.value.ready && validation.value.ok && !subiendo.value
)

function resetPreview() {
  preview.value = { ready: false, headers: [], rows: [], totalRows: 0, warnings: [] }
  resultado.value = null
  apiError.value = ''
}

function limpiar() {
  file.value = null
  fileError.value = ''
  resetPreview()
}

function onFileSelected() {
  resultado.value = null
  apiError.value = ''
  fileError.value = ''

  if (!file.value) {
    resetPreview()
    return
  }
  parseCsv(file.value)
}

function parseCsv(f) {
  resetPreview()

  Papa.parse(f, {
    header: true,          // convierte filas en objetos usando la primera fila como cabecera
    skipEmptyLines: true,
    dynamicTyping: true,   // convierte números si es posible
    worker: true,          // parse en web worker (mejor performance)
    complete: results => {
      const warnings = []
      if (results.errors && results.errors.length) {
        results.errors.forEach(e => warnings.push(`${e.type} @row ${e.row}: ${e.message}`))
      }

      // headers en lower-case para normalizar
      const headers = (results.meta?.fields || []).map(h => String(h).toLowerCase().trim())

      // normaliza keys a lower-case
      const normRows = (results.data || []).map(r => {
        const obj = {}
        for (const k in r) {
          obj[String(k).toLowerCase().trim()] = r[k]
        }
        return obj
      })

      preview.value = {
        ready: true,
        headers,
        rows: normRows,
        totalRows: normRows.length,
        warnings,
      }
    },
    error: err => {
      fileError.value = `Error al leer CSV: ${err?.message || err}`
      resetPreview()
    },
  })
}

async function importar() {
  if (!canImport.value) return
  try {
    subiendo.value = true
    apiError.value = ''
    resultado.value = null

    const formData = new FormData()
    formData.append('file', file.value)

    const { data } = await api.post(endpoint.value, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      maxBodyLength: Infinity,
    })

    resultado.value = data
  } catch (err) {
    apiError.value =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      err?.message ||
      'Error al importar'
  } finally {
    subiendo.value = false
  }
}
</script>
