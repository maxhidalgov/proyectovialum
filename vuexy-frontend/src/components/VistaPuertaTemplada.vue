<template>
  <v-stage ref="stageRef" :config="{ width: 400, height: 400 }">
    <v-layer>
      <!-- Vidrio templado (sin marco, va de borde a borde) -->
      <v-rect :config="vidrioConfig" />

      <!-- Bisagra Superior (izquierda, arriba) -->
      <v-rect :config="bisagraSupConfig" />
      <!-- Bisagra Inferior (izquierda, abajo) -->
      <v-rect :config="bisagraInfConfig" />

      <!-- Pivote (esquina superior izquierda) -->
      <v-rect :config="pivoteConfig" />

      <!-- Quicio Hidráulico (piso, izquierda) -->
      <v-rect :config="quicioConfig" />

      <!-- Cerradura (piso, derecha) -->
      <v-rect :config="cerraduraConfig" />

      <!-- Tirador Tipo H (derecha, centrado) -->
      <v-rect :config="tiradorBarraConfig" />
      <v-rect :config="tiradorTopConfig" />
      <v-rect :config="tiradorBotConfig" />

      <!-- Etiquetas dimensión -->
      <v-text :config="labelAncho" />
      <v-text :config="labelAlto" />
    </v-layer>
  </v-stage>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  ancho:     { type: Number, required: true },
  alto:      { type: Number, required: true },
  tiradorMm: { type: Number, default: 1000 },
})

const stageRef = ref(null)

const OFFSET = 40  // margen para etiquetas

const esc = computed(() => 320 / Math.max(props.ancho || 1, props.alto || 1))
const SW  = computed(() => (props.ancho || 1) * esc.value)
const SH  = computed(() => (props.alto  || 1) * esc.value)

// Origen del vidrio en pantalla
const GX = computed(() => OFFSET)
const GY = computed(() => OFFSET)

// Colores
const GLASS    = '#cce8f4'
const HARDWARE = '#9E9E9E'
const SHADOW   = '#616161'

// ── Vidrio ──────────────────────────────────────────────────
const vidrioConfig = computed(() => ({
  x: GX.value, y: GY.value,
  width: SW.value, height: SH.value,
  fill: GLASS, stroke: '#78909C', strokeWidth: 1.5,
}))

// ── Bisagras ──────────────────────────────────────────────────
// BW = grosor (profundidad), BH = longitud del cuerpo de la bisagra
const BW = computed(() => Math.max(5, SW.value * 0.045))
const BH = computed(() => Math.max(12, SH.value * 0.07))

// Bisagra Superior: borde superior del vidrio, justo a la derecha del pivote
const bisagraSupConfig = computed(() => ({
  x: GX.value + PV.value * 0.55,
  y: GY.value - BW.value * 0.4,
  width: BH.value, height: BW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.8, cornerRadius: 1,
}))

// Bisagra Inferior: borde inferior del vidrio, junto al quicio
const bisagraInfConfig = computed(() => ({
  x: GX.value + SW.value * 0.03,
  y: GY.value + SH.value - BW.value * 0.6,
  width: BH.value, height: BW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.8, cornerRadius: 1,
}))

// ── Pivote (esquina superior izquierda) ──────────────────────
const PV = computed(() => Math.max(7, SW.value * 0.06))

const pivoteConfig = computed(() => ({
  x: GX.value - PV.value * 0.4,
  y: GY.value - PV.value * 0.4,
  width: PV.value, height: PV.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.8, cornerRadius: 2,
}))

// ── Quicio Hidráulico (piso, izquierda) ──────────────────────
const QW = computed(() => Math.max(10, SW.value * 0.09))
const QH = computed(() => Math.max(6,  SH.value * 0.03))

const quicioConfig = computed(() => ({
  x: GX.value + SW.value * 0.04,
  y: GY.value + SH.value - QH.value * 0.5,
  width: QW.value, height: QH.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.8, cornerRadius: 1,
}))

// ── Cerradura (piso, derecha) ────────────────────────────────
const CW = computed(() => Math.max(8, SW.value * 0.07))
const CH = computed(() => Math.max(6, SH.value * 0.03))

const cerraduraConfig = computed(() => ({
  x: GX.value + SW.value * 0.72,
  y: GY.value + SH.value - CH.value * 0.5,
  width: CW.value, height: CH.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.8, cornerRadius: 1,
}))

// ── Tirador Tipo H (derecha, centrado verticalmente) ─────────
// Longitud del tirador en pantalla, proporcional al alto real de la puerta
const tiradorPx = computed(() => (props.tiradorMm / (props.alto || 1)) * SH.value)
const TW = computed(() => Math.max(3, SW.value * 0.025))    // grosor barra
const TH = computed(() => Math.max(8, SW.value * 0.06))     // largo travesaño H
const TX = computed(() => GX.value + SW.value * 0.82)        // posición X

const tiradorCenterY = computed(() => GY.value + SH.value / 2)

const tiradorBarraConfig = computed(() => ({
  x: TX.value,
  y: tiradorCenterY.value - tiradorPx.value / 2,
  width: TW.value, height: tiradorPx.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.5, cornerRadius: 1,
}))

const tiradorTopConfig = computed(() => ({
  x: TX.value - TH.value / 2 + TW.value / 2,
  y: tiradorCenterY.value - tiradorPx.value / 2,
  width: TH.value, height: TW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.5, cornerRadius: 1,
}))

const tiradorBotConfig = computed(() => ({
  x: TX.value - TH.value / 2 + TW.value / 2,
  y: tiradorCenterY.value + tiradorPx.value / 2 - TW.value,
  width: TH.value, height: TW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.5, cornerRadius: 1,
}))

// ── Etiquetas ────────────────────────────────────────────────
const labelAncho = computed(() => ({
  x: GX.value + SW.value / 2 - 25,
  y: GY.value + SH.value + 12,
  text: `${props.ancho}mm`, fontSize: 13, fill: 'black',
}))

const labelAlto = computed(() => ({
  x: GX.value - 28,
  y: GY.value + SH.value / 2,
  text: `${props.alto}mm`, fontSize: 13, fill: 'black', rotation: -90,
}))

defineExpose({
  exportarImagen: () => {
    try { return stageRef.value?.getStage()?.toDataURL({ pixelRatio: 2 }) ?? null } catch { return null }
  },
})
</script>
