<template>
  <v-card variant="outlined" class="mt-3">
    <v-card-title class="text-subtitle-1 pa-3 pb-0 d-flex align-center">
      <v-icon class="mr-2" size="18">mdi-receipt-text</v-icon>
      Plan de facturación
      <v-spacer />
      <v-btn v-if="!editando && !cargando" size="x-small" variant="text" @click="editando = true">
        <v-icon size="14">mdi-pencil</v-icon> Configurar
      </v-btn>
    </v-card-title>

    <v-card-text class="pa-3">
      <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-2" />

      <!-- Sin plan configurado -->
      <div v-if="!cargando && !editando && !documentos.length" class="text-center pa-4">
        <v-icon size="36" color="grey" class="mb-2">mdi-file-question-outline</v-icon>
        <p class="text-caption text-grey">Sin plan de facturación configurado</p>
        <v-btn size="small" color="primary" variant="tonal" class="mt-2" @click="editando = true">
          Configurar ahora
        </v-btn>
      </div>

      <!-- Lista de documentos -->
      <div v-else-if="!editando">
        <div
          v-for="doc in documentos"
          :key="doc.id"
          class="doc-row d-flex align-center justify-space-between pa-2 mb-1 rounded"
          :class="doc.estado === 'emitido' ? 'doc-emitido' : 'doc-pendiente'"
        >
          <div class="d-flex align-center gap-2">
            <v-icon size="16" :color="doc.estado === 'emitido' ? 'success' : 'warning'">
              {{ doc.estado === 'emitido' ? 'mdi-check-circle' : 'mdi-clock-outline' }}
            </v-icon>
            <div>
              <span class="text-body-2 font-weight-medium text-capitalize">{{ doc.tipo }}</span>
              <span class="text-caption text-grey ml-1">({{ doc.porcentaje }}%)</span>
              <span class="text-body-2 ml-2">{{ fmt(doc.monto) }}</span>
              <span v-if="doc.numero_documento_bsale" class="text-caption text-grey ml-2">
                — Doc #{{ doc.numero_documento_bsale }}
              </span>
              <span v-if="doc.fecha_emision" class="text-caption text-grey ml-1">
                {{ doc.fecha_emision }}
              </span>
            </div>
          </div>
          <div class="d-flex align-center gap-1">
            <v-btn
              v-if="doc.estado === 'emitido' && doc.url_pdf_bsale"
              icon size="x-small" variant="text" color="info"
              :href="doc.url_pdf_bsale" target="_blank"
            >
              <v-icon size="14">mdi-file-pdf-box</v-icon>
            </v-btn>
            <v-btn
              v-if="doc.estado === 'pendiente'"
              size="x-small" color="success" variant="tonal"
              @click="abrirEmitir(doc)"
            >
              Emitir
            </v-btn>
            <v-btn
              v-if="doc.estado === 'pendiente'"
              icon size="x-small" variant="text" color="error"
              @click="eliminar(doc)"
            >
              <v-icon size="14">mdi-delete</v-icon>
            </v-btn>
          </div>
        </div>

        <!-- Barra progreso cobro -->
        <div class="mt-3">
          <div class="d-flex justify-space-between text-caption mb-1">
            <span>Emitido: {{ fmt(totalEmitido) }}</span>
            <span>Pendiente: {{ fmt(totalPendiente) }}</span>
          </div>
          <v-progress-linear
            :model-value="pctEmitido"
            color="success"
            bg-color="warning"
            rounded
            height="6"
          />
        </div>
      </div>

      <!-- Editor de plan -->
      <div v-else>
        <p class="text-caption text-grey mb-3">
          Total cotización: <strong>{{ fmt(cotizacion.total) }}</strong> — Los porcentajes deben sumar 100%
        </p>

        <!-- Plantillas rápidas -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <v-chip size="small" clickable @click="aplicarPlantilla('total')">Total único</v-chip>
          <v-chip size="small" clickable @click="aplicarPlantilla('50-50')">50% anticipo + 50% saldo</v-chip>
          <v-chip size="small" clickable @click="aplicarPlantilla('30-70')">30% anticipo + 70% saldo</v-chip>
        </div>

        <div v-for="(doc, i) in plan" :key="i" class="d-flex align-center gap-2 mb-2">
          <v-select
            v-model="doc.tipo"
            :items="tiposDoc"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width:130px"
          />
          <v-text-field
            v-model.number="doc.porcentaje"
            type="number"
            density="compact"
            variant="outlined"
            hide-details
            suffix="%"
            style="max-width:90px"
            @input="recalcularMonto(doc)"
          />
          <span class="text-body-2 font-weight-medium text-no-wrap">{{ fmt(doc.monto) }}</span>
          <v-text-field
            v-model="doc.nota"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Nota"
            style="max-width:160px"
          />
          <v-btn icon size="x-small" variant="text" color="error" @click="plan.splice(i, 1)">
            <v-icon size="14">mdi-delete</v-icon>
          </v-btn>
        </div>

        <!-- Error suma -->
        <v-alert v-if="plan.length && sumaPct !== 100" type="warning" density="compact" variant="tonal" class="mb-2">
          Los porcentajes suman {{ sumaPct }}% — deben sumar 100%
        </v-alert>

        <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="agregarDoc">
          Agregar documento
        </v-btn>

        <div class="d-flex gap-2 mt-3">
          <v-btn size="small" variant="text" @click="cancelar">Cancelar</v-btn>
          <v-spacer />
          <v-btn
            size="small" color="primary" variant="flat"
            :loading="guardando"
            :disabled="sumaPct !== 100 || !plan.length"
            @click="guardar"
          >
            Guardar plan
          </v-btn>
        </div>
      </div>
    </v-card-text>

    <!-- Dialog emitir -->
    <v-dialog v-model="dialogEmitir" max-width="400">
      <v-card v-if="docEmitir">
        <v-card-title class="pa-4 pb-1 text-subtitle-1">
          Registrar documento emitido
        </v-card-title>
        <v-card-subtitle class="px-4 pb-0">
          <span class="text-capitalize">{{ docEmitir.tipo }}</span> — {{ fmt(docEmitir.monto) }}
        </v-card-subtitle>

        <v-card-text class="pa-4">
          <!-- Paso 1: generar en Bsale -->
          <div class="mb-4 pa-3 rounded" style="border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))">
            <p class="text-caption text-medium-emphasis mb-2">Paso 1 — Genera el documento en Bsale</p>
            <v-btn size="small" color="primary" variant="tonal" @click="emit('abrir-bsale')">
              <v-icon size="14" start>mdi-open-in-new</v-icon>
              Generar en Bsale
            </v-btn>
          </div>

          <!-- Paso 2: registrar número -->
          <p class="text-caption text-medium-emphasis mb-2">Paso 2 — Ingresa el número del documento generado</p>
          <v-row dense>
            <v-col cols="7">
              <v-text-field
                v-model="emitirForm.numero_documento_bsale"
                label="N° boleta / factura"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Ej: 1045"
              />
            </v-col>
            <v-col cols="5">
              <v-text-field
                v-model="emitirForm.fecha_emision"
                type="date"
                label="Fecha"
                density="compact"
                variant="outlined"
                hide-details
              />
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions class="pa-4 pt-0">
          <v-btn variant="text" size="small" @click="dialogEmitir = false">Cancelar</v-btn>
          <v-spacer />
          <v-btn color="success" variant="flat" size="small" :loading="emitiendo" @click="confirmarEmitir">
            Marcar como emitido
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/axiosInstance'

const props = defineProps({ cotizacion: { type: Object, required: true } })
const emit = defineEmits(['plan-guardado', 'abrir-bsale'])

const cargando  = ref(false)
const guardando = ref(false)
const editando  = ref(false)
const documentos = ref([])
const plan      = ref([])

const tiposDoc = ['anticipo', 'saldo', 'total']

// ── Cargar ───────────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get(`/api/cotizaciones/${props.cotizacion.id}/documentos-facturacion`)
    documentos.value = data.documentos
  } catch { /* silencioso */ }
  finally { cargando.value = false }
}

watch(() => props.cotizacion.id, cargar, { immediate: true })

// ── Totales ──────────────────────────────────────────────────────
const totalEmitido  = computed(() => documentos.value.filter(d => d.estado === 'emitido').reduce((s, d) => s + Number(d.monto), 0))
const totalPendiente = computed(() => documentos.value.filter(d => d.estado === 'pendiente').reduce((s, d) => s + Number(d.monto), 0))
const pctEmitido    = computed(() => props.cotizacion.total > 0 ? (totalEmitido.value / props.cotizacion.total) * 100 : 0)

// ── Editor ───────────────────────────────────────────────────────
const sumaPct = computed(() => plan.value.reduce((s, d) => s + (Number(d.porcentaje) || 0), 0))

function recalcularMonto(doc) {
  doc.monto = Math.round((props.cotizacion.total * (Number(doc.porcentaje) || 0)) / 100)
}

function agregarDoc() {
  const restante = 100 - sumaPct.value
  const doc = { tipo: 'saldo', porcentaje: restante > 0 ? restante : 0, monto: 0, nota: '' }
  recalcularMonto(doc)
  plan.value.push(doc)
}

function aplicarPlantilla(tipo) {
  const total = props.cotizacion.total
  if (tipo === 'total') {
    plan.value = [{ tipo: 'total', porcentaje: 100, monto: total, nota: '' }]
  } else if (tipo === '50-50') {
    plan.value = [
      { tipo: 'anticipo', porcentaje: 50, monto: Math.round(total * 0.5), nota: '' },
      { tipo: 'saldo',    porcentaje: 50, monto: Math.round(total * 0.5), nota: '' },
    ]
  } else if (tipo === '30-70') {
    plan.value = [
      { tipo: 'anticipo', porcentaje: 30, monto: Math.round(total * 0.3), nota: '' },
      { tipo: 'saldo',    porcentaje: 70, monto: Math.round(total * 0.7), nota: '' },
    ]
  }
}

function cancelar() {
  editando.value = false
  plan.value = []
}

async function guardar() {
  guardando.value = true
  try {
    const { data } = await api.post(`/api/cotizaciones/${props.cotizacion.id}/documentos-facturacion`, {
      documentos: plan.value,
    })
    // Recargar para ver también los ya emitidos
    await cargar()
    editando.value = false
    plan.value = []
    emit('plan-guardado')
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

// ── Eliminar ─────────────────────────────────────────────────────
async function eliminar(doc) {
  if (!confirm('¿Eliminar este documento pendiente?')) return
  try {
    await api.delete(`/api/documentos-facturacion/${doc.id}`)
    documentos.value = documentos.value.filter(d => d.id !== doc.id)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar')
  }
}

// ── Emitir ───────────────────────────────────────────────────────
const dialogEmitir = ref(false)
const docEmitir    = ref(null)
const emitiendo    = ref(false)
const emitirForm   = ref({ numero_documento_bsale: '', fecha_emision: new Date().toISOString().split('T')[0], url_pdf_bsale: '' })

function abrirEmitir(doc) {
  docEmitir.value  = doc
  emitirForm.value = { numero_documento_bsale: '', fecha_emision: new Date().toISOString().split('T')[0], url_pdf_bsale: '' }
  dialogEmitir.value = true
}

async function confirmarEmitir() {
  emitiendo.value = true
  try {
    const { data } = await api.patch(`/api/documentos-facturacion/${docEmitir.value.id}/emitir`, emitirForm.value)
    const idx = documentos.value.findIndex(d => d.id === data.id)
    if (idx !== -1) documentos.value[idx] = data
    dialogEmitir.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Error al marcar como emitido')
  } finally {
    emitiendo.value = false
  }
}

// ── Helpers ──────────────────────────────────────────────────────
function fmt(val) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(val || 0)
}
</script>

<style scoped>
.doc-emitido  { background: rgba(76, 175, 80, 0.08); }
.doc-pendiente { background: rgba(255, 152, 0, 0.08); }
</style>
