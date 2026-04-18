<template>
  <v-group>
    <!-- Renderiza las ventanas -->
    <template v-for="seg in segmentos" :key="seg.key">
      <!-- ✅ Grupo clickeable para ventana simple -->
      <v-group v-if="!seg.isCompuesta">
        
        <!-- ✅ 1. PRIMERO: Ventana visual (fondo) -->
        <VentanaSimple 
          v-bind="seg.props" 
          :color="seg.props.colorMarco"
          :colorMarco="seg.props.colorMarco"
        />
        
        <!-- ✅ 2. SEGUNDO: Área invisible para capturar doble clicks (encima de la ventana) -->
        <v-rect
          :x="seg.props.x"
          :y="seg.props.y"
          :width="seg.props.ancho * seg.props.escala"
          :height="seg.props.alto * seg.props.escala"
          fill="transparent"
          @click="handleVentanaClick(seg.path)"
          style="cursor:pointer"
        />
        
        <!-- Botones de control (ocultos en modo exportar) -->
        <template v-if="!modoExportar">
          <!-- Botón de editar -->
          <v-circle
            :x="seg.props.x + seg.props.ancho * seg.props.escala - 15"
            :y="seg.props.y + 10"
            :radius="8"
            fill="#2196f3" opacity="0.8"
            @click="(e) => handleEditarClick(e, seg.path)"
            style="cursor:pointer"
          />
          <v-text
            :x="seg.props.x + seg.props.ancho * seg.props.escala - 18"
            :y="seg.props.y + 6"
            text="✏" fontSize="10" fill="white"
            @click="(e) => handleEditarClick(e, seg.path)"
            style="cursor:pointer"
          />
          <!-- Botón de eliminar -->
          <v-circle
            :x="seg.props.x + seg.props.ancho * seg.props.escala - 35"
            :y="seg.props.y + 10"
            :radius="8"
            fill="#f44336" opacity="0.8"
            @click="(e) => handleEliminarClick(e, seg.path)"
            style="cursor:pointer"
          />
          <v-text
            :x="seg.props.x + seg.props.ancho * seg.props.escala - 38"
            :y="seg.props.y + 6"
            text="✖" fontSize="10" fill="white"
            @click="(e) => handleEliminarClick(e, seg.path)"
            style="cursor:pointer"
          />
        </template>
      </v-group>
      
      <!-- Ventana compuesta recursiva -->
      <VistaVentanaCompuestaInterna
        v-else
        v-bind="seg.props"
        :path-prefix="seg.path"
        :modo-exportar="modoExportar"
        @agregar="(payload) => emit('agregar', payload)"
        @editar-ventana="(path) => emit('editar-ventana', path)"
        @eliminar-ventana="(path) => emit('eliminar-ventana', path)"
      />
    </template>

    <!-- Botones "+" entre secciones (azules, ocultos en modo exportar) -->
    <template v-if="!modoExportar" v-for="i in validItems.length + 1" :key="'plus-'+i">
      <v-group @click="emitirAgregar(i-1)" style="cursor:pointer">
        <v-circle
          :x="plusX(i-1)" :y="plusY(i-1)" :radius="14"
          fill="#fff" stroke="#1976d2" :strokeWidth="2" opacity="0.85"
        />
        <v-text
          :x="plusX(i-1) - 7" :y="plusY(i-1) - 10"
          text="+" fontSize="24" fill="#1976d2" fontStyle="bold"
        />
      </v-group>
    </template>
  </v-group>
</template>

<script setup>
import { computed, defineEmits, watch, ref, onUnmounted } from 'vue'
import VentanaSimple from './VentanaSimple.vue'

defineOptions({
  name: 'VistaVentanaCompuestaInterna'
})

const emit = defineEmits(['agregar', 'editar-ventana', 'eliminar-ventana'])

// ✅ NUEVO: Variables para detectar doble click por ventana específica
const clickTimers = ref(new Map()) // Map para manejar timers por path
const lastClickTime = ref(new Map()) // Map para tiempo del último click

const props = defineProps({
  ancho: { type: [Number, String], required: true },
  alto: { type: [Number, String], required: true },
  colorMarco: { type: [String, Object], default: 'blanco' },
  orientacion: { type: String, default: 'horizontal' },
  items: { type: Array, default: () => [] },
  escala: { type: Number, default: 1 },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  pathPrefix: { type: String, default: '' },
  modoExportar: { type: Boolean, default: false }
})

function emitirAgregar(idx) {
  emit('agregar', { pathPrefix: props.pathPrefix, idx })
}

function editarVentana(path) {
  console.log('🔧 Editando ventana por path:', path)
  emit('editar-ventana', path)
}

function eliminarVentana(path) {
  emit('eliminar-ventana', path)
}

// ✅ Nueva función para manejar click en botón editar (con stopPropagation)
function handleEditarClick(event, path) {
  console.log('🔧 Click en botón editar:', path)
  // Detiene la propagación para que no active handleVentanaClick
  if (event && event.evt && event.evt.stopPropagation) {
    event.evt.stopPropagation()
  }
  editarVentana(path)
}

// ✅ Nueva función para manejar click en botón eliminar (con stopPropagation)
function handleEliminarClick(event, path) {
  console.log('🗑️ Click en botón eliminar:', path)
  // Detiene la propagación para que no active handleVentanaClick
  if (event && event.evt && event.evt.stopPropagation) {
    event.evt.stopPropagation()
  }
  eliminarVentana(path)
}

// ✅ NUEVA función mejorada para detectar doble click
function handleVentanaClick(path) {
  const now = Date.now()
  const lastTime = lastClickTime.value.get(path) || 0
  const timeDiff = now - lastTime
  
  console.log(`👆 Click en ventana ${path}, tiempo desde último: ${timeDiff}ms`)
  
  // Si el tiempo entre clicks es menor a 400ms, es doble click
  if (timeDiff < 400 && timeDiff > 50) { // 50ms mínimo para evitar clicks accidentales
    console.log('🎯 ¡DOBLE CLICK detectado! Editando ventana:', path)
    // Limpia el timer si existe
    if (clickTimers.value.has(path)) {
      clearTimeout(clickTimers.value.get(path))
      clickTimers.value.delete(path)
    }
    // Limpia el último tiempo de click
    lastClickTime.value.delete(path)
    // Abre el modal de edición
    editarVentana(path)
    return
  }
  
  // Es un click simple - actualiza el tiempo
  lastClickTime.value.set(path, now)
  
  // Limpia el timer anterior si existe
  if (clickTimers.value.has(path)) {
    clearTimeout(clickTimers.value.get(path))
  }
  
  // Crea un nuevo timer para limpiar después de 400ms
  const timer = setTimeout(() => {
    console.log('⏱️ Timer expirado para ventana:', path)
    lastClickTime.value.delete(path)
    clickTimers.value.delete(path)
  }, 400)
  
  clickTimers.value.set(path, timer)
}

// ✅ Limpia timers cuando el componente se desmonta
onUnmounted(() => {
  // Limpia todos los timers activos
  clickTimers.value.forEach(timer => clearTimeout(timer))
  clickTimers.value.clear()
  lastClickTime.value.clear()
})

const anchoMM = computed(() => Number(props.ancho) || 1)
const altoMM = computed(() => Number(props.alto) || 1)

const validItems = computed(() => 
  props.items.filter(it => 
    it && 
    (typeof it.tipo === 'number' || it.tipo === 'compuesta') &&
    it.tipo !== null && 
    it.tipo !== undefined
  )
)

const porcentajes = computed(() => {
  const n = validItems.value.length
  if (!n) return []
  
  const sum = validItems.value.reduce((a, it) => {
    const percent = Number(it?.sizePercent) || 0
    return a + percent
  }, 0)
  
  if (sum > 0) {
    return validItems.value.map(it => {
      const percent = Number(it?.sizePercent) || 0
      return Math.max(0, Math.min(1, percent / sum))
    })
  }
  return Array(n).fill(1 / n)
})

// ✅ Watch profundo para detectar cambios en los items
watch(() => props.items, () => {
  console.log('🔄 Items cambiaron, forzando recálculo de segmentos')
}, { deep: true })

const segmentos = computed(() => {
  console.log('🎯 INTERNA - RECALCULANDO SEGMENTOS')
  console.log('Props colorMarco:', props.colorMarco)
  
  const segs = []
  let xMM = 0, yMM = 0
  
  validItems.value.forEach((it, i) => {
    // ✅ CAMBIO PRINCIPAL: Usa dimensiones específicas del item SI existen
    let anchoVentanaReal, altoVentanaReal
    
    if (it.ancho && it.alto) {
      // ✅ Si el item tiene dimensiones específicas, úsalas directamente
      anchoVentanaReal = Number(it.ancho)
      altoVentanaReal = Number(it.alto)
      console.log(`📐 Ventana ${i} - Usando dimensiones específicas:`, { ancho: anchoVentanaReal, alto: altoVentanaReal })
    } else {
      // ✅ Si no tiene dimensiones específicas, calcula por porcentaje
      const p = porcentajes.value[i] || 0
      
      if (props.orientacion === 'horizontal') {
        anchoVentanaReal = anchoMM.value * p
        altoVentanaReal = altoMM.value
      } else {
        anchoVentanaReal = anchoMM.value
        altoVentanaReal = altoMM.value * p
      }
      console.log(`📐 Ventana ${i} - Calculado por porcentaje:`, { ancho: anchoVentanaReal, alto: altoVentanaReal, porcentaje: p })
    }
    
    const xPx = props.x + (props.orientacion === 'horizontal' ? xMM * props.escala : 0)
    const yPx = props.y + (props.orientacion === 'vertical' ? yMM * props.escala : 0)
    
    // ✅ Construye el path para rastrear ubicación anidada
    const currentPath = props.pathPrefix ? `${props.pathPrefix}.${i}` : `${i}`
    
    const baseProps = {
      x: xPx,
      y: yPx,
      ancho: anchoVentanaReal,
      alto: altoVentanaReal,
      escala: props.escala,
      colorMarco: props.colorMarco // ✅ Asegúrate de que esto esté aquí
    }

    if (it?.tipo === 'compuesta') {
      segs.push({
        key: 'comp-' + i,
        path: currentPath,
        isCompuesta: true,
        props: { 
          ...baseProps, 
          orientacion: it.orientacion || 'horizontal', 
          items: it.items || [],
          pathPrefix: currentPath
        }
      })
    } else {
      segs.push({
        key: 'simple-' + i,
        path: currentPath,
        isCompuesta: false,
        props: {
          ...baseProps,
          tipo: it.tipo,
          ladoApertura: it.ladoApertura || 'izquierda',
          direccionApertura: it.direccionApertura || 'interior'
        }
      })
    }
    
    // ✅ Avanza la posición usando las dimensiones calculadas
    if (props.orientacion === 'horizontal') xMM += anchoVentanaReal
    else yMM += altoVentanaReal
  })
  
  return segs
})

const plusX = (idx) => {
  if (props.orientacion === 'horizontal') {
    let x = props.x
    for (let i = 0; i < idx; i++) {
      const item = validItems.value[i]
      if (item?.ancho) {
        x += Number(item.ancho) * props.escala
      } else {
        const p = porcentajes.value[i] || 0
        x += (anchoMM.value * p) * props.escala
      }
    }
    return x
  }
  return props.x + (anchoMM.value * props.escala) / 2
}

const plusY = (idx) => {
  if (props.orientacion === 'vertical') {
    let y = props.y
    for (let i = 0; i < idx; i++) {
      const item = validItems.value[i]
      if (item?.alto) {
        y += Number(item.alto) * props.escala
      } else {
        const p = porcentajes.value[i] || 0
        y += (altoMM.value * p) * props.escala
      }
    }
    return y
  }
  return props.y + (altoMM.value * props.escala) / 2
}
</script>

<style scoped>
/* Estilos específicos para VistaVentanaCompuestaInterna */
</style>