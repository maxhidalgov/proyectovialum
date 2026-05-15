<template>
  <v-stage ref="stageRef" :config="{ width: 400, height: 400 }" :key="renderKey">
    <v-layer>
      <!-- Marco perimetral -->
      <v-line v-bind="marcoTop"    @click="(e) => $emit('click-perimetro', 'top',    e.evt?.ctrlKey || e.evt?.metaKey)" />
      <v-line v-bind="marcoRight"  @click="(e) => $emit('click-perimetro', 'right',  e.evt?.ctrlKey || e.evt?.metaKey)" />
      <v-line v-bind="marcoBottom" @click="(e) => $emit('click-perimetro', 'bottom', e.evt?.ctrlKey || e.evt?.metaKey)" />
      <v-line v-bind="marcoLeft"   @click="(e) => $emit('click-perimetro', 'left',   e.evt?.ctrlKey || e.evt?.metaKey)" />

      <!-- Spaces (leaves) -->
      <template v-for="leaf in flatLeaves" :key="leaf.key">
        <!-- Background — click target -->
        <v-rect v-bind="leaf.fondo" @click="(e) => $emit('click-espacio', leaf.path, e.evt?.ctrlKey || e.evt?.metaKey)" />

        <!-- Mini render (clipped, non-interactive) -->
        <template v-if="leaf.isVentana">
          <v-group v-if="leaf.cat === 'fija'" v-bind="leaf.clipGroup">
            <VistaVentanaFijaS60mini
              :ancho="leaf.mmW" :alto="leaf.mmH" :escala="leaf.esc"
              :config="{ x: 0, y: 0 }" :color-marco="colorNombre" :show-height-label="false"
            />
          </v-group>
          <v-group v-else-if="leaf.cat === 'proyectante'" v-bind="leaf.clipGroup">
            <VistaVentanaProyectanteS60mini
              :ancho="leaf.mmW" :alto="leaf.mmH" :escala="leaf.esc"
              :config="{ x: 0, y: 0 }" :color-marco="colorNombre" :show-height-label="false"
            />
          </v-group>
          <v-group v-else-if="leaf.cat === 'corredera'" v-bind="leaf.clipGroup">
            <VistaVentanaCorrederamini
              :ancho="leaf.mmW" :alto="leaf.mmH" :escala="leaf.esc"
              :config="{ x: 0, y: 0 }" :color-marco="colorNombre" :show-height-label="false"
            />
          </v-group>
          <v-group v-else-if="leaf.cat === 'puerta'" v-bind="leaf.clipGroup">
            <VistaPuertaS60mini
              :ancho="leaf.mmW" :alto="leaf.mmH" :escala="leaf.esc"
              :config="{ x: 0, y: 0 }" :color-marco="colorNombre" :show-height-label="false"
            />
          </v-group>
          <!-- Label for compuesta / unknown types -->
          <v-text v-if="leaf.label" v-bind="leaf.label" />
        </template>

        <!-- Selection overlay -->
        <v-rect v-if="leaf.sel" v-bind="leaf.selOverlay" />

        <!-- Dimension legend -->
        <v-text v-bind="leaf.dimLabel" />
      </template>

      <!-- Division bars -->
      <v-rect
        v-for="div in flatDivs" :key="div.key"
        v-bind="div.bar"
        @click="(e) => $emit('click-div', div.splitPath, div.idx, e.evt?.ctrlKey || e.evt?.metaKey)"
      />

      <v-text v-bind="labelAncho" />
      <v-text v-bind="labelAlto" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { computed, ref, watch, reactive, onMounted } from 'vue'
import VistaVentanaFijaS60mini       from './VistaVentanaFijaS60mini.vue'
import VistaVentanaProyectanteS60mini from './VistaVentanaProyectanteS60mini.vue'
import VistaVentanaCorrederamini      from './VistaVentanaCorrederamini.vue'
import VistaPuertaS60mini            from './VistaPuertaS60mini.vue'

const SEL = '#FF6B00'

const TIPO_CAT = {
  1: 'fija', 2: 'fija', 58: 'fija',
  3: 'corredera', 46: 'corredera', 52: 'corredera', 53: 'corredera', 55: 'corredera',
  45: 'proyectante', 49: 'proyectante', 56: 'proyectante',
  50: 'puerta', 51: 'puerta',
  47: 'compuesta', 54: 'compuesta', 57: 'compuesta',
}

const stageRef = ref(null)
const renderKey = ref(0)

const props = defineProps({
  ancho: { type: Number, required: true },
  alto:  { type: Number, required: true },
  colorMarco: { type: String, default: 'blanco' },
  marco: { type: Object, default: () => ({ tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null }) },
  selectedEls: { type: Array, default: () => [] },
  tiposVentanaNombres: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['click-perimetro', 'click-div', 'click-espacio'])

watch(() => [props.ancho, props.alto, props.marco], () => { renderKey.value++ }, { deep: true })

const OFFSET = 40, MARCO_ORIG = 54, BARRA_ORIG = 40

const esc  = computed(() => 320 / Math.max(props.ancho || 1, props.alto || 1))
const SW   = computed(() => (props.ancho || 1) * esc.value)
const SH   = computed(() => (props.alto  || 1) * esc.value)
const MA   = computed(() => MARCO_ORIG * esc.value)
const BA   = computed(() => BARRA_ORIG * esc.value)

const tex = reactive({ roble: null, nogal: null })
onMounted(() => {
  const load = (k, url) => { const img = new Image(); img.src = url; img.onload = () => { tex[k] = img } }
  load('roble', new URL('@/assets/images/roble.png', import.meta.url).href)
  load('nogal', new URL('@/assets/images/nogal.png', import.meta.url).href)
})

const cn = computed(() => {
  const c = props.colorMarco
  return typeof c === 'string' ? c.toLowerCase() : (c?.nombre ?? 'blanco').toLowerCase()
})
const colorNombre = cn

const hexMap = { blanco:'#FFFFFF', mate:'#D8D8D8', negro:'#2C2C2C', gris:'#808080', grafito:'#4A4A4A', bronce:'#CD7F32', champagne:'#F7E7CE', cafe:'#8B4513', café:'#8B4513', nogal:'#8b5a2b', roble:'#c9a36b', titanio:'#998F77', inox:'#C0C0C0', 'negro mate':'#1A1A1A' }
const cHex = computed(() => hexMap[cn.value] || '#FFFFFF')

function ca() {
  const n = cn.value
  if (['roble','nogal'].includes(n) && tex[n]) return { fill: null, fillPatternImage: tex[n], fillPatternRepeat: 'repeat', fillPatternScale: { x: 0.2, y: 0.2 } }
  return { fill: cHex.value, fillPatternImage: null }
}

// Selection helpers using path-based keys
function elKey(el) {
  if (!el) return ''
  if (el.tipo === 'div')    return `div-${(el.splitPath ?? []).join(',')}-${el.idx}`
  if (el.tipo === 'espacio') return `espacio-${(el.path ?? []).join(',')}`
  return el.tipo  // top / right / bottom / left
}
function isSel(el) { const k = elKey(el); return props.selectedEls.some(s => elKey(s) === k) }
function lineStroke(s) { return s ? SEL : 'black' }
function lineSW(s, base = 0.8) { return s ? base * 3 : base }

// Perimeter
const marcoTop    = computed(() => { const s = isSel({ tipo:'top'    }); return { points:[OFFSET,OFFSET,OFFSET+SW.value,OFFSET,OFFSET+SW.value-MA.value,OFFSET+MA.value,OFFSET+MA.value,OFFSET+MA.value], closed:true,...ca(),stroke:lineStroke(s),strokeWidth:lineSW(s) } })
const marcoRight  = computed(() => { const s = isSel({ tipo:'right'  }); return { points:[OFFSET+SW.value,OFFSET,OFFSET+SW.value,OFFSET+SH.value,OFFSET+SW.value-MA.value,OFFSET+SH.value-MA.value,OFFSET+SW.value-MA.value,OFFSET+MA.value], closed:true,...ca(),stroke:lineStroke(s),strokeWidth:lineSW(s) } })
const marcoBottom = computed(() => { const s = isSel({ tipo:'bottom' }); return { points:[OFFSET+SW.value,OFFSET+SH.value,OFFSET,OFFSET+SH.value,OFFSET+MA.value,OFFSET+SH.value-MA.value,OFFSET+SW.value-MA.value,OFFSET+SH.value-MA.value], closed:true,...ca(),stroke:lineStroke(s),strokeWidth:lineSW(s) } })
const marcoLeft   = computed(() => { const s = isSel({ tipo:'left'  }); return { points:[OFFSET,OFFSET+SH.value,OFFSET,OFFSET,OFFSET+MA.value,OFFSET+MA.value,OFFSET+MA.value,OFFSET+SH.value-MA.value], closed:true,...ca(),stroke:lineStroke(s),strokeWidth:lineSW(s) } })

// Tree flattening
function getSections(posMM, totalMM) {
  const sorted = [...posMM].sort((a, b) => a - b)
  const secs = []; let prev = 0
  for (const p of sorted) { if (p > prev && p < totalMM) { secs.push(p - prev); prev = p } }
  secs.push(totalMM - prev)
  return secs.length ? secs : [totalMM]
}

const flatItems = computed(() => {
  const leaves = [], divs = []
  const IX = OFFSET + MA.value, IY = OFFSET + MA.value
  const IW = SW.value - MA.value * 2, IH = SH.value - MA.value * 2

  function flatten(node, x, y, w, h, mmW, mmH, path) {
    if (!node || node.tipo === 'leaf') {
      const esp = node ?? { tipo: 'leaf', contenido: 'vidrio', tipo_ventana_id: null }
      const isVentana = esp.contenido === 'ventana' && esp.tipo_ventana_id
      const cat = isVentana ? (TIPO_CAT[esp.tipo_ventana_id] ?? 'ventana') : 'vidrio'
      const sel = isSel({ tipo: 'espacio', path })
      // Use Min-fit scale then stretch via scaleX/scaleY so the render fills the cell.
      // Konva applies clip in the group's local space (before scaleX/scaleY transform),
      // so clipWidth=naturalW + scaleX=w/naturalW → screen clip = w. ✓
      const esc_cell = Math.min(w / Math.max(mmW, 1), h / Math.max(mmH, 1))
      const naturalW = Math.max(mmW * esc_cell, 1)
      const naturalH = Math.max(mmH * esc_cell, 1)

      const leaf = {
        key: 'leaf-' + (path.length ? path.join('-') : 'root'),
        path, node: esp, cat, isVentana, sel,
        x, y, w, h, mmW, mmH, esc: esc_cell,
        fondo: { x, y, width: w, height: h, fill: isVentana ? '#e8f5e9' : '#b3e5fc', stroke: 'black', strokeWidth: 0.5 },
        clipGroup: { x, y, scaleX: w / naturalW, scaleY: h / naturalH, clipX: 0, clipY: 0, clipWidth: naturalW, clipHeight: naturalH, listening: false },
        selOverlay: { x, y, width: w, height: h, fill: 'transparent', stroke: SEL, strokeWidth: 2.5 },
      }
      if (isVentana && (cat === 'compuesta' || cat === 'ventana')) {
        const nombre = props.tiposVentanaNombres?.[esp.tipo_ventana_id] ?? `Tipo ${esp.tipo_ventana_id}`
        const fs = Math.max(8, Math.min(w * 0.11, h * 0.14, 11))
        leaf.label = { x: x+3, y: y+3, text: nombre, fontSize: fs, fill: '#1b5e20', fontStyle: 'bold', width: w-6, wrap: 'none', ellipsis: true }
      }
      // Dimension legend
      const dimFs = Math.max(7, Math.min(w * 0.1, h * 0.09, 10))
      leaf.dimLabel = {
        x, y: y + h - dimFs - 3,
        width: w, align: 'center',
        text: `${Math.round(mmW)}×${Math.round(mmH)}`,
        fontSize: dimFs, fill: '#444', fontStyle: 'italic',
      }
      leaves.push(leaf)
      return
    }

    if (node.tipo === 'split') {
      const isV = node.direction === 'v'
      const posMM = (node.positions ?? []).map(p => p.posicion)
      const n = posMM.length
      const totalMM = isV ? mmW : mmH
      const totalPx = isV ? w : h
      const sections = getSections(posMM, totalMM)
      const availPx = totalPx - n * BA.value
      const sectionsPx = sections.map(s => s / totalMM * availPx)

      // Division bars
      let off = 0
      for (let i = 0; i < n; i++) {
        off += sectionsPx[i]
        const s = isSel({ tipo: 'div', splitPath: path, idx: i })
        divs.push({
          key: `div-${path.length ? path.join('-') : 'root'}-${i}`,
          splitPath: path, idx: i,
          bar: { x: isV ? x+off : x, y: isV ? y : y+off, width: isV ? BA.value : w, height: isV ? h : BA.value, ...ca(), stroke: lineStroke(s), strokeWidth: lineSW(s) },
        })
        off += BA.value
      }

      // Recurse
      off = 0
      for (let i = 0; i <= n; i++) {
        flatten(
          node.children?.[i] ?? null,
          isV ? x+off : x, isV ? y : y+off,
          isV ? sectionsPx[i] : w, isV ? h : sectionsPx[i],
          isV ? sections[i] : mmW, isV ? mmH : sections[i],
          [...path, i]
        )
        off += sectionsPx[i]
        if (i < n) off += BA.value
      }
    }
  }

  flatten(props.marco ?? { tipo:'leaf', contenido:'vidrio' }, IX, IY, IW, IH, props.ancho || 1, props.alto || 1, [])
  return { leaves, divs }
})

const flatLeaves = computed(() => flatItems.value.leaves)
const flatDivs   = computed(() => flatItems.value.divs)

const labelAncho = computed(() => ({ x: OFFSET+SW.value/2-30, y: OFFSET+SH.value+15, text:`${props.ancho}mm`, fontSize:14, fill:'black' }))
const labelAlto  = computed(() => ({ x: OFFSET-20, y: OFFSET+SH.value/2, text:`${props.alto}mm`, fontSize:14, fill:'black', rotation:-90 }))

defineExpose({
  getStage: () => stageRef.value?.getStage(),
  exportarImagen: () => { try { return stageRef.value?.getStage()?.toDataURL({ pixelRatio:2 }) ?? null } catch { return null } },
})
</script>
