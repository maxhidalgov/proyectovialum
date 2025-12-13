<template>
  <div 
    class="nodo-vista"
    :class="{ 'clickable': !nodo.hijos }"
    :style="getNodeStyle()"
    @click.stop="$emit('click-nodo', nodo)"
  >
    <!-- Etiquetas de dimensiones para subdivisiones -->
    <div 
      v-if="mostrarEtiquetas && etiquetasVisibles && nodo.tipo !== 'marco'" 
      class="etiqueta-dimension etiqueta-ancho" 
      :style="getEtiquetaAnchoStyle()"
      draggable="true"
      @dragstart="iniciarArrastre($event, 'ancho')"
      @click.stop="editarDimension('ancho')"
    >
      {{ nodo.ancho }}mm
    </div>
    <div 
      v-if="mostrarEtiquetas && etiquetasVisibles && nodo.tipo !== 'marco'" 
      class="etiqueta-dimension etiqueta-alto" 
      :style="getEtiquetaAltoStyle()"
      draggable="true"
      @dragstart="iniciarArrastre($event, 'alto')"
      @click.stop="editarDimension('alto')"
    >
      {{ nodo.alto }}mm
    </div>

    <!-- Si tiene tipo asignado, renderizar componente -->
    <div v-if="nodo.tipoVentanaId && !nodo.hijos" class="componente-wrapper">
      <component
        :is="componentesVista[nodo.tipoVentanaId]"
        :ancho="nodo.ancho"
        :alto="nodo.alto"
        :color-marco="colorMarco"
      />
    </div>

    <!-- Si tiene hijos, renderizar división -->
    <div v-else-if="nodo.hijos" class="division">
      <div 
        class="division-container"
        :style="getDivisionContainerStyle()"
      >
        <!-- Nodo 1 -->
        <div class="hijo-nodo" :style="getHijo1Style()">
          <NodoVista
            :nodo="nodo.hijos.nodo1"
            :color-marco="colorMarco"
            :componentes-vista="componentesVista"
            :escala-global="escalaGlobal"
            :mostrar-etiquetas="true"
            :etiquetas-visibles="etiquetasVisibles"
            @click-nodo="$emit('click-nodo', $event)"
            @actualizar-dimension="$emit('actualizar-dimension', $event)"
            @mover-divisor="$emit('mover-divisor', $event)"
            @finalizar-movimiento-divisor="$emit('finalizar-movimiento-divisor')"
          />
        </div>

        <!-- Divisor visual -->
        <div 
          class="divisor-visual"
          :style="getDivisorStyle()"
          @mousedown="iniciarArrastreDivisor"
          @click.stop
        >
          <div class="divisor-inner">
            <v-icon size="16" color="white">
              {{ nodo.hijos.orientacion === 'horizontal' ? 'mdi-drag-horizontal-variant' : 'mdi-drag-vertical-variant' }}
            </v-icon>
          </div>
        </div>

        <!-- Nodo 2 -->
        <div class="hijo-nodo" :style="getHijo2Style()">
          <NodoVista
            :nodo="nodo.hijos.nodo2"
            :color-marco="colorMarco"
            :componentes-vista="componentesVista"
            :escala-global="escalaGlobal"
            :mostrar-etiquetas="true"
            :etiquetas-visibles="etiquetasVisibles"
            @click-nodo="$emit('click-nodo', $event)"
            @actualizar-dimension="$emit('actualizar-dimension', $event)"
            @mover-divisor="$emit('mover-divisor', $event)"
            @finalizar-movimiento-divisor="$emit('finalizar-movimiento-divisor')"
          />
        </div>
      </div>
    </div>

    <!-- Espacio vacío -->
    <div v-else class="espacio-vacio">
      <div class="espacio-placeholder">
        <v-icon size="32" color="grey-lighten-1">mdi-crop-square</v-icon>
        <div class="text-caption text-grey mt-1">
          {{ nodo.ancho }}mm × {{ nodo.alto }}mm
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  nodo: { type: Object, required: true },
  colorMarco: { type: String, default: 'blanco' },
  componentesVista: { type: Object, required: true },
  escalaGlobal: { type: Number, default: 1 },
  mostrarEtiquetas: { type: Boolean, default: true },
  etiquetasVisibles: { type: Boolean, default: true },
})

const emit = defineEmits(['click-nodo', 'actualizar-dimension', 'mover-divisor', 'finalizar-movimiento-divisor'])

// Estado para drag & drop de etiquetas
const arrastrando = ref(null)
const posicionInicial = ref({ x: 0, y: 0 })

// Estado para drag & drop de divisor
const arrastandoDivisor = ref(false)
const divisorPosicionInicial = ref(0)

function iniciarArrastre(event, tipo) {
  arrastrando.value = tipo
  posicionInicial.value = {
    x: event.clientX,
    y: event.clientY,
  }
  event.dataTransfer.effectAllowed = 'move'
}

function iniciarArrastreDivisor(event) {
  if (!props.nodo.hijos) return
  
  arrastandoDivisor.value = true
  const orientacion = props.nodo.hijos.orientacion
  const posicionInicialMouse = orientacion === 'horizontal' ? event.clientY : event.clientX
  const posicionInicialDivisor = props.nodo.hijos.divisor.posicion
  
  const moverDivisor = (e) => {
    if (!arrastandoDivisor.value) return
    
    const posicionActualMouse = orientacion === 'horizontal' ? e.clientY : e.clientX
    const deltaPixeles = posicionActualMouse - posicionInicialMouse
    const deltaMilimetros = deltaPixeles / props.escalaGlobal
    const nuevaPosicion = posicionInicialDivisor + deltaMilimetros
    
    // Validar límites
    const total = orientacion === 'horizontal' ? props.nodo.alto : props.nodo.ancho
    const minimo = 50 // Mínimo 50mm
    const divisorSize = 8
    const maximo = total - minimo - divisorSize // Máximo menos mínimo del otro lado menos divisor
    
    if (nuevaPosicion >= minimo && nuevaPosicion <= maximo) {
      emit('mover-divisor', {
        nodo: props.nodo,
        nuevaPosicion: Math.round(nuevaPosicion),
      })
    }
  }
  
  const terminarArrastre = () => {
    arrastandoDivisor.value = false
    document.removeEventListener('mousemove', moverDivisor)
    document.removeEventListener('mouseup', terminarArrastre)
    
    // Notificar que terminó el arrastre para guardar en historial
    emit('finalizar-movimiento-divisor')
  }
  
  document.addEventListener('mousemove', moverDivisor)
  document.addEventListener('mouseup', terminarArrastre)
  
  event.preventDefault()
  event.stopPropagation()
}

function editarDimension(tipo) {
  const valorActual = tipo === 'ancho' ? props.nodo.ancho : props.nodo.alto
  const nuevoValor = prompt(`Ingrese el nuevo ${tipo} en milímetros:`, valorActual)
  
  if (nuevoValor !== null && !isNaN(nuevoValor)) {
    const valor = parseInt(nuevoValor)
    if (valor > 0) {
      emit('actualizar-dimension', {
        nodo: props.nodo,
        tipo,
        valor,
      })
    }
  }
}

function getNodeStyle() {
  return {
    width: `${props.nodo.ancho}px`,
    height: `${props.nodo.alto}px`,
    position: 'relative',
    border: '1px solid #666',
    boxSizing: 'border-box',
    overflow: 'visible',
  }
}

function getDivisionContainerStyle() {
  if (!props.nodo.hijos) return {}
  
  const orientacion = props.nodo.hijos.orientacion
  return {
    display: 'flex',
    flexDirection: orientacion === 'horizontal' ? 'column' : 'row',
    width: '100%',
    height: '100%',
  }
}

function getHijo1Style() {
  if (!props.nodo.hijos) return {}
  
  const orientacion = props.nodo.hijos.orientacion
  const posicion = props.nodo.hijos.divisor.posicion || 0
  
  if (orientacion === 'horizontal') {
    return {
      width: '100%',
      height: `${posicion}px`,
      flexShrink: 0,
    }
  } else {
    return {
      width: `${posicion}px`,
      height: '100%',
      flexShrink: 0,
    }
  }
}

function getHijo2Style() {
  if (!props.nodo.hijos) return {}
  
  const orientacion = props.nodo.hijos.orientacion
  const posicion = props.nodo.hijos.divisor.posicion || 0
  const total = orientacion === 'horizontal' ? props.nodo.alto : props.nodo.ancho
  const divisorSize = 8
  const restante = total - posicion - divisorSize
  
  if (orientacion === 'horizontal') {
    return {
      width: '100%',
      height: `${restante}px`,
      flexShrink: 0,
    }
  } else {
    return {
      width: `${restante}px`,
      height: '100%',
      flexShrink: 0,
    }
  }
}

function getDivisorStyle() {
  if (!props.nodo.hijos) return {}
  
  const orientacion = props.nodo.hijos.orientacion
  const divisorSize = '8px'
  
  return {
    background: '#1a1a1a',
    [orientacion === 'horizontal' ? 'height' : 'width']: divisorSize,
    [orientacion === 'horizontal' ? 'width' : 'height']: '100%',
    flexShrink: 0,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
    boxShadow: '0 0 4px rgba(0, 0, 0, 0.8)',
    border: '1px solid #000',
    cursor: orientacion === 'horizontal' ? 'ns-resize' : 'ew-resize',
    userSelect: 'none',
    transition: 'background 0.2s',
  }
}

function getLabelAnchoStyle() {
  // Tamaño fijo para que sea siempre legible independiente del tamaño de la ventana
  const fontSize = 18
  return {
    position: 'absolute',
    top: '-32px',
    left: '50%',
    transform: 'translateX(-50%)',
    fontSize: `${fontSize}px`,
    fontWeight: 'bold',
    color: '#fff',
    background: '#2196F3',
    padding: '6px 12px',
    borderRadius: '4px',
    whiteSpace: 'nowrap',
    zIndex: 10,
    border: '2px solid #1976D2',
    boxShadow: '0 2px 6px rgba(0, 0, 0, 0.4)',
  }
}

function getLabelAltoStyle() {
  // Tamaño fijo para que sea siempre legible independiente del tamaño de la ventana
  const fontSize = 18
  return {
    position: 'absolute',
    left: '-25px',
    top: '50%',
    transform: 'translateY(-50%) rotate(-90deg)',
    transformOrigin: 'center',
    fontSize: `${fontSize}px`,
    fontWeight: 'bold',
    color: '#fff',
    background: '#2196F3',
    padding: '6px 12px',
    borderRadius: '4px',
    whiteSpace: 'nowrap',
    zIndex: 10,
    border: '2px solid #1976D2',
    boxShadow: '0 2px 6px rgba(0, 0, 0, 0.4)',
  }
}

function getEtiquetaAnchoStyle() {
  // Tamaño en pixeles que NO se escala (se ajusta por la inversa de la escala)
  const fontSize = 14 / props.escalaGlobal
  const padding = (4 / props.escalaGlobal)
  const borderWidth = (2 / props.escalaGlobal)
  const offset = (28 / props.escalaGlobal)
  
  return {
    position: 'absolute',
    fontSize: `${fontSize}px`,
    padding: `${padding}px ${padding * 2}px`,
    borderWidth: `${borderWidth}px`,
    top: `-${offset}px`,
    left: '50%',
    transform: 'translateX(-50%)',
    zIndex: 100,
  }
}

function getEtiquetaAltoStyle() {
  // Tamaño en pixeles que NO se escala (se ajusta por la inversa de la escala)
  const fontSize = 14 / props.escalaGlobal
  const padding = (4 / props.escalaGlobal)
  const borderWidth = (2 / props.escalaGlobal)
  const offset = (28 / props.escalaGlobal)
  
  return {
    position: 'absolute',
    fontSize: `${fontSize}px`,
    padding: `${padding}px ${padding * 2}px`,
    borderWidth: `${borderWidth}px`,
    left: `-${offset}px`,
    top: '50%',
    transform: 'translateY(-50%) rotate(-90deg)',
    transformOrigin: 'center',
    zIndex: 100,
  }
}


</script>

<style scoped>
.nodo-vista {
  box-sizing: border-box;
  overflow: visible;
  position: relative;
}

.dimensiones-labels {
  pointer-events: none;
}

.etiqueta-dimension {
  position: absolute;
  font-weight: bold;
  color: #fff;
  background: #FF9800;
  border-radius: 3px;
  white-space: nowrap;
  border-style: solid;
  border-color: #F57C00;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
  pointer-events: auto;
  z-index: 51;
  cursor: move;
  user-select: none;
  transition: all 0.2s;
}

.etiqueta-dimension:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
  filter: brightness(1.1);
}

.label-ancho,
.label-alto {
  pointer-events: none;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.clickable {
  cursor: pointer;
  transition: box-shadow 0.2s;
}

.clickable:hover {
  box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.5);
}

.componente-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: rgba(135, 206, 235, 0.2);
}

.componente-wrapper :deep(canvas) {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.division {
  width: 100%;
  height: 100%;
}

.division-container {
  width: 100%;
  height: 100%;
}

.divisor-visual {
  position: relative;
}

.divisor-visual:hover {
  background: #2196F3 !important;
}

.divisor-visual:active {
  background: #1976D2 !important;
}

.divisor-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  opacity: 0;
  transition: opacity 0.2s;
}

.divisor-visual:hover .divisor-inner {
  opacity: 1;
}

.hijo-nodo {
  flex-shrink: 0;
  box-sizing: border-box;
  overflow: visible;
}

.divisor-visual {
  flex-shrink: 0;
  position: relative;
}

.divisor-inner {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, #555 0%, #333 100%);
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.8), inset 0 0 3px rgba(255, 255, 255, 0.2);
  border: 1px solid #222;
}

.espacio-vacio {
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.espacio-placeholder {
  text-align: center;
}
</style>
