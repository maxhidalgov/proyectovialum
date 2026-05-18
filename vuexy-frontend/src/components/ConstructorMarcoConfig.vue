<template>
  <v-card variant="outlined" class="pa-3">
    <div class="text-subtitle-1 font-weight-bold mb-3">Constructor de Marco</div>

    <!-- Canvas + Panel lateral -->
    <v-row dense>
      <!-- Canvas -->
      <v-col cols="12" md="7">
        <div class="text-caption text-medium-emphasis text-center mb-1">
          Click para seleccionar · Ctrl+Click para selección múltiple
        </div>

        <!-- Quick-select buttons -->
        <div class="d-flex flex-wrap ga-1 justify-center mb-2">
          <v-btn size="x-small" variant="tonal" @click="selAll('perimetro')">Todo perímetro</v-btn>
          <v-btn size="x-small" variant="tonal" :disabled="!hasDivs" @click="selAll('div')">Todas divisiones</v-btn>
          <v-btn size="x-small" variant="tonal" @click="selAll('todo')">Todo marco</v-btn>
          <v-btn size="x-small" variant="outlined" color="error" :disabled="!selectedEls.length" @click="selectedEls = []">Limpiar</v-btn>
        </div>

        <VistaConstructorMarco
          ref="canvasRef"
          :ancho="ventana.ancho || 1000"
          :alto="ventana.alto || 1000"
          :color-marco="colorNombre"
          :marco="ventana.marcoConstructor"
          :selected-els="selectedEls"
          :tipos-ventana-nombres="tiposVentanaNombres"
          @click-perimetro="onClickPerimetro"
          @click-div="onClickDiv"
          @click-espacio="onClickEspacio"
        />
      </v-col>

      <!-- Panel de propiedades -->
      <v-col cols="12" md="5">
        <!-- Vacío -->
        <div
          v-if="panelType === 'empty'"
          class="d-flex flex-column align-center justify-center text-medium-emphasis"
          style="min-height: 220px"
        >
          <v-icon size="48" color="grey-lighten-1">mdi-cursor-pointer</v-icon>
          <div class="text-caption mt-2 text-center">
            Selecciona una línea de perímetro,<br>una división o un espacio
          </div>
        </div>

        <!-- Líneas (una o varias) -->
        <template v-else-if="panelType === 'lines'">
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ selectedEls.length === 1 ? selectedElLabel : `${selectedEls.length} líneas seleccionadas` }}
          </div>

          <!-- Editar posición + eliminar (solo para una división seleccionada) -->
          <template v-if="selectedEls.length === 1 && selectedEls[0].tipo === 'div'">
            <div class="text-caption text-medium-emphasis mb-1">Posición (mm desde inicio del espacio):</div>
            <div class="d-flex ga-2 align-center mb-3">
              <v-text-field
                :model-value="currentDivPosition"
                type="number" :min="1"
                hide-details variant="outlined" density="compact" color="primary"
                class="flex-grow-1"
                @update:model-value="setDivPosition"
              />
              <v-btn
                size="small" variant="outlined" color="error"
                icon="mdi-delete-outline"
                title="Eliminar división"
                @click="removeDiv(selectedEls[0])"
              />
            </div>
          </template>

          <div class="text-caption text-medium-emphasis mb-2">Asignar perfil:</div>
          <v-autocomplete
            :model-value="currentLinePcpId"
            :items="perfilesConDisplay"
            item-title="display"
            item-value="id"
            label="Buscar perfil..."
            clearable variant="outlined" density="compact" color="primary"
            :loading="loadingPerfiles"
            no-data-text="Sin perfiles disponibles"
            @update:model-value="setLinePcp"
          />
          <v-card v-if="selectedPerfilInfo" variant="tonal" color="primary" class="pa-2 mt-1">
            <div class="text-caption font-weight-bold">{{ selectedPerfilInfo.producto_nombre }}</div>
            <div class="text-caption">Color: {{ selectedPerfilInfo.color_nombre }}</div>
            <div class="text-caption">Proveedor: {{ selectedPerfilInfo.proveedor_nombre }}</div>
            <div class="text-caption font-weight-bold text-success">${{ selectedPerfilInfo.costo?.toLocaleString('es-CL') }} / m</div>
          </v-card>
          <div v-else-if="selectedEls.length > 1" class="text-caption text-medium-emphasis mt-1">
            El perfil se asignará a las {{ selectedEls.length }} líneas seleccionadas.
          </div>
          <div v-else class="text-caption text-medium-emphasis mt-1">Sin perfil asignado</div>
        </template>

        <!-- Espacio (leaf) -->
        <template v-else-if="panelType === 'space'">
          <div class="text-subtitle-2 font-weight-bold mb-2">
            Espacio {{ spacePathLabel }}
          </div>

          <!-- Contenido -->
          <v-radio-group
            :model-value="currentSpaceObj?.contenido ?? 'vidrio'"
            inline density="compact"
            @update:model-value="setSpaceContenido"
          >
            <v-radio label="Vidrio" value="vidrio" color="primary" />
            <v-radio label="Tipo de ventana" value="ventana" color="primary" />
          </v-radio-group>

          <template v-if="currentSpaceObj?.contenido === 'ventana'">
            <v-select
              :model-value="currentSpaceObj.tipo_ventana_id"
              :items="tiposVentanaFiltrados"
              item-title="nombre" item-value="id"
              label="Tipo de ventana"
              variant="outlined" density="compact" color="primary" class="mt-2"
              @update:model-value="setSpaceTipoVentana"
            />

            <!-- Opciones específicas para Puerta Templada (tipo 61) -->
            <template v-if="currentSpaceObj.tipo_ventana_id === 61">
              <div class="text-caption text-medium-emphasis mt-3 mb-1">Vidrio templado:</div>
              <v-select
                :model-value="currentSpaceObj.producto_vidrio_proveedor_id"
                :items="productosVidrioTemplado"
                item-title="nombre" item-value="id"
                label="Espesor de vidrio"
                variant="outlined" density="compact" color="primary"
                clearable
                @update:model-value="setSpaceProductoVidrio"
              />
              <div class="text-caption text-medium-emphasis mt-2 mb-1">Tirador:</div>
              <v-select
                :model-value="currentSpaceObj.tirador_id"
                :items="tiradoresTemplado"
                item-title="label" item-value="id"
                label="Tirador Tipo H"
                variant="outlined" density="compact" color="primary"
                clearable
                @update:model-value="setSpaceTirador"
              />
            </template>
          </template>
          <template v-else>
            <div class="text-caption text-medium-emphasis mt-1 mb-2">
              Se usará el vidrio de la ventana principal.
            </div>
            <div class="text-caption text-medium-emphasis mb-1">Junquillo (opcional):</div>
            <v-autocomplete
              :model-value="currentSpaceObj?.junquillo_pcp_id ?? null"
              :items="perfilesConDisplay"
              item-title="display"
              item-value="id"
              label="Seleccionar junquillo..."
              clearable variant="outlined" density="compact" color="primary"
              :loading="loadingPerfiles"
              no-data-text="Sin perfiles disponibles"
              @update:model-value="setSpaceJunquillo"
            />
            <v-card v-if="currentSpaceJunquilloInfo" variant="tonal" color="secondary" class="pa-2">
              <div class="text-caption font-weight-bold">{{ currentSpaceJunquilloInfo.producto_nombre }}</div>
              <div class="text-caption">Color: {{ currentSpaceJunquilloInfo.color_nombre }}</div>
              <div class="text-caption font-weight-bold text-success">${{ currentSpaceJunquilloInfo.costo?.toLocaleString('es-CL') }} / m</div>
            </v-card>
          </template>

          <!-- Agregar subdivisión -->
          <v-divider class="my-3" />
          <div class="text-caption font-weight-bold mb-1">Agregar subdivisión al espacio:</div>
          <v-text-field
            v-model.number="nuevaSubDiv"
            type="number" label="Posición (mm desde inicio)"
            :min="1"
            hide-details variant="outlined" density="compact" color="primary" class="mb-2"
          />
          <div class="d-flex ga-2">
            <v-btn size="small" variant="tonal" color="primary" class="flex-grow-1" @click="addSubDiv('h')">+ Horizontal</v-btn>
            <v-btn size="small" variant="tonal" color="primary" class="flex-grow-1" @click="addSubDiv('v')">+ Vertical</v-btn>
          </div>
          <v-btn
            v-if="selectedEls[0]?.path?.length > 0"
            size="x-small" variant="text" color="error" class="mt-2"
            @click="resetLeaf(selectedEls[0].path)"
          >Resetear espacio a vidrio</v-btn>
        </template>
      </v-col>
    </v-row>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/axiosInstance'
import VistaConstructorMarco from '@/components/VistaConstructorMarco.vue'

const props = defineProps({
  ventana: { type: Object, required: true },
  tiposVentana: { type: Array, default: () => [] },
  colores: { type: Array, default: () => [] },
  productosVidrio: { type: Array, default: () => [] },
})

const tiradoresTemplado = [
  { id: 266, label: 'Tirador 450mm' },
  { id: 267, label: 'Tirador 600mm' },
  { id: 268, label: 'Tirador 800mm' },
  { id: 269, label: 'Tirador 1000mm' },
  { id: 270, label: 'Tirador 1200mm' },
  { id: 271, label: 'Tirador 1800mm' },
]

const productosVidrioTemplado = computed(() =>
  props.productosVidrio
    .filter(p => p.tipo_producto_id === 7)
    .flatMap(p => (p.colores_por_proveedor ?? []).map(cpp => ({
      id: cpp.id,
      nombre: `${p.nombre} (${cpp.proveedor?.nombre ?? 'Proveedor'})`,
    })))
)

const canvasRef = ref(null)

// ─── Perfiles ─────────────────────────────────────────────────────────────
const perfiles = ref([])
const loadingPerfiles = ref(false)

async function cargarPerfiles() {
  loadingPerfiles.value = true
  try { const { data } = await api.get('/api/perfiles-constructor'); perfiles.value = data }
  catch (e) { console.error('Error cargando perfiles:', e) }
  finally { loadingPerfiles.value = false }
}

const perfilesConDisplay = computed(() =>
  perfiles.value.map(p => ({ ...p, display: `${p.producto_nombre} | ${p.color_nombre} | ${p.proveedor_nombre} — $${p.costo?.toLocaleString('es-CL')}/m` }))
)

// ─── Init ──────────────────────────────────────────────────────────────────
function migrateOldToTree(div_h, div_v, espacios) {
  const makeLeaf = (ri, ci) => {
    const esp = espacios?.[ri]?.[ci] ?? {}
    return { tipo: 'leaf', contenido: esp.contenido ?? 'vidrio', tipo_ventana_id: esp.tipo_ventana_id ?? null }
  }
  const vPos = [...(div_v ?? [])].sort((a,b) => a.posicion - b.posicion)
  const hPos = [...(div_h ?? [])].sort((a,b) => a.posicion - b.posicion)

  if (!vPos.length && !hPos.length) return makeLeaf(0, 0)

  const buildCol = (ci) => {
    if (!hPos.length) return makeLeaf(0, ci)
    return {
      tipo: 'split', direction: 'h',
      positions: hPos.map(p => ({ posicion: p.posicion, pcp_id: p.pcp_id ?? null })),
      children: Array.from({ length: hPos.length + 1 }, (_, ri) => makeLeaf(ri, ci)),
    }
  }
  if (!vPos.length) return buildCol(0)
  return {
    tipo: 'split', direction: 'v',
    positions: vPos.map(p => ({ posicion: p.posicion, pcp_id: p.pcp_id ?? null })),
    children: Array.from({ length: vPos.length + 1 }, (_, ci) => buildCol(ci)),
  }
}

function init() {
  if (!props.ventana.perimetroConstructor) {
    props.ventana.perimetroConstructor = {
      top: { pcp_id: null }, right: { pcp_id: null },
      bottom: { pcp_id: null }, left: { pcp_id: null },
    }
  }
  if (!props.ventana.marcoConstructor) {
    const { divisiones_h, divisiones_v, espaciosConstructor } = props.ventana
    if (divisiones_h?.length || divisiones_v?.length || espaciosConstructor?.length) {
      props.ventana.marcoConstructor = migrateOldToTree(divisiones_h, divisiones_v, espaciosConstructor)
    } else {
      props.ventana.marcoConstructor = { tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null }
    }
  }
}

// ─── Tree helpers ──────────────────────────────────────────────────────────
function getNode(path) {
  let node = props.ventana.marcoConstructor
  for (const i of (path ?? [])) {
    if (!node?.children) return null
    node = node.children[i]
  }
  return node
}

function setNodeAt(path, newNode) {
  if (!path?.length) { props.ventana.marcoConstructor = newNode; return }
  const parent = getNode(path.slice(0, -1))
  if (parent?.children) parent.children[path[path.length - 1]] = newNode
}

function getSectionsMM(posMM, totalMM) {
  const sorted = [...posMM].sort((a,b) => a-b)
  const secs = []; let prev = 0
  for (const p of sorted) { if (p > prev && p < totalMM) { secs.push(p-prev); prev = p } }
  secs.push(totalMM - prev)
  return secs.length ? secs : [totalMM]
}

function getLeafDimensions(path) {
  let mmW = props.ventana.ancho || 1000
  let mmH = props.ventana.alto  || 1000
  let node = props.ventana.marcoConstructor
  for (const childIdx of (path ?? [])) {
    if (!node || node.tipo !== 'split') break
    const posMM = node.positions.map(p => p.posicion)
    const totalMM = node.direction === 'v' ? mmW : mmH
    const secs = getSectionsMM(posMM, totalMM)
    if (node.direction === 'v') mmW = secs[childIdx] ?? mmW
    else mmH = secs[childIdx] ?? mmH
    node = node.children?.[childIdx]
  }
  return { mmW, mmH }
}

function getAllDivs(node, path = []) {
  if (!node || node.tipo !== 'split') return []
  const result = (node.positions ?? []).map((_, i) => ({ tipo: 'div', splitPath: [...path], idx: i }))
  for (let i = 0; i < (node.children?.length ?? 0); i++) {
    result.push(...getAllDivs(node.children[i], [...path, i]))
  }
  return result
}

const hasDivs = computed(() => getAllDivs(props.ventana.marcoConstructor).length > 0)

// ─── Division edit / remove ───────────────────────────────────────────────
const currentDivPosition = computed(() => {
  const el = selectedEls.value[0]
  if (el?.tipo !== 'div') return null
  return getNode(el.splitPath)?.positions?.[el.idx]?.posicion ?? null
})

function setDivPosition(val) {
  const el = selectedEls.value[0]
  if (el?.tipo !== 'div') return
  const pos = Number(val)
  if (!pos || pos <= 0) return
  const node = getNode(el.splitPath)
  if (node?.positions?.[el.idx]) node.positions[el.idx].posicion = pos
}

function removeDiv(el) {
  if (el?.tipo !== 'div') return
  const node = getNode(el.splitPath)
  if (!node?.positions) return
  if (node.positions.length === 1) {
    // Collapse the split — keep the first child
    setNodeAt(el.splitPath, node.children?.[0] ?? { tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null })
  } else {
    // Remove the bar and its right/below child
    node.positions.splice(el.idx, 1)
    node.children.splice(el.idx + 1, 1)
  }
  selectedEls.value = []
}

// ─── Sub-division add/reset ────────────────────────────────────────────────
const nuevaSubDiv = ref(null)

function addSubDiv(direction) {
  const path = selectedEls.value[0]?.path
  const pos = Number(nuevaSubDiv.value)
  if (!pos || pos <= 0) return
  const leaf = getNode(path)
  if (!leaf || leaf.tipo !== 'leaf') return
  const dims = getLeafDimensions(path)
  const max = direction === 'h' ? dims.mmH : dims.mmW
  if (pos >= max) return
  setNodeAt(path, {
    tipo: 'split', direction,
    positions: [{ posicion: pos, pcp_id: null }],
    children: [
      { tipo: 'leaf', contenido: leaf.contenido, tipo_ventana_id: leaf.tipo_ventana_id ?? null },
      { tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null },
    ],
  })
  nuevaSubDiv.value = null
  selectedEls.value = []
}

function resetLeaf(path) {
  setNodeAt(path, { tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null })
  selectedEls.value = []
}

// ─── Selection state ───────────────────────────────────────────────────────
const selectedEls = ref([])

function elKey(el) {
  if (!el) return ''
  if (el.tipo === 'div')    return `div-${(el.splitPath ?? []).join(',')}-${el.idx}`
  if (el.tipo === 'espacio') return `espacio-${(el.path ?? []).join(',')}`
  return el.tipo
}
function elIsLine(el) { return el.tipo !== 'espacio' }

function toggleOrSet(newEl, isMulti) {
  if (!isMulti) { selectedEls.value = [newEl]; return }
  const key = elKey(newEl)
  const idx = selectedEls.value.findIndex(e => elKey(e) === key)
  if (idx >= 0) { selectedEls.value.splice(idx, 1) }
  else if (elIsLine(newEl) && selectedEls.value.some(e => !elIsLine(e))) { selectedEls.value = [newEl] }
  else { selectedEls.value.push(newEl) }
}

const panelType = computed(() => {
  if (!selectedEls.value.length) return 'empty'
  if (selectedEls.value.length === 1 && selectedEls.value[0].tipo === 'espacio') return 'space'
  if (selectedEls.value.every(elIsLine)) return 'lines'
  return 'empty'
})

const labelMap = { top: 'Perímetro Superior', right: 'Perímetro Derecho', bottom: 'Perímetro Inferior', left: 'Perímetro Izquierdo' }

const selectedElLabel = computed(() => {
  const el = selectedEls.value[0]
  if (!el) return ''
  if (labelMap[el.tipo]) return labelMap[el.tipo]
  if (el.tipo === 'div') {
    const node = getNode(el.splitPath)
    const pos = node?.positions?.[el.idx]?.posicion
    const dir = node?.direction === 'v' ? 'Vertical' : 'Horizontal'
    return `División ${dir} (${pos ?? '?'}mm)`
  }
  return ''
})

const spacePathLabel = computed(() => {
  const path = selectedEls.value[0]?.path
  if (!path?.length) return 'Principal'
  return `[${path.map((v, i) => (i === 0 ? (v === 0 ? 'izq/arr' : 'der/ab') : v)).join('→')}]`
})

// Line data object for a selection element
function getLineObj(el) {
  if (!el) return null
  if (labelMap[el.tipo]) return props.ventana.perimetroConstructor?.[el.tipo]
  if (el.tipo === 'div') return getNode(el.splitPath)?.positions?.[el.idx] ?? null
  return null
}

const currentLinePcpId = computed(() => {
  const lines = selectedEls.value.filter(elIsLine)
  if (!lines.length) return null
  const ids = lines.map(el => getLineObj(el)?.pcp_id ?? null)
  const first = ids[0]
  return ids.every(id => id === first) ? first : null
})

function setLinePcp(pcpId) {
  for (const el of selectedEls.value.filter(elIsLine)) {
    const obj = getLineObj(el)
    if (obj) obj.pcp_id = pcpId ?? null
  }
}

const selectedPerfilInfo = computed(() =>
  currentLinePcpId.value != null ? perfiles.value.find(p => p.id === currentLinePcpId.value) ?? null : null
)

// Space object
const currentSpaceObj = computed(() => {
  if (panelType.value !== 'space') return null
  return getNode(selectedEls.value[0]?.path) ?? null
})

function setSpaceContenido(val) {
  const obj = currentSpaceObj.value
  if (!obj) return
  obj.contenido = val
  if (val === 'vidrio') {
    obj.tipo_ventana_id = null
    delete obj.tirador_id
    delete obj.producto_vidrio_proveedor_id
  } else {
    delete obj.junquillo_pcp_id
  }
}
function setSpaceTipoVentana(val) {
  const obj = currentSpaceObj.value
  if (!obj) return
  obj.tipo_ventana_id = val
  if (val !== 61) {
    delete obj.tirador_id
    delete obj.producto_vidrio_proveedor_id
  } else {
    if (obj.tirador_id === undefined) obj.tirador_id = null
    if (obj.producto_vidrio_proveedor_id === undefined) obj.producto_vidrio_proveedor_id = null
  }
}
function setSpaceTirador(val) {
  const obj = currentSpaceObj.value
  if (obj) obj.tirador_id = val ?? null
}
function setSpaceProductoVidrio(val) {
  const obj = currentSpaceObj.value
  if (obj) obj.producto_vidrio_proveedor_id = val ?? null
}
function setSpaceJunquillo(val) {
  const obj = currentSpaceObj.value
  if (obj) obj.junquillo_pcp_id = val ?? null
}

const currentSpaceJunquilloInfo = computed(() => {
  const id = currentSpaceObj.value?.junquillo_pcp_id
  if (!id) return null
  return perfiles.value.find(p => p.id === id) ?? null
})

// Canvas event handlers
const onClickPerimetro = (lado, isMulti) => toggleOrSet({ tipo: lado }, isMulti)
const onClickDiv = (splitPath, idx, isMulti) => toggleOrSet({ tipo: 'div', splitPath, idx }, isMulti)
const onClickEspacio = (path, isMulti) => {
  // Spaces: single select only
  selectedEls.value = [{ tipo: 'espacio', path }]
}

// Select-all shortcuts
function selAll(group) {
  const perimetro = [{ tipo:'top' }, { tipo:'right' }, { tipo:'bottom' }, { tipo:'left' }]
  const divsAll = getAllDivs(props.ventana.marcoConstructor)
  if (group === 'perimetro') selectedEls.value = perimetro
  else if (group === 'div')  selectedEls.value = divsAll
  else if (group === 'todo') selectedEls.value = [...perimetro, ...divsAll]
}

// ─── Utilities ─────────────────────────────────────────────────────────────
const colorNombre = computed(() => {
  const c = props.ventana.color
  return props.colores.find(col => col.id === c)?.nombre?.toLowerCase() || 'blanco'
})

const tiposVentanaFiltrados = computed(() => props.tiposVentana.filter(t => ![59, 60].includes(t.id)))

const tiposVentanaNombres = computed(() => {
  const map = {}
  for (const t of props.tiposVentana) map[t.id] = t.nombre
  return map
})

defineExpose({
  exportarImagen: () => canvasRef.value?.exportarImagen?.() ?? null,
})

onMounted(() => { init(); cargarPerfiles() })
watch(() => props.ventana.tipo, init)
</script>
