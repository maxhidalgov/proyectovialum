<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-3">
        <v-icon icon="mdi-factory" size="32" color="primary" />
        <div>
          <h1 class="text-h5 font-weight-bold">Taller de Fabricación</h1>
          <p class="text-caption text-grey mt-1">Seguimiento de etapas por cotización</p>
        </div>
      </div>
      <div class="d-flex gap-2">
        <v-btn variant="tonal" prepend-icon="mdi-refresh" size="small" @click="cargar" :loading="cargando">
          Actualizar
        </v-btn>
      </div>
    </div>

    <!-- Stat cards -->
    <v-row class="mb-4" dense>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="orange" class="pa-3 text-center">
          <v-icon color="orange" size="22" class="mb-1">mdi-wrench</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.en_fabricacion ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">En Fabricación</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="blue" class="pa-3 text-center">
          <v-icon color="blue" size="22" class="mb-1">mdi-scissors-cutting</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.lista_corte ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Lista para Corte</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="green" class="pa-3 text-center">
          <v-icon color="green" size="22" class="mb-1">mdi-check-circle</v-icon>
          <div class="text-h6 font-weight-bold">{{ stats.fabricadas_ok ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Fabricadas OK</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="tonal" color="error" class="pa-3 text-center">
          <v-icon color="error" size="22" class="mb-1">mdi-calendar-alert</v-icon>
          <div class="text-h6 font-weight-bold">{{ vencidas }}</div>
          <div class="text-caption text-medium-emphasis">Entregas vencidas</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtro -->
    <div class="d-flex gap-3 mb-4 align-center flex-wrap">
      <v-text-field
        v-model="busqueda"
        prepend-inner-icon="mdi-magnify"
        label="Buscar cliente..."
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:280px"
      />
      <v-btn-toggle v-model="filtroEstado" color="primary" variant="outlined" density="compact">
        <v-btn value="">Todos</v-btn>
        <v-btn value="En Fabricación">Fabricando</v-btn>
        <v-btn value="Lista para Corte">Lista</v-btn>
        <v-btn value="Fabricadas OK">OK</v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-4" />

    <!-- Sin resultados -->
    <v-card v-if="!cargando && cotizacionesFiltradas.length === 0" class="pa-8 text-center" variant="outlined">
      <v-icon size="48" color="grey" class="mb-3">mdi-factory</v-icon>
      <div class="text-body-1 text-grey">No hay cotizaciones en producción</div>
      <div class="text-caption text-grey mt-1">Las cotizaciones aparecen aquí cuando tienen estado "Lista para Corte" o "En Fabricación"</div>
    </v-card>

    <!-- Tarjetas de cotización -->
    <div v-for="cotizacion in cotizacionesFiltradas" :key="cotizacion.id" class="mb-4">
      <v-card
        :class="['cotizacion-card', `urgencia-${cotizacion.urgencia}`]"
        variant="outlined"
      >
        <!-- Header de la tarjeta -->
        <v-card-text class="pb-2">
          <div class="d-flex align-start justify-space-between gap-2 flex-wrap">
            <div class="d-flex align-center gap-3">
              <div>
                <div class="d-flex align-center gap-2">
                  <span class="text-h6 font-weight-bold">{{ cotizacion.cliente }}</span>
                  <span class="text-caption text-grey">#{{ cotizacion.id }}</span>
                  <v-chip
                    size="x-small"
                    :color="colorEstado(cotizacion.estado_produccion)"
                    variant="flat"
                  >
                    {{ cotizacion.estado_produccion }}
                  </v-chip>
                </div>
                <div class="d-flex align-center gap-3 mt-1 flex-wrap">
                  <span class="text-caption text-grey">
                    <v-icon size="12">mdi-ruler-square</v-icon>
                    {{ cotizacion.m2 }} m² · {{ cotizacion.cant_ventanas }} ventanas
                  </span>
                  <span
                    v-if="cotizacion.fecha_entrega"
                    class="text-caption"
                    :class="claseEntrega(cotizacion)"
                  >
                    <v-icon size="12">mdi-calendar</v-icon>
                    Entrega: {{ fmtFecha(cotizacion.fecha_entrega) }}
                    <span v-if="cotizacion.dias_para_entrega !== null">
                      ({{ cotizacion.dias_para_entrega >= 0 ? `en ${cotizacion.dias_para_entrega}d` : `${Math.abs(cotizacion.dias_para_entrega)}d vencida` }})
                    </span>
                  </span>
                  <v-chip v-if="cotizacion.fabricar_termopanel" size="x-small" color="teal" variant="tonal">Termopanel</v-chip>
                  <v-chip v-if="cotizacion.cortar_vidrio_cnc" size="x-small" color="purple" variant="tonal">Vidrio CNC</v-chip>
                </div>
              </div>
            </div>

            <!-- Progreso + acciones -->
            <div class="d-flex align-center gap-2">
              <div class="text-center" style="min-width:80px">
                <v-progress-circular
                  :model-value="cotizacion.progreso"
                  :color="cotizacion.progreso === 100 ? 'success' : 'primary'"
                  size="42"
                  width="4"
                >
                  <span class="text-caption font-weight-bold">{{ cotizacion.progreso }}%</span>
                </v-progress-circular>
              </div>
              <div class="d-flex flex-column gap-1">
                <v-btn
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-scissors-cutting"
                  :to="{ name: 'produccion-id', params: { id: cotizacion.id } }"
                >
                  Hoja Cortes
                </v-btn>
                <v-btn
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  prepend-icon="mdi-clipboard-list"
                  :to="{ name: 'produccion-materiales-id', params: { id: cotizacion.id } }"
                >
                  Materiales
                </v-btn>
                <v-btn
                  v-if="cotizacion.winperfil_numero"
                  size="x-small"
                  color="deep-purple"
                  variant="tonal"
                  prepend-icon="mdi-window-open"
                  @click="abrirMateriales(cotizacion)"
                >
                  Materiales WP
                </v-btn>
              </div>
            </div>
          </div>

          <!-- Barra de progreso -->
          <v-progress-linear
            :model-value="cotizacion.progreso"
            :color="cotizacion.progreso === 100 ? 'success' : 'primary'"
            rounded
            height="4"
            class="mt-3 mb-3"
          />

          <!-- Etapas -->
          <div class="etapas-row">
            <div
              v-for="etapa in cotizacion.etapas"
              :key="etapa.etapa"
              class="etapa-item"
              :class="`etapa--${etapa.estado}`"
            >
              <div class="etapa-icon-wrap">
                <v-icon size="16" :color="colorEtapa(etapa.estado)">{{ iconoEtapa(etapa.etapa) }}</v-icon>
              </div>
              <div class="etapa-label">{{ etapa.label }}</div>
              <div v-if="etapa.empleado" class="etapa-empleado">{{ primerNombre(etapa.empleado) }}</div>
              <div class="etapa-actions">
                <v-btn
                  v-if="etapa.estado === 'pendiente'"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'en_progreso')"
                >
                  Iniciar
                </v-btn>
                <v-btn
                  v-else-if="etapa.estado === 'en_progreso'"
                  size="x-small"
                  color="success"
                  variant="flat"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'completado')"
                >
                  ✓ Listo
                </v-btn>
                <v-btn
                  v-else
                  size="x-small"
                  color="grey"
                  variant="plain"
                  block
                  @click="cambiarEtapa(cotizacion, etapa, 'pendiente')"
                >
                  Reabrir
                </v-btn>
              </div>
            </div>
          </div>

          <!-- Notas -->
          <div v-if="cotizacion.notas_operaciones" class="mt-2">
            <v-chip size="x-small" color="grey" variant="tonal" prepend-icon="mdi-note-text">
              {{ cotizacion.notas_operaciones }}
            </v-chip>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Dialog asignar empleado -->
    <v-dialog v-model="dialogEtapa.show" max-width="380">
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold pa-4">
          {{ dialogEtapa.etapaLabel }} — {{ dialogEtapa.cliente }}
        </v-card-title>
        <v-card-text>
          <v-select
            v-model="dialogEtapa.empleadoId"
            :items="empleados"
            item-title="nombre"
            item-value="id"
            label="Asignar a empleado (opcional)"
            variant="outlined"
            density="compact"
            clearable
            class="mb-3"
          />
          <v-textarea
            v-model="dialogEtapa.notas"
            label="Notas (opcional)"
            variant="outlined"
            density="compact"
            rows="2"
            hide-details
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialogEtapa.show = false">Cancelar</v-btn>
          <v-btn color="primary" variant="flat" :loading="guardando" @click="confirmarEtapa">
            Guardar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Dialog Materiales Winperfil -->
    <v-dialog v-model="dialogMat.show" max-width="1100" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="deep-purple">mdi-window-open</v-icon>
          Materiales — {{ dialogMat.cliente }}
          <span class="text-caption text-medium-emphasis">WP {{ dialogMat.serie }}-{{ dialogMat.numero }}</span>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="dialogMat.show = false" />
        </v-card-title>

        <v-tabs v-model="dialogMat.tab" color="deep-purple" class="px-4">
          <v-tab value="compra"><v-icon size="16" class="mr-1">mdi-cart</v-icon>Lista de compra</v-tab>
          <v-tab value="ventana"><v-icon size="16" class="mr-1">mdi-format-list-bulleted</v-icon>Despiece por ventana</v-tab>
          <v-tab value="cortes"><v-icon size="16" class="mr-1">mdi-content-cut</v-icon>Hoja de cortes</v-tab>
        </v-tabs>
        <v-divider />

        <v-card-text style="min-height:300px">
          <div v-if="dialogMat.loading" class="text-center py-10">
            <v-progress-circular indeterminate color="deep-purple" />
            <div class="text-caption text-medium-emphasis mt-3">Consultando Winperfil...</div>
          </div>

          <v-alert v-else-if="dialogMat.error" type="warning" variant="tonal" density="compact">
            {{ dialogMat.error }}
          </v-alert>

          <v-window v-else v-model="dialogMat.tab">
            <!-- TAB compra -->
            <v-window-item value="compra">
              <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2">
                <div class="text-caption text-medium-emphasis">
                  Marca lo que quieras pedir y genera una orden de compra.
                </div>
                <div class="d-flex align-center gap-2">
                  <v-chip v-if="nSeleccionados" size="small" color="deep-purple" variant="tonal">
                    {{ nSeleccionados }} seleccionado{{ nSeleccionados !== 1 ? 's' : '' }}
                  </v-chip>
                  <v-btn
                    color="deep-purple"
                    size="small"
                    prepend-icon="mdi-cart-arrow-down"
                    :disabled="!nSeleccionados"
                    @click="abrirDialogOrden"
                  >
                    Generar orden de compra
                  </v-btn>
                </div>
              </div>

              <div v-for="grupo in gruposCompra" :key="grupo.key" class="mb-4">
                <div v-if="grupo.items.length" class="text-subtitle-2 font-weight-bold mb-1 d-flex align-center gap-1">
                  <v-checkbox
                    :model-value="grupoTodoSeleccionado(grupo)"
                    :indeterminate="grupoParcial(grupo)"
                    density="compact"
                    hide-details
                    color="deep-purple"
                    @update:model-value="toggleGrupo(grupo, $event)"
                  />
                  <v-icon size="16" :color="grupo.color">{{ grupo.icon }}</v-icon>{{ grupo.label }}
                  <v-chip size="x-small" :color="grupo.color" variant="tonal">{{ grupo.items.length }}</v-chip>
                </div>
                <v-table v-if="grupo.items.length" density="compact" class="mb-2">
                  <thead>
                    <tr>
                      <th style="width:40px"></th>
                      <th class="text-left">Referencia</th>
                      <th class="text-left">Descripción</th>
                      <th v-if="grupo.key === 'vidrios'" class="text-left">Medida (mm)</th>
                      <th v-if="grupo.key === 'perfiles'" class="text-right">Metros</th>
                      <th class="text-right">{{ grupo.key === 'perfiles' ? 'Piezas' : 'Cant.' }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(it, i) in grupo.items" :key="i" @click="toggleItem(grupo.key, i)" style="cursor:pointer">
                      <td>
                        <v-checkbox
                          :model-value="isSelected(grupo.key, i)"
                          density="compact"
                          hide-details
                          color="deep-purple"
                          @click.stop
                          @update:model-value="toggleItem(grupo.key, i)"
                        />
                      </td>
                      <td class="font-monospace text-caption">{{ it.referencia || '—' }}</td>
                      <td class="text-caption">{{ it.descripcion }}</td>
                      <td v-if="grupo.key === 'vidrios'" class="text-caption">{{ it.ancho }} × {{ it.alto }}</td>
                      <td v-if="grupo.key === 'perfiles'" class="text-right text-caption">{{ it.metros_lineales }} m</td>
                      <td class="text-right font-weight-bold">{{ grupo.key === 'perfiles' ? it.piezas : it.cantidad }}</td>
                    </tr>
                  </tbody>
                </v-table>
              </div>
            </v-window-item>

            <!-- TAB por ventana -->
            <v-window-item value="ventana">
              <v-expansion-panels multiple>
                <v-expansion-panel v-for="(m, i) in dialogMat.data?.modelos || []" :key="i">
                  <v-expansion-panel-title>
                    <div>
                      <v-chip size="x-small" color="deep-purple" variant="tonal" class="mr-2">{{ m.cantidad }}×</v-chip>
                      <span class="text-body-2">{{ m.descripcion }}</span>
                    </div>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <div v-for="(items, tipo) in m.grupos" :key="tipo" class="mb-3">
                      <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1">{{ tipo }}</div>
                      <v-table density="compact">
                        <tbody>
                          <tr v-for="(it, j) in items" :key="j">
                            <td class="font-monospace text-caption" style="width:120px">{{ it.referencia || '—' }}</td>
                            <td class="text-caption">{{ it.descripcion }}<span v-if="it.ubicacion" class="text-medium-emphasis"> · {{ it.ubicacion }}</span></td>
                            <td class="text-caption text-right" style="width:110px">
                              <span v-if="it.longitud">{{ it.longitud }}<span v-if="it.alto">×{{ it.alto }}</span> mm</span>
                            </td>
                            <td v-if="it.corte_izq !== null" class="text-caption text-right" style="width:70px">{{ it.corte_izq }}°/{{ it.corte_der }}°</td>
                            <td class="text-right font-weight-bold" style="width:50px">{{ it.total }}</td>
                          </tr>
                        </tbody>
                      </v-table>
                    </div>
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>
            </v-window-item>

            <!-- TAB hoja de cortes -->
            <v-window-item value="cortes">
              <div v-if="hojaCortes.loading" class="text-center py-10">
                <v-progress-circular indeterminate color="deep-purple" />
                <div class="text-caption text-medium-emphasis mt-3">Calculando optimización de barras...</div>
              </div>
              <v-alert v-else-if="hojaCortes.error" type="warning" variant="tonal" density="compact">
                {{ hojaCortes.error }}
              </v-alert>
              <template v-else-if="hojaCortes.data">
                <div class="d-flex align-center justify-space-between mb-3 gap-2 flex-wrap">
                  <v-alert type="info" variant="tonal" density="compact" class="text-caption mb-0 flex-grow-1">
                    Optimización estimada (bin-packing). Puede diferir en 1 barra respecto a Winperfil hasta habilitar la optimización exacta.
                  </v-alert>
                  <v-btn color="deep-purple" prepend-icon="mdi-printer" @click="imprimirCortes">
                    Imprimir / PDF
                  </v-btn>
                </div>
                <HojaCortesView :data="hojaCortes.data" />
              </template>
            </v-window-item>
          </v-window>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Dialog crear orden de compra -->
    <v-dialog v-model="dialogOrden.show" max-width="480" persistent>
      <v-card>
        <v-card-title class="text-body-1 font-weight-bold pa-4">
          <v-icon color="deep-purple" class="mr-1">mdi-cart-arrow-down</v-icon>
          Orden de compra ({{ nSeleccionados }} ítems)
        </v-card-title>
        <v-card-text>
          <template v-if="!dialogOrden.ordenId">
            <v-select
              v-model="dialogOrden.proveedorId"
              :items="proveedores"
              item-title="nombre"
              item-value="id"
              label="Proveedor"
              variant="outlined"
              density="compact"
              clearable
              class="mb-3"
            />
            <v-textarea
              v-model="dialogOrden.observaciones"
              label="Observaciones (opcional)"
              variant="outlined"
              density="compact"
              rows="2"
              hide-details
            />
          </template>
          <v-alert v-else type="success" variant="tonal" density="compact">
            Orden <strong>{{ dialogOrden.numero }}</strong> creada. Descárgala:
          </v-alert>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <template v-if="!dialogOrden.ordenId">
            <v-btn variant="text" @click="dialogOrden.show = false">Cancelar</v-btn>
            <v-btn color="deep-purple" variant="flat" :loading="dialogOrden.creando" @click="crearOrden">
              Crear orden
            </v-btn>
          </template>
          <template v-else>
            <v-btn variant="tonal" color="red" prepend-icon="mdi-file-pdf-box" @click="descargarOrden('pdf')">PDF</v-btn>
            <v-btn variant="tonal" color="green" prepend-icon="mdi-file-excel" @click="descargarOrden('excel')">Excel</v-btn>
            <v-btn variant="text" @click="cerrarDialogOrden">Cerrar</v-btn>
          </template>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">
      {{ snack.msg }}
    </v-snackbar>

  </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/axiosInstance'
import HojaCortesView from '@/components/HojaCortesView.vue'
const api = axios
const router = useRouter()

const cargando  = ref(false)
const guardando = ref(false)
const cotizaciones = ref([])
const empleados    = ref([])
const stats        = ref({})
const busqueda     = ref('')
const filtroEstado = ref('')

const snack = ref({ show: false, color: 'success', msg: '' })

const dialogEtapa = ref({
  show: false, cotizacion: null, etapa: null,
  etapaLabel: '', cliente: '',
  nuevoEstado: '', empleadoId: null, notas: '',
})

// ── Materiales Winperfil ──────────────────────────────────────────
const dialogMat = ref({
  show: false, loading: false, error: '',
  cliente: '', numero: null, serie: '', tab: 'compra', data: null,
})

const gruposCompra = computed(() => {
  const c = dialogMat.value.data?.compra
  if (!c) return []
  return [
    { key: 'perfiles', label: 'Perfiles',  icon: 'mdi-view-day',        color: 'blue',       items: c.perfiles || [] },
    { key: 'vidrios',  label: 'Vidrios',   icon: 'mdi-window-maximize', color: 'cyan',       items: c.vidrios || [] },
    { key: 'herrajes', label: 'Herrajes',  icon: 'mdi-cog',             color: 'orange',     items: c.herrajes || [] },
    { key: 'juntas',   label: 'Juntas/Felpas/Gomas', icon: 'mdi-rubber-band', color: 'green', items: c.juntas || [] },
    { key: 'otros',    label: 'Otros',     icon: 'mdi-dots-horizontal', color: 'grey',       items: c.otros || [] },
  ]
})

const seleccion = ref({})          // { 'perfiles:0': true, ... }
const cotizacionMatId = ref(null)  // cotización origen para la orden

// Hoja de cortes (lazy: se carga al abrir la tab)
const hojaCortes = ref({ loading: false, error: '', data: null })

async function abrirMateriales(cotizacion) {
  seleccion.value = {}
  cotizacionMatId.value = cotizacion.id
  hojaCortes.value = { loading: false, error: '', data: null }
  dialogMat.value = {
    show: true, loading: true, error: '',
    cliente: cotizacion.cliente, numero: cotizacion.winperfil_numero,
    serie: cotizacion.winperfil_serie, tab: 'compra', data: null,
  }
  try {
    const { data } = await api.get('/api/winperfil/materiales', {
      params: { cotizacion_id: cotizacion.id },
    })
    dialogMat.value.data = data
  } catch (e) {
    dialogMat.value.error = e.response?.data?.error || 'No se pudieron cargar los materiales'
  } finally {
    dialogMat.value.loading = false
  }
}

// Cargar la hoja de cortes solo cuando se entra a esa tab (es una llamada extra a Winperfil)
watch(() => dialogMat.value.tab, async (tab) => {
  if (tab !== 'cortes' || hojaCortes.value.data || hojaCortes.value.loading) return
  hojaCortes.value.loading = true
  hojaCortes.value.error = ''
  try {
    const { data } = await api.get('/api/winperfil/hoja-cortes', {
      params: { cotizacion_id: cotizacionMatId.value },
    })
    hojaCortes.value.data = data
  } catch (e) {
    hojaCortes.value.error = e.response?.data?.error || 'No se pudo generar la hoja de cortes'
  } finally {
    hojaCortes.value.loading = false
  }
})

// ── Selección de materiales ───────────────────────────────────────
const isSelected = (grupoKey, i) => !!seleccion.value[`${grupoKey}:${i}`]

function toggleItem(grupoKey, i) {
  const k = `${grupoKey}:${i}`
  seleccion.value = { ...seleccion.value, [k]: !seleccion.value[k] }
}

function grupoTodoSeleccionado(grupo) {
  return grupo.items.length > 0 && grupo.items.every((_, i) => isSelected(grupo.key, i))
}
function grupoParcial(grupo) {
  const n = grupo.items.filter((_, i) => isSelected(grupo.key, i)).length
  return n > 0 && n < grupo.items.length
}
function toggleGrupo(grupo, val) {
  const copia = { ...seleccion.value }
  grupo.items.forEach((_, i) => { copia[`${grupo.key}:${i}`] = val })
  seleccion.value = copia
}

const nSeleccionados = computed(() =>
  Object.values(seleccion.value).filter(Boolean).length,
)

// Construye los items normalizados para la orden
const itemsSeleccionados = computed(() => {
  const out = []
  for (const grupo of gruposCompra.value) {
    grupo.items.forEach((it, i) => {
      if (!isSelected(grupo.key, i)) return
      out.push({
        categoria:   grupo.label,
        referencia:  it.referencia || '',
        descripcion: it.descripcion || '',
        detalle:     grupo.key === 'vidrios' ? `${it.ancho}×${it.alto} mm`
                    : grupo.key === 'perfiles' ? `${it.metros_lineales} m`
                    : '',
        cantidad:    grupo.key === 'perfiles' ? it.piezas : it.cantidad,
      })
    })
  }
  return out
})

// ── Orden de compra ───────────────────────────────────────────────
const proveedores = ref([])
const dialogOrden = ref({ show: false, proveedorId: null, observaciones: '', creando: false, ordenId: null, numero: '' })

async function abrirDialogOrden() {
  dialogOrden.value = { show: true, proveedorId: null, observaciones: '', creando: false, ordenId: null, numero: '' }
  if (!proveedores.value.length) {
    try {
      const { data } = await api.get('/api/proveedores')
      proveedores.value = Array.isArray(data) ? data : (data?.data ?? [])
    } catch { /* sin proveedores, se puede crear sin uno */ }
  }
}

async function crearOrden() {
  dialogOrden.value.creando = true
  try {
    const { data } = await api.post('/api/ordenes-compra', {
      cotizacion_id: cotizacionMatId.value,
      proveedor_id:  dialogOrden.value.proveedorId,
      observaciones: dialogOrden.value.observaciones || null,
      items:         itemsSeleccionados.value,
    })
    dialogOrden.value.ordenId = data.id
    dialogOrden.value.numero  = data.numero
    mostrarSnack(`Orden ${data.numero} creada`)
  } catch (e) {
    mostrarSnack(e.response?.data?.message || 'Error al crear la orden', 'error')
  } finally {
    dialogOrden.value.creando = false
  }
}

async function descargarOrden(formato) {
  try {
    const res = await api.get(`/api/ordenes-compra/${dialogOrden.value.ordenId}/${formato}`, { responseType: 'blob' })
    const ext = formato === 'pdf' ? 'pdf' : 'csv'
    const mime = formato === 'pdf' ? 'application/pdf' : 'text/csv'
    const url = window.URL.createObjectURL(new Blob([res.data], { type: mime }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${dialogOrden.value.numero}.${ext}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
  } catch {
    mostrarSnack('Error al descargar', 'error')
  }
}

function cerrarDialogOrden() {
  dialogOrden.value.show = false
  seleccion.value = {}
}

function imprimirCortes() {
  const href = router.resolve({
    name: 'produccion-hoja-winperfil',
    query: {
      cotizacion_id: cotizacionMatId.value,
      serie: dialogMat.value.serie,
      numero: dialogMat.value.numero,
    },
  }).href
  window.open(href, '_blank')
}

// ── Computed ──────────────────────────────────────────────────────
const vencidas = computed(() =>
  cotizaciones.value.filter(c => c.urgencia === 'vencida').length
)

const cotizacionesFiltradas = computed(() => {
  return cotizaciones.value.filter(c => {
    if (filtroEstado.value && c.estado_produccion !== filtroEstado.value) return false
    if (busqueda.value && !c.cliente.toLowerCase().includes(busqueda.value.toLowerCase())) return false
    return true
  })
})

// ── Cargar datos ──────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/taller')
    cotizaciones.value = data.cotizaciones
    empleados.value    = data.empleados
    stats.value        = data.stats
  } catch {
    mostrarSnack('Error al cargar taller', 'error')
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Etapas ────────────────────────────────────────────────────────
function cambiarEtapa(cotizacion, etapa, nuevoEstado) {
  // Si va a iniciar/completar, mostrar dialog para asignar empleado
  if (nuevoEstado !== 'pendiente') {
    dialogEtapa.value = {
      show: true,
      cotizacion,
      etapa,
      etapaLabel: etapa.label,
      cliente: cotizacion.cliente,
      nuevoEstado,
      empleadoId: etapa.empleado_id ?? null,
      notas: etapa.notas ?? '',
    }
  } else {
    // Reabrir: directo sin dialog
    guardarEtapa(cotizacion, etapa, 'pendiente', null, null)
  }
}

async function confirmarEtapa() {
  const { cotizacion, etapa, nuevoEstado, empleadoId, notas } = dialogEtapa.value
  await guardarEtapa(cotizacion, etapa, nuevoEstado, empleadoId, notas)
  dialogEtapa.value.show = false
}

async function guardarEtapa(cotizacion, etapa, estado, empleadoId, notas) {
  guardando.value = true
  try {
    await api.post(`/api/taller/${cotizacion.id}/etapas`, {
      etapa: etapa.etapa,
      estado,
      empleado_id: empleadoId,
      notas,
    })
    // Actualizar local sin recargar todo
    etapa.estado      = estado
    etapa.empleado_id = empleadoId
    etapa.notas       = notas
    if (estado === 'en_progreso') etapa.fecha_inicio = new Date().toISOString().split('T')[0]
    if (estado === 'completado')  etapa.fecha_fin_real = new Date().toISOString().split('T')[0]
    if (estado === 'pendiente') { etapa.fecha_inicio = null; etapa.fecha_fin_real = null }

    // Recalcular progreso local
    const total     = cotizacion.etapas.length
    const completas = cotizacion.etapas.filter(e => e.estado === 'completado').length
    cotizacion.progreso = total > 0 ? Math.round(completas / total * 100) : 0

    mostrarSnack(`Etapa "${etapa.label}" → ${estado}`)
  } catch {
    mostrarSnack('Error al guardar etapa', 'error')
  } finally {
    guardando.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────
function colorEstado(estado) {
  return { 'En Fabricación': 'orange', 'Lista para Corte': 'blue', 'Fabricadas OK': 'green' }[estado] ?? 'grey'
}

function colorEtapa(estado) {
  return { pendiente: 'grey', en_progreso: 'orange', completado: 'success' }[estado] ?? 'grey'
}

function iconoEtapa(etapa) {
  const map = {
    corte_perfiles:         'mdi-scissors-cutting',
    corte_vidrio:           'mdi-image-filter-frames',
    fabricacion_termopanel: 'mdi-layers',
    armado:                 'mdi-hammer-wrench',
    vidriado:               'mdi-window-open',
    junquillos:             'mdi-minus',
    control:                'mdi-magnify-scan',
    instalacion:            'mdi-home-wrench',
    entrega:                'mdi-truck-delivery',
  }
  return map[etapa] ?? 'mdi-circle-small'
}

function claseEntrega(c) {
  if (c.urgencia === 'vencida') return 'text-error font-weight-bold'
  if (c.urgencia === 'critica') return 'text-error'
  if (c.urgencia === 'proxima') return 'text-warning'
  return 'text-grey'
}

function fmtFecha(f) {
  if (!f) return ''
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

function primerNombre(nombre) {
  return nombre?.split(' ')[0] ?? ''
}

function mostrarSnack(msg, color = 'success') {
  snack.value = { show: true, color, msg }
}
</script>

<style scoped>
.cotizacion-card {
  border-left: 4px solid transparent;
  transition: box-shadow 0.15s;
}
.cotizacion-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.2) !important; }
.urgencia-vencida  { border-left-color: rgb(244, 67, 54)  !important; }
.urgencia-critica  { border-left-color: rgb(244, 67, 54)  !important; }
.urgencia-proxima  { border-left-color: rgb(255, 152, 0)  !important; }
.urgencia-normal   { border-left-color: rgb(var(--v-theme-primary)) !important; }

/* Etapas */
.etapas-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.etapa-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 8px 6px;
  border-radius: 8px;
  min-width: 76px;
  max-width: 90px;
  border: 1px solid rgba(255,255,255,0.1);
  flex: 1;
  transition: background 0.15s;
}

.etapa--pendiente  { background: rgba(255,255,255,0.03); }
.etapa--en_progreso { background: rgba(255, 152, 0, 0.12); border-color: rgba(255,152,0,0.4); }
.etapa--completado  { background: rgba(76, 175, 80, 0.1);  border-color: rgba(76,175,80,0.4); }

.etapa-icon-wrap {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.08);
}

.etapa-label {
  font-size: 10px;
  font-weight: 600;
  text-align: center;
  line-height: 1.2;
  color: rgba(255,255,255,0.85);
}

.etapa-empleado {
  font-size: 9px;
  color: rgba(255,255,255,0.5);
  text-align: center;
}

.etapa-actions {
  width: 100%;
  margin-top: 2px;
}
</style>
