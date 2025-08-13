<template>
  <component
    :is="getComponenteVentana(tipo)"
    :ancho="ancho"
    :alto="alto"
    :color-marco="colorMarco"
    :escala="escala"
    :config="{ x, y, scaleY: 1 }"
    :hojas-moviles="hojasMoviles"
    :hoja-movil-seleccionada="hojaMovilSeleccionada"
    :orden-hoja1-al-frente="ordenHoja1AlFrente"
    :lado-apertura="ladoApertura"
    :direccion-apertura="direccionApertura"
  />
</template>

<script setup>
import VistaVentanaFijaS60Mini from './VistaVentanaFijaS60mini.vue'
import VistaVentanaProyectanteS60Mini from './VistaVentanaProyectanteS60mini.vue'
import VistaVentanaCorrederamini from './VistaVentanaCorrederamini.vue'
import VistaVentanaAbatirS60mini from './VistaVentanaAbatirS60mini.vue'
import VistaPuertaS60mini from './VistaPuertaS60mini.vue'   // ← nuevo

const props = defineProps({
  x: Number,
  y: Number,
  ancho: Number,
  alto: Number,
  escala: Number,
  colorMarco: [String, Object],
  // puede venir como id, string o { id }
  tipo: [Number, String, Object],
  hojasMoviles: { type: Number, default: null },
  hojaMovilSeleccionada: { type: Number, default: null },
  ordenHoja1AlFrente: { type: Boolean, default: null },
  ladoApertura: { type: String, default: 'izquierda' },
  direccionApertura: { type: String, default: 'interior' }
})

// Normaliza el “tipo” a un id numérico conocido
function normalizeTipoId(t) {
  if (t == null) return null
  if (typeof t === 'number') return t
  if (typeof t === 'string') {
    const n = parseInt(t, 10)
    if (!Number.isNaN(n)) return n
    const s = t.toLowerCase()
    if (s.includes('abat')) return 49
    if (s.includes('proy')) return 45   // ← usa 45 como canónico
    if (s.includes('corr')) return 3
    if (s.includes('fija')) return 44
    return null
  }
  if (typeof t === 'object') {
    if ('id' in t) return normalizeTipoId(t.id)
    if ('tipo' in t) return normalizeTipoId(t.tipo)
  }
  return null
}

// Devuelve el componente mini adecuado (fallback a Fija)
function getComponenteVentana(tipoIn) {
  const id = normalizeTipoId(tipoIn)
  switch (id) {
    case 49: return VistaVentanaAbatirS60mini         // Abatible S60
    case 46:                                         // ← acepta 46
    case 45: return VistaVentanaProyectanteS60Mini    // ← y 45
    case 3:  return VistaVentanaCorrederamini         // Corredera
    case 50: return VistaPuertaS60mini         // ← Puerta S60 mini
    case 44:
    case 2:  return VistaVentanaFijaS60Mini           // Fija
    default: return VistaVentanaFijaS60Mini
  }
}
</script>
