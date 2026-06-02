<template>
  <div class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-5 flex-wrap gap-3">
      <div>
        <h5 class="text-h5 font-weight-bold d-flex align-center gap-2">
          <v-icon color="warning" size="28">mdi-lightning-bolt</v-icon>
          Sugerencias de conciliación
        </h5>
        <p class="text-medium-emphasis text-body-2 mb-0">
          Revisa las parejas sugeridas y apruébalas una a una o todas a la vez.
        </p>
      </div>

      <div class="d-flex gap-2 align-center">
        <v-btn variant="tonal" size="small" :loading="cargando" @click="cargar">
          <v-icon start size="16">mdi-refresh</v-icon>Actualizar
        </v-btn>
        <v-btn
          v-if="pendientes.length > 0"
          color="warning"
          variant="flat"
          size="small"
          :loading="conciliandoTodas"
          @click="conciliarTodas"
        >
          <v-icon start size="16">mdi-lightning-bolt</v-icon>
          Conciliar todas ({{ pendientes.length }})
        </v-btn>
      </div>
    </div>

    <!-- Estado vacío / cargando -->
    <div v-if="cargando" class="d-flex flex-column gap-3">
      <v-skeleton-loader v-for="i in 3" :key="i" type="card" height="130" />
    </div>

    <v-card v-else-if="pendientes.length === 0" variant="flat" rounded="lg" class="text-center pa-10">
      <v-icon size="56" color="success" class="mb-3">mdi-check-circle-outline</v-icon>
      <p class="text-h6 font-weight-medium mb-1">Sin sugerencias pendientes</p>
      <p class="text-body-2 text-medium-emphasis">
        No se encontraron pares movimiento ↔ documento con coincidencias de RUT o monto.
      </p>
    </v-card>

    <!-- Lista de sugerencias -->
    <div v-else class="d-flex flex-column gap-3">
      <v-card
        v-for="sug in pendientes"
        :key="sug.id"
        rounded="lg"
        variant="flat"
        :class="['sugerencia-card', { 'opacity-50': conciliando[sug.id] }]"
      >
        <v-card-text class="pa-0">
          <v-row no-gutters align="center">

            <!-- Movimiento bancario -->
            <v-col cols="12" md="5" class="pa-4 border-e">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption text-medium-emphasis">Movimiento bancario</span>
                <v-chip
                  size="x-small"
                  :color="sug.movimiento.tipo === 'C' ? 'success' : 'error'"
                  variant="tonal"
                >
                  {{ sug.movimiento.tipo === 'C' ? 'Abono' : 'Cargo' }}
                </v-chip>
              </div>
              <p class="text-body-2 font-weight-medium mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ sug.movimiento.descripcion }}
              </p>
              <p v-if="sug.movimiento.glosa" class="text-caption text-medium-emphasis mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ sug.movimiento.glosa }}
              </p>
              <div class="d-flex justify-space-between align-center mt-2">
                <span class="text-caption text-medium-emphasis">{{ fmtFecha(sug.movimiento.fecha_contable) }}</span>
                <span class="text-body-1 font-weight-bold" :class="sug.movimiento.tipo === 'C' ? 'text-success' : 'text-error'">
                  {{ sug.movimiento.tipo === 'D' ? '-' : '+' }}{{ clp(sug.movimiento.monto) }}
                </span>
              </div>
            </v-col>

            <!-- Centro: ícono + razón -->
            <v-col cols="12" md="2" class="d-flex flex-column align-center justify-center pa-3 gap-2">
              <v-icon color="warning" size="22">mdi-link-variant</v-icon>
              <v-chip
                size="x-small"
                :color="colorRazon(sug.razon)"
                variant="tonal"
                class="text-center"
              >
                <v-icon start size="10">{{ iconoRazon(sug.razon) }}</v-icon>
                {{ labelRazon(sug.razon) }}
              </v-chip>
            </v-col>

            <!-- Documento de respaldo -->
            <v-col cols="12" md="5" class="pa-4">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption text-medium-emphasis">Documento de respaldo</span>
                <v-chip size="x-small" :color="sug.tipo_documento === 'compra' ? 'error' : 'success'" variant="tonal" class="text-capitalize">
                  {{ sug.tipo_documento === 'compra' ? 'Compra' : 'Venta' }}
                </v-chip>
              </div>
              <p class="text-body-2 font-weight-medium mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ sug.tipo_documento === 'compra' ? sug.documento.nombre_emisor : sug.documento.nombre_cliente }}
              </p>
              <p class="text-caption text-medium-emphasis mb-1">
                {{ sug.tipo_documento === 'compra' ? sug.documento.rut_emisor : sug.documento.rut_cliente }}
                <span v-if="sug.documento.numero_documento_bsale" class="ml-2">· Doc #{{ sug.documento.numero_documento_bsale }}</span>
                <span v-if="sug.documento.folio" class="ml-2">· Folio {{ sug.documento.folio }}</span>
              </p>
              <div class="d-flex justify-space-between align-center mt-2">
                <span class="text-caption text-medium-emphasis">{{ fmtFecha(sug.documento.fecha_emision) }}</span>
                <div class="text-end">
                  <span class="text-body-1 font-weight-bold">{{ clp(sug.monto_sugerido) }}</span>
                  <span v-if="sug.documento.saldo_pendiente > sug.monto_sugerido" class="text-caption text-medium-emphasis d-block">
                    Saldo total: {{ clp(sug.documento.saldo_pendiente) }}
                  </span>
                </div>
              </div>
            </v-col>

          </v-row>

          <!-- Footer: acciones -->
          <v-divider />
          <div class="d-flex align-center justify-end gap-3 pa-3">
            <v-btn
              variant="text"
              color="error"
              size="small"
              :disabled="conciliando[sug.id]"
              @click="rechazar(sug.id)"
            >
              Rechazar
            </v-btn>
            <v-btn
              color="warning"
              variant="flat"
              size="small"
              :loading="conciliando[sug.id]"
              @click="conciliar(sug)"
            >
              <v-icon start size="14">mdi-lightning-bolt</v-icon>
              Conciliar
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Snack -->
    <v-snackbar v-model="snack.show" :color="snack.color" location="top right" :timeout="4000">
      {{ snack.text }}
      <template #actions>
        <v-btn variant="text" @click="snack.show = false">Cerrar</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/axiosInstance'

const cargando        = ref(true)
const todasSugerencias = ref([])
const rechazadas      = ref(new Set())   // IDs rechazados localmente esta sesión
const conciliando     = ref({})
const conciliandoTodas = ref(false)
const snack           = ref({ show: false, text: '', color: 'success' })

const pendientes = computed(() =>
  todasSugerencias.value.filter(s => !rechazadas.value.has(s.id))
)

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/conciliacion/sugerencias')
    todasSugerencias.value = data
    rechazadas.value = new Set()
  } catch (e) {
    mostrarSnack('Error al cargar sugerencias', 'error')
  } finally {
    cargando.value = false
  }
}

async function conciliar(sug) {
  conciliando.value[sug.id] = true
  try {
    if (sug.tipo_documento === 'compra') {
      await api.post(`/api/compras/${sug.documento.id}/movimientos`, {
        movimiento_id: sug.movimiento.id,
        monto: sug.monto_sugerido,
      })
    } else {
      await api.post(`/api/ventas/${sug.documento.id}/movimientos`, {
        movimiento_id: sug.movimiento.id,
        monto: sug.monto_sugerido,
      })
    }
    rechazadas.value.add(sug.id)   // sacar de la vista
    mostrarSnack('Conciliación registrada ✓')
  } catch (e) {
    const msg = e.response?.data?.message || 'Error al conciliar'
    mostrarSnack(msg, 'error')
  } finally {
    conciliando.value[sug.id] = false
  }
}

function rechazar(id) {
  rechazadas.value = new Set([...rechazadas.value, id])
}

async function conciliarTodas() {
  conciliandoTodas.value = true
  let ok = 0
  let err = 0
  for (const sug of pendientes.value) {
    try {
      if (sug.tipo_documento === 'compra') {
        await api.post(`/api/compras/${sug.documento.id}/movimientos`, {
          movimiento_id: sug.movimiento.id,
          monto: sug.monto_sugerido,
        })
      } else {
        await api.post(`/api/ventas/${sug.documento.id}/movimientos`, {
          movimiento_id: sug.movimiento.id,
          monto: sug.monto_sugerido,
        })
      }
      rechazadas.value.add(sug.id)
      ok++
    } catch {
      err++
    }
  }
  conciliandoTodas.value = false
  mostrarSnack(`${ok} conciliadas${err ? ` · ${err} errores` : ''}`, err ? 'warning' : 'success')
}

// ── Helpers ──────────────────────────────────────────────────────────────
const clp = (n) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)

const fmtFecha = (f) => f ? new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

function colorRazon(razon) {
  return { rut: 'info', monto: 'warning', descripcion: 'secondary' }[razon] ?? 'grey'
}
function iconoRazon(razon) {
  return { rut: 'mdi-card-account-details-outline', monto: 'mdi-currency-usd', descripcion: 'mdi-text-search' }[razon] ?? 'mdi-help'
}
function labelRazon(razon) {
  return { rut: 'Por RUT', monto: 'Por monto', descripcion: 'Por descripción' }[razon] ?? razon
}

function mostrarSnack(text, color = 'success') {
  snack.value = { show: true, text, color }
}

cargar()
</script>

<style scoped>
.sugerencia-card {
  transition: opacity 0.2s;
}
</style>
