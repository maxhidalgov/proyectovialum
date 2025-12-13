<template>
  <div class="nodo-estructura">
    <!-- Nodo actual -->
    <div 
      class="nodo-item"
      :class="{ 'seleccionado': seleccionado, 'con-tipo': nodo.tipoVentanaId }"
      :style="{ paddingLeft: `${nivel * 16}px` }"
      @click.stop="$emit('seleccionar', nodo)"
    >
      <v-icon size="small" class="mr-2">
        {{ getIcono() }}
      </v-icon>
      
      <span class="nodo-nombre">
        {{ getNombre() }}
      </span>
      
      <span class="nodo-dimensiones">
        {{ nodo.ancho }} × {{ nodo.alto }}
      </span>

      <!-- Acciones rápidas -->
      <div class="nodo-acciones" @click.stop>
        <v-btn
          v-if="!nodo.tipoVentanaId && !nodo.hijos"
          icon
          size="x-small"
          variant="text"
          @click.stop="$emit('dividir', nodo)"
        >
          <v-icon size="small">mdi-call-split</v-icon>
          <v-tooltip activator="parent" location="top">Dividir</v-tooltip>
        </v-btn>
        
        <v-btn
          v-if="!nodo.tipoVentanaId && !nodo.hijos"
          icon
          size="x-small"
          variant="text"
          @click.stop="$emit('asignar-tipo', nodo)"
        >
          <v-icon size="small">mdi-window-closed-variant</v-icon>
          <v-tooltip activator="parent" location="top">Asignar Tipo</v-tooltip>
        </v-btn>
      </div>
    </div>

    <!-- Hijos recursivos -->
    <div v-if="nodo.hijos" class="nodo-hijos">
      <!-- Divisor -->
      <div 
        class="divisor-info"
        :style="{ paddingLeft: `${(nivel + 1) * 16}px` }"
      >
        <v-icon size="x-small" class="mr-1">mdi-minus</v-icon>
        <span class="text-caption">
          Divisor {{ nodo.hijos.orientacion === 'horizontal' ? 'Horizontal' : 'Vertical' }}
          @ {{ nodo.hijos.divisor.posicion }}mm
        </span>
      </div>

      <!-- Nodo 1 -->
      <NodoEstructura
        :nodo="nodo.hijos.nodo1"
        :nivel="nivel + 1"
        :seleccionado="seleccionado"
        @seleccionar="$emit('seleccionar', $event)"
        @dividir="$emit('dividir', $event)"
        @asignar-tipo="$emit('asignar-tipo', $event)"
        @eliminar="$emit('eliminar', $event)"
      />

      <!-- Nodo 2 -->
      <NodoEstructura
        :nodo="nodo.hijos.nodo2"
        :nivel="nivel + 1"
        :seleccionado="seleccionado"
        @seleccionar="$emit('seleccionar', $event)"
        @dividir="$emit('dividir', $event)"
        @asignar-tipo="$emit('asignar-tipo', $event)"
        @eliminar="$emit('eliminar', $event)"
      />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  nodo: { type: Object, required: true },
  nivel: { type: Number, default: 0 },
  seleccionado: { type: Boolean, default: false },
})

defineEmits(['seleccionar', 'dividir', 'asignar-tipo', 'eliminar'])

function getIcono() {
  if (props.nodo.tipo === 'marco') return 'mdi-border-all'
  if (props.nodo.tipoVentanaId) return 'mdi-window-closed-variant'
  if (props.nodo.hijos) return 'mdi-view-split-vertical'
  return 'mdi-square-outline'
}

function getNombre() {
  if (props.nodo.tipo === 'marco') return 'Marco Principal'
  if (props.nodo.tipoVentanaId) return `Ventana Tipo ${props.nodo.tipoVentanaId}`
  return 'Espacio'
}
</script>

<style scoped>
.nodo-estructura {
  margin-bottom: 4px;
}

.nodo-item {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  gap: 8px;
}

.nodo-item:hover {
  background: rgba(33, 150, 243, 0.08);
}

.nodo-item.seleccionado {
  background: rgba(33, 150, 243, 0.15);
  border-left: 3px solid #2196F3;
}

.nodo-item.con-tipo {
  background: rgba(76, 175, 80, 0.08);
}

.nodo-item.con-tipo.seleccionado {
  background: rgba(76, 175, 80, 0.15);
  border-left-color: #4CAF50;
}

.nodo-nombre {
  flex: 1;
  font-size: 13px;
  font-weight: 500;
}

.nodo-dimensiones {
  font-size: 11px;
  color: #666;
  font-family: monospace;
}

.nodo-acciones {
  display: flex;
  gap: 2px;
  opacity: 0;
  transition: opacity 0.2s;
}

.nodo-item:hover .nodo-acciones {
  opacity: 1;
}

.nodo-hijos {
  margin-left: 12px;
  border-left: 2px solid #e0e0e0;
}

.divisor-info {
  padding: 4px 12px;
  color: #666;
  font-size: 11px;
  display: flex;
  align-items: center;
}
</style>
