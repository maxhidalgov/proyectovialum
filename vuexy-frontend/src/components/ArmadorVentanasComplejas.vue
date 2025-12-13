<template>
  <div class="armador-complejas">
    <!-- Toolbar Superior -->
    <div class="toolbar-top">
      <div class="toolbar-title">
        <v-icon>mdi-view-dashboard</v-icon>
        <span class="ml-2">Armador de Ventanas Complejas</span>
      </div>
      <div class="toolbar-actions">
        <v-btn size="small" variant="text" @click="deshacer" :disabled="!puedeDeshacer">
          <v-icon>mdi-undo</v-icon>
        </v-btn>
        <v-btn size="small" variant="text" @click="rehacer" :disabled="!puedeRehacer">
          <v-icon>mdi-redo</v-icon>
        </v-btn>
        <v-btn size="small" variant="text" color="error" @click="limpiar">
          <v-icon>mdi-delete-sweep</v-icon>
        </v-btn>
      </div>
    </div>

    <div class="armador-content">
      <!-- Panel Izquierdo: Árbol de Estructura -->
      <div class="panel-tree">
        <v-card elevation="0" class="fill-height">
          <v-card-title class="d-flex align-center pa-3 bg-primary">
            <v-icon class="mr-2">mdi-file-tree</v-icon>
            Estructura
          </v-card-title>
          
          <v-card-text class="pa-3">
            <!-- Configuración del Marco Principal -->
            <div v-if="!estructura" class="text-center py-8">
              <v-icon size="64" color="grey-lighten-1">mdi-window-closed-variant</v-icon>
              <div class="text-body-2 text-grey mt-2">
                Define el marco principal
              </div>
              <v-btn 
                color="primary" 
                variant="flat" 
                class="mt-4"
                @click="mostrarDialogoMarco = true"
              >
                <v-icon left>mdi-plus</v-icon>
                Crear Marco
              </v-btn>
            </div>

            <!-- Árbol de nodos -->
            <div v-else>
              <NodoEstructura
                :nodo="estructura"
                :nivel="0"
                :seleccionado="nodoSeleccionado?.id === estructura.id"
                @seleccionar="seleccionarNodo"
                @dividir="dividirNodo"
                @asignar-tipo="asignarTipoANodo"
                @eliminar="eliminarNodo"
              />
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Panel Central: Vista Previa -->
      <div class="panel-preview">
        <v-card elevation="0" class="fill-height">
          <v-card-title class="d-flex align-center justify-space-between pa-3 bg-primary">
            <div class="d-flex align-center">
              <v-icon class="mr-2">mdi-eye</v-icon>
              Vista Previa
            </div>
            <div class="d-flex align-center gap-2">
              <div class="text-caption">
                {{ estructura ? `${estructura.ancho}mm × ${estructura.alto}mm` : '' }}
              </div>
              <v-btn
                icon
                size="small"
                variant="tonal"
                color="white"
                @click="etiquetasVisibles = !etiquetasVisibles"
                :title="etiquetasVisibles ? 'Ocultar etiquetas' : 'Mostrar etiquetas'"
              >
                <v-icon>{{ etiquetasVisibles ? 'mdi-label' : 'mdi-label-off' }}</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="tonal"
                color="white"
                @click="panelPropiedadesVisible = !panelPropiedadesVisible"
                :title="panelPropiedadesVisible ? 'Ocultar propiedades' : 'Mostrar propiedades'"
              >
                <v-icon>{{ panelPropiedadesVisible ? 'mdi-chevron-right' : 'mdi-chevron-left' }}</v-icon>
              </v-btn>
            </div>
          </v-card-title>
          
          <v-card-text class="pa-4 preview-content">
            <div v-if="!estructura" class="empty-preview">
              <v-icon size="80" color="grey-lighten-2">mdi-image-off</v-icon>
              <div class="text-body-2 text-grey mt-3">
                Crea un marco para ver la vista previa
              </div>
            </div>
            
            <!-- Renderizar estructura recursivamente -->
            <div v-else class="preview-wrapper" ref="previewContainer">
              <!-- Etiquetas de dimensiones principales (fuera de la escala) -->
              <div v-if="estructura" v-show="etiquetasVisibles" class="dimensiones-principales">
                <div class="label-dimension label-ancho-principal" :style="getLabelAnchoPos()">
                  {{ estructura.ancho }}mm
                </div>
                <div class="label-dimension label-alto-principal" :style="getLabelAltoPos()">
                  {{ estructura.alto }}mm
                </div>
              </div>
              
              <div class="preview-scaler" :key="escalaKey" :style="getScaleStyle()">
                <NodoVista
                  :nodo="estructura"
                  :color-marco="colorMarco"
                  :componentes-vista="componentesVista"
                  :escala-global="getEscalaActual()"
                  :mostrar-etiquetas="false"
                  :etiquetas-visibles="etiquetasVisibles"
                  @click-nodo="seleccionarNodo"
                  @actualizar-dimension="actualizarDimension"
                  @mover-divisor="moverDivisor"
                  @finalizar-movimiento-divisor="finalizarMovimientoDivisor"
                />
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Panel Derecho: Propiedades -->
      <div v-show="panelPropiedadesVisible" class="panel-properties">
        <v-card elevation="0" class="fill-height">
          <v-card-title class="d-flex align-center pa-3 bg-primary">
            <v-icon class="mr-2">mdi-cog</v-icon>
            Propiedades
          </v-card-title>
          
          <v-card-text class="pa-3">
            <div v-if="!nodoSeleccionado" class="text-center py-8 text-grey">
              <v-icon size="48">mdi-cursor-default-click</v-icon>
              <div class="text-body-2 mt-2">
                Selecciona un elemento
              </div>
            </div>

            <div v-else>
              <!-- Propiedades del nodo -->
              <v-list density="compact">
                <v-list-item>
                  <v-list-item-title class="text-caption text-grey">Tipo</v-list-item-title>
                  <v-list-item-subtitle class="text-body-2 font-weight-bold">
                    {{ nodoSeleccionado.tipo === 'marco' ? 'Marco' : nodoSeleccionado.tipoVentanaId ? 'Ventana' : 'Espacio' }}
                  </v-list-item-subtitle>
                </v-list-item>
                
                <v-list-item>
                  <v-list-item-title class="text-caption text-grey">Dimensiones</v-list-item-title>
                  <v-list-item-subtitle class="text-body-2">
                    {{ nodoSeleccionado.ancho }}mm × {{ nodoSeleccionado.alto }}mm
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>

              <v-divider class="my-3" />

              <!-- Acciones -->
              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-if="!nodoSeleccionado.tipoVentanaId && !nodoSeleccionado.hijos"
                  block
                  color="primary"
                  variant="tonal"
                  size="small"
                  @click="mostrarDialogoDividir = true"
                >
                  <v-icon left>mdi-page-layout-sidebar-left</v-icon>
                  Dividir
                </v-btn>

                <v-btn
                  v-if="!nodoSeleccionado.tipoVentanaId && !nodoSeleccionado.hijos"
                  block
                  color="success"
                  variant="tonal"
                  size="small"
                  @click="mostrarDialogoAsignarTipo = true"
                >
                  <v-icon left>mdi-window-closed-variant</v-icon>
                  Asignar Tipo
                </v-btn>

                <v-btn
                  v-if="nodoSeleccionado.tipoVentanaId"
                  block
                  color="warning"
                  variant="tonal"
                  size="small"
                  @click="limpiarTipoNodo"
                >
                  <v-icon left>mdi-eraser</v-icon>
                  Quitar Tipo
                </v-btn>

                <v-btn
                  v-if="nodoSeleccionado.tipo !== 'marco'"
                  block
                  color="error"
                  variant="outlined"
                  size="small"
                  @click="eliminarNodo(nodoSeleccionado)"
                >
                  <v-icon left>mdi-delete</v-icon>
                  Eliminar
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>
    </div>

    <!-- Acciones Finales -->
    <div class="actions-bottom">
      <v-btn @click="$emit('cancelar')" variant="text">
        Cancelar
      </v-btn>
      <v-btn 
        @click="aplicarConfiguracion" 
        color="primary" 
        variant="flat"
        :disabled="!estructura"
      >
        <v-icon left>mdi-check</v-icon>
        Aplicar Configuración
      </v-btn>
    </div>

    <!-- Diálogo: Crear Marco -->
    <v-dialog v-model="mostrarDialogoMarco" max-width="500">
      <v-card>
        <v-card-title>Crear Marco Principal</v-card-title>
        <v-card-text>
          <v-text-field
            v-model.number="nuevoMarco.ancho"
            label="Ancho (mm)"
            type="number"
            variant="outlined"
            density="compact"
            class="mb-3"
          />
          <v-text-field
            v-model.number="nuevoMarco.alto"
            label="Alto (mm)"
            type="number"
            variant="outlined"
            density="compact"
            class="mb-3"
          />
          <v-autocomplete
            v-model="nuevoMarco.perfilId"
            :items="perfilesMarco"
            item-title="nombre"
            item-value="id"
            label="Perfil del Marco"
            variant="outlined"
            density="compact"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="mostrarDialogoMarco = false">Cancelar</v-btn>
          <v-btn color="primary" @click="crearMarco">Crear</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Diálogo: Dividir Nodo -->
    <v-dialog v-model="mostrarDialogoDividir" max-width="500">
      <v-card>
        <v-card-title>Dividir Espacio</v-card-title>
        <v-card-text>
          <v-radio-group v-model="divisionConfig.orientacion">
            <v-radio label="Horizontal" value="horizontal" />
            <v-radio label="Vertical" value="vertical" />
          </v-radio-group>
          
          <v-text-field
            v-model.number="divisionConfig.posicion"
            :label="`Posición (mm) - Max: ${nodoSeleccionado ? (divisionConfig.orientacion === 'horizontal' ? nodoSeleccionado.alto : nodoSeleccionado.ancho) : 0}`"
            type="number"
            variant="outlined"
            density="compact"
            :max="nodoSeleccionado ? (divisionConfig.orientacion === 'horizontal' ? nodoSeleccionado.alto : nodoSeleccionado.ancho) : 0"
          />
          
          <v-autocomplete
            v-model="divisionConfig.perfilId"
            :items="perfilesDivisores"
            item-title="nombre"
            item-value="id"
            label="Perfil del Divisor"
            variant="outlined"
            density="compact"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="mostrarDialogoDividir = false">Cancelar</v-btn>
          <v-btn color="primary" @click="confirmarDividir">Dividir</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Diálogo: Asignar Tipo -->
    <v-dialog v-model="mostrarDialogoAsignarTipo" max-width="500">
      <v-card>
        <v-card-title>Asignar Tipo de Ventana</v-card-title>
        <v-card-text>
          <div v-if="tiposVentana.length === 0" class="text-caption text-grey mb-2">
            ⚠️ No hay tipos de ventana disponibles para este material
          </div>
          <v-autocomplete
            v-model="tipoSeleccionado"
            :items="tiposVentana"
            item-title="nombre"
            item-value="id"
            label="Tipo de Ventana"
            variant="outlined"
            density="compact"
            :no-data-text="'No hay tipos de ventana disponibles'"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="mostrarDialogoAsignarTipo = false">Cancelar</v-btn>
          <v-btn color="primary" @click="confirmarAsignarTipo">Asignar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import NodoEstructura from './armador/NodoEstructura.vue'
import NodoVista from './armador/NodoVista.vue'

// Importar componentes Vista - S60 (PVC)
import VistaVentanaFijaS60 from './armador/VistaVentanaFijaArmador.vue'
import VistaVentanaProyectanteS60 from './armador/VistaVentanaProyectanteArmador.vue'
import VistaVentanaCorredera from './VistaVentanaCorredera.vue'
import VistaVentanaAbatirS60 from './VistaVentanaAbatirS60.vue'
import VistaPuertaS60 from './VistaPuertaS60.vue'

// Importar componentes Vista - AL42 (Aluminio) - Versiones Armador
import VistaVentanaFijaAL42 from './armador/VistaVentanaFijaAL42Armador.vue'
import VistaVentanaProyectanteAL42 from './armador/VistaVentanaProyectanteAL42Armador.vue'
import VistaVentanaCorrederaAL25 from './armador/VistaVentanaCorrederaAL25Armador.vue'
import VistaVentanaCompuestaAL42 from './VistaVentanaCompuestaAL42.vue'

const componentesVista = {
  // PVC - S60
  2: VistaVentanaFijaS60,
  45: VistaVentanaProyectanteS60,
  3: VistaVentanaCorredera,
  49: VistaVentanaAbatirS60,
  50: VistaPuertaS60,
  
  // Aluminio - AL42/AL25 - Versiones Armador (sin controles)
  1: VistaVentanaFijaAL42,
  55: VistaVentanaCorrederaAL25,
  56: VistaVentanaProyectanteAL42,
  57: VistaVentanaCompuestaAL42,
}

const props = defineProps({
  tiposVentana: { type: Array, default: () => [] },
  perfilesMarco: { type: Array, default: () => [] },
  perfilesDivisores: { type: Array, default: () => [] },
  colorMarco: { type: String, default: 'blanco' },
})

const emit = defineEmits(['actualizar', 'cancelar'])

// Debug: Ver qué tipos de ventana llegan
watch(() => props.tiposVentana, (newVal) => {
  console.log('🪟 Tipos de ventana en ArmadorVentanasComplejas:', newVal)
}, { immediate: true })

// Estado
const estructura = ref(null)
const nodoSeleccionado = ref(null)
const historial = ref([])
const historialIndex = ref(-1)
const previewContainer = ref(null)
const panelPropiedadesVisible = ref(true)
const etiquetasVisibles = ref(true)
const escalaKey = ref(0) // Key para forzar re-render

// Diálogos
const mostrarDialogoMarco = ref(false)
const mostrarDialogoDividir = ref(false)
const mostrarDialogoAsignarTipo = ref(false)

// Configuraciones
const nuevoMarco = ref({ ancho: 2400, alto: 1600, perfilId: null })
const divisionConfig = ref({ orientacion: 'horizontal', posicion: 800, perfilId: null })
const tipoSeleccionado = ref(null)

// Historial
const puedeDeshacer = computed(() => historialIndex.value > 0)
const puedeRehacer = computed(() => historialIndex.value < historial.value.length - 1)

// Watch para emitir cambios automáticamente cuando cambia la estructura
watch(() => estructura.value, (newVal) => {
  if (newVal) {
    emit('actualizar', newVal)
  }
}, { deep: true })

let contadorId = 1

function generarId() {
  return `nodo-${contadorId++}`
}

function crearMarco() {
  estructura.value = {
    id: generarId(),
    tipo: 'marco',
    ancho: nuevoMarco.value.ancho,
    alto: nuevoMarco.value.alto,
    perfilId: nuevoMarco.value.perfilId,
    hijos: null,
    tipoVentanaId: null,
  }
  
  mostrarDialogoMarco.value = false
  guardarEnHistorial()
  aplicarConfiguracion() // Emitir evento para recalcular costos
}

function seleccionarNodo(nodo) {
  nodoSeleccionado.value = nodo
}

function dividirNodo(nodo, config) {
  divisionConfig.value = { ...config }
  nodoSeleccionado.value = nodo
  mostrarDialogoDividir.value = true
}

function confirmarDividir() {
  if (!nodoSeleccionado.value) return

  const orientacion = divisionConfig.value.orientacion
  const posicion = divisionConfig.value.posicion
  
  const dimension = orientacion === 'horizontal' 
    ? nodoSeleccionado.value.alto 
    : nodoSeleccionado.value.ancho

  if (posicion <= 0 || posicion >= dimension) {
    alert(`La posición debe estar entre 0 y ${dimension}`)
    return
  }

  // Crear dos hijos
  const divisorSize = 8
  nodoSeleccionado.value.hijos = {
    orientacion,
    divisor: {
      posicion,
      perfilId: divisionConfig.value.perfilId,
    },
    nodo1: {
      id: generarId(),
      tipo: 'espacio',
      ancho: orientacion === 'vertical' ? posicion : nodoSeleccionado.value.ancho,
      alto: orientacion === 'horizontal' ? posicion : nodoSeleccionado.value.alto,
      hijos: null,
      tipoVentanaId: null,
    },
    nodo2: {
      id: generarId(),
      tipo: 'espacio',
      ancho: orientacion === 'vertical' ? (nodoSeleccionado.value.ancho - posicion - divisorSize) : nodoSeleccionado.value.ancho,
      alto: orientacion === 'horizontal' ? (nodoSeleccionado.value.alto - posicion - divisorSize) : nodoSeleccionado.value.alto,
      hijos: null,
      tipoVentanaId: null,
    },
  }

  mostrarDialogoDividir.value = false
  guardarEnHistorial()
  aplicarConfiguracion() // Emitir evento para recalcular costos
}

function asignarTipoANodo(nodo) {
  nodoSeleccionado.value = nodo
  tipoSeleccionado.value = nodo.tipoVentanaId
  mostrarDialogoAsignarTipo.value = true
}

function confirmarAsignarTipo() {
  if (!nodoSeleccionado.value || !tipoSeleccionado.value) return
  
  nodoSeleccionado.value.tipoVentanaId = tipoSeleccionado.value
  mostrarDialogoAsignarTipo.value = false
  guardarEnHistorial()
  aplicarConfiguracion() // Emitir evento para recalcular costos
}

function limpiarTipoNodo() {
  if (nodoSeleccionado.value) {
    nodoSeleccionado.value.tipoVentanaId = null
    guardarEnHistorial()
    aplicarConfiguracion() // Emitir evento para recalcular costos
  }
}

function eliminarNodo(nodo) {
  // TODO: Implementar eliminación recursiva en árbol
  console.log('Eliminar nodo:', nodo)
}

function limpiar() {
  if (confirm('¿Estás seguro de limpiar toda la estructura?')) {
    estructura.value = null
    nodoSeleccionado.value = null
    guardarEnHistorial()
  }
}

function guardarEnHistorial() {
  const estado = JSON.parse(JSON.stringify(estructura.value))
  historial.value = historial.value.slice(0, historialIndex.value + 1)
  historial.value.push(estado)
  historialIndex.value++
}

function deshacer() {
  if (puedeDeshacer.value) {
    historialIndex.value--
    estructura.value = JSON.parse(JSON.stringify(historial.value[historialIndex.value]))
    aplicarConfiguracion() // Emitir evento para recalcular costos
  }
}

function rehacer() {
  if (puedeRehacer.value) {
    historialIndex.value++
    estructura.value = JSON.parse(JSON.stringify(historial.value[historialIndex.value]))
    aplicarConfiguracion() // Emitir evento para recalcular costos
  }
}

function aplicarConfiguracion() {
  emit('actualizar', estructura.value)
}

function actualizarDimension({ nodo, tipo, valor }) {
  if (tipo === 'ancho') {
    nodo.ancho = valor
  } else if (tipo === 'alto') {
    nodo.alto = valor
  }
  
  // Propagar el cambio hacia arriba (ajustar padres) y hacia abajo (ajustar hijos)
  if (estructura.value) {
    propagarCambio(estructura.value, nodo, tipo, valor)
  }
  
  // Si el nodo tiene hijos, recalcular sus dimensiones
  if (nodo.hijos) {
    recalcularHijos(nodo)
  }
  
  // Guardar en historial
  guardarEnHistorial()
  
  // Emitir evento para recalcular costos
  aplicarConfiguracion()
  
  // Forzar actualización de la vista
  escalaKey.value++
}

function propagarCambio(raiz, nodoModificado, tipo, nuevoValor) {
  // Encontrar el nodo padre y ajustar dimensiones
  const encontrarYAjustar = (nodoActual, nodoBuscado, padre = null, esPrimerHijo = false) => {
    if (nodoActual === nodoBuscado) {
      // Encontramos el nodo, ahora ajustamos el padre si existe
      if (padre && padre.hijos) {
        const orientacion = padre.hijos.orientacion
        const divisorSize = 8
        const posicion = padre.hijos.divisor.posicion
        
        if (tipo === 'ancho' && orientacion === 'vertical') {
          // Cambió el ancho en una división vertical
          // Calcular nuevo ancho total del padre
          const anchoNodo1 = padre.hijos.nodo1.ancho
          const anchoNodo2 = padre.hijos.nodo2.ancho
          const nuevoAnchoTotal = anchoNodo1 + anchoNodo2 + divisorSize
          padre.ancho = nuevoAnchoTotal
          
          // Seguir propagando hacia arriba
          propagarCambio(raiz, padre, tipo, nuevoAnchoTotal)
          
        } else if (tipo === 'alto' && orientacion === 'horizontal') {
          // Cambió el alto en una división horizontal
          const altoNodo1 = padre.hijos.nodo1.alto
          const altoNodo2 = padre.hijos.nodo2.alto
          const nuevoAltoTotal = altoNodo1 + altoNodo2 + divisorSize
          padre.alto = nuevoAltoTotal
          
          // Seguir propagando hacia arriba
          propagarCambio(raiz, padre, tipo, nuevoAltoTotal)
          
        } else if (tipo === 'ancho' && orientacion === 'horizontal') {
          // División horizontal, el ancho se propaga igual a ambos nodos
          padre.ancho = nuevoValor
          padre.hijos.nodo1.ancho = nuevoValor
          padre.hijos.nodo2.ancho = nuevoValor
          propagarCambio(raiz, padre, tipo, nuevoValor)
          
        } else if (tipo === 'alto' && orientacion === 'vertical') {
          // División vertical, el alto se propaga igual a ambos nodos
          padre.alto = nuevoValor
          padre.hijos.nodo1.alto = nuevoValor
          padre.hijos.nodo2.alto = nuevoValor
          propagarCambio(raiz, padre, tipo, nuevoValor)
        }
      }
      return true
    }
    
    if (nodoActual.hijos) {
      if (encontrarYAjustar(nodoActual.hijos.nodo1, nodoBuscado, nodoActual, true)) return true
      if (encontrarYAjustar(nodoActual.hijos.nodo2, nodoBuscado, nodoActual, false)) return true
    }
    
    return false
  }
  
  encontrarYAjustar(raiz, nodoModificado)
}

function recalcularHijos(nodo) {
  if (!nodo.hijos) return
  
  const orientacion = nodo.hijos.orientacion
  const posicion = nodo.hijos.divisor.posicion
  const divisorSize = 8
  
  if (orientacion === 'vertical') {
    // División vertical: mantener proporciones si es posible
    nodo.hijos.nodo1.ancho = posicion
    nodo.hijos.nodo2.ancho = nodo.ancho - posicion - divisorSize
    nodo.hijos.nodo1.alto = nodo.alto
    nodo.hijos.nodo2.alto = nodo.alto
  } else {
    // División horizontal
    nodo.hijos.nodo1.alto = posicion
    nodo.hijos.nodo2.alto = nodo.alto - posicion - divisorSize
    nodo.hijos.nodo1.ancho = nodo.ancho
    nodo.hijos.nodo2.ancho = nodo.ancho
  }
  
  // Recalcular recursivamente los hijos
  if (nodo.hijos.nodo1.hijos) recalcularHijos(nodo.hijos.nodo1)
  if (nodo.hijos.nodo2.hijos) recalcularHijos(nodo.hijos.nodo2)
}

function moverDivisor({ nodo, nuevaPosicion }) {
  if (!nodo.hijos) return
  
  const orientacion = nodo.hijos.orientacion
  const divisorSize = 8
  
  // Actualizar posición del divisor
  nodo.hijos.divisor.posicion = nuevaPosicion
  
  // Recalcular dimensiones de los hijos
  if (orientacion === 'vertical') {
    nodo.hijos.nodo1.ancho = nuevaPosicion
    nodo.hijos.nodo2.ancho = nodo.ancho - nuevaPosicion - divisorSize
  } else {
    nodo.hijos.nodo1.alto = nuevaPosicion
    nodo.hijos.nodo2.alto = nodo.alto - nuevaPosicion - divisorSize
  }
  
  // Recalcular hijos recursivamente
  if (nodo.hijos.nodo1.hijos) recalcularHijos(nodo.hijos.nodo1)
  if (nodo.hijos.nodo2.hijos) recalcularHijos(nodo.hijos.nodo2)
  
  // NO forzar re-render durante el arrastre - Vue lo hace automáticamente
}

function finalizarMovimientoDivisor() {
  // Guardar en historial solo cuando termina el arrastre
  guardarEnHistorial()
  // Ahora sí forzar actualización
  escalaKey.value++
}
 
function getScaleStyle() {
  if (!estructura.value) return {}
  
  // Calcular escala basada en el espacio disponible
  // Ajustar según si el panel de propiedades está visible
  const availableWidth = panelPropiedadesVisible.value ? 500 : 800
  const availableHeight = 600
  const margin = 40 // Margen para que no toque los bordes
  
  const scaleX = (availableWidth - margin) / estructura.value.ancho
  const scaleY = (availableHeight - margin) / estructura.value.alto
  const scale = Math.min(scaleX, scaleY)
  
  return {
    transform: `scale(${scale})`,
    transformOrigin: 'top left',
    marginLeft: `${margin / 2}px`,
    marginTop: `${margin / 2}px`,
  }
}

function getLabelAnchoPos() {
  if (!estructura.value) return {}
  
  const availableWidth = panelPropiedadesVisible.value ? 500 : 800
  const availableHeight = 600
  const margin = 40
  
  const scaleX = (availableWidth - margin) / estructura.value.ancho
  const scaleY = (availableHeight - margin) / estructura.value.alto
  const scale = Math.min(scaleX, scaleY)
  
  const anchoEscalado = estructura.value.ancho * scale
  const leftPos = margin / 2 + anchoEscalado / 2
  
  return {
    top: '5px',
    left: `${leftPos}px`,
    transform: 'translateX(-50%)',
  }
}

function getLabelAltoPos() {
  if (!estructura.value) return {}
  
  const availableWidth = panelPropiedadesVisible.value ? 500 : 800
  const availableHeight = 600
  const margin = 40
  
  const scaleX = (availableWidth - margin) / estructura.value.ancho
  const scaleY = (availableHeight - margin) / estructura.value.alto
  const scale = Math.min(scaleX, scaleY)
  
  const altoEscalado = estructura.value.alto * scale
  const topPos = margin / 2 + altoEscalado / 2
  
  return {
    left: '5px',
    top: `${topPos}px`,
    transform: 'translateY(-50%) rotate(-90deg)',
  }
}

function getEscalaActual() {
  if (!estructura.value) return 1
  
  const availableWidth = panelPropiedadesVisible.value ? 500 : 800
  const availableHeight = 600
  const margin = 40
  
  const scaleX = (availableWidth - margin) / estructura.value.ancho
  const scaleY = (availableHeight - margin) / estructura.value.alto
  return Math.min(scaleX, scaleY)
}

// Watcher para recalcular escala cuando cambia visibilidad del panel
watch(panelPropiedadesVisible, () => {
  nextTick(() => {
    escalaKey.value++
  })
})

</script>

<style scoped>
.armador-complejas {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #f5f5f5;
}

.toolbar-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: white;
  border-bottom: 1px solid #e0e0e0;
}

.toolbar-title {
  display: flex;
  align-items: center;
  font-size: 18px;
  font-weight: 600;
}

.toolbar-actions {
  display: flex;
  gap: 8px;
}

.armador-content {
  flex: 1;
  display: flex;
  gap: 16px;
  padding: 16px;
  overflow: hidden;
}

.panel-tree {
  width: 300px;
  overflow-y: auto;
}

.panel-preview {
  flex: 1;
  overflow: auto;
}

.panel-properties {
  width: 280px;
  overflow-y: auto;
  transition: all 0.3s ease;
}

.preview-content {
  min-height: 600px;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  box-sizing: border-box;
}

.empty-preview {
  text-align: center;
}

.preview-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  overflow: visible;
  position: relative;
}

.dimensiones-principales {
  position: absolute;
  top: 0;
  left: 0;
  pointer-events: none;
  z-index: 100;
}

.label-dimension {
  position: absolute;
  font-size: 18px;
  font-weight: bold;
  color: #fff;
  background: #2196F3;
  padding: 6px 12px;
  border-radius: 4px;
  white-space: nowrap;
  border: 2px solid #1976D2;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
}

.preview-scaler {
  transition: transform 0.2s ease;
  display: inline-block;
}

.actions-bottom {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px;
  background: white;
  border-top: 1px solid #e0e0e0;
}
</style>
