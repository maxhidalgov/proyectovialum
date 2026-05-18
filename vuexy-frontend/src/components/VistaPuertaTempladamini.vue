<template>
  <v-group :config="{ x: props.config?.x ?? 0, y: props.config?.y ?? 0 }">
    <!-- Vidrio templado -->
    <v-rect :config="vidrioConfig" />
    <!-- Bisagra Superior (borde superior, derecha del pivote) -->
    <v-rect :config="bisagraSupConfig" />
    <!-- Bisagra Inferior (borde inferior, junto al quicio) -->
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
  </v-group>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  ancho:     { type: Number, required: true },
  alto:      { type: Number, required: true },
  escala:    { type: Number, default: 1 },
  config:    { type: Object, default: () => ({ x: 0, y: 0 }) },
  tiradorMm: { type: Number, default: 1000 },
  showHeightLabel: { type: Boolean, default: true },
})

const SW = computed(() => props.ancho * props.escala)
const SH = computed(() => props.alto  * props.escala)

const GLASS    = '#cce8f4'
const HARDWARE = '#9E9E9E'
const SHADOW   = '#616161'

// ── Vidrio ───────────────────────────────────────────────────
const vidrioConfig = computed(() => ({
  x: 0, y: 0,
  width: SW.value, height: SH.value,
  fill: GLASS, stroke: '#78909C', strokeWidth: 1,
}))

// ── Pivote (esquina superior izquierda) ──────────────────────
const PV = computed(() => Math.max(4, SW.value * 0.06))

const pivoteConfig = computed(() => ({
  x: -PV.value * 0.4, y: -PV.value * 0.4,
  width: PV.value, height: PV.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.6, cornerRadius: 1,
}))

// ── Bisagras ─────────────────────────────────────────────────
const BW = computed(() => Math.max(3, SW.value * 0.045))
const BH = computed(() => Math.max(8, SH.value * 0.07))

// Bisagra Superior: borde superior del vidrio, justo a la derecha del pivote
const bisagraSupConfig = computed(() => ({
  x: PV.value * 0.55,
  y: -BW.value * 0.4,
  width: BH.value, height: BW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.6, cornerRadius: 1,
}))

// ── Quicio Hidráulico (piso, izquierda) ──────────────────────
const QW = computed(() => Math.max(6, SW.value * 0.09))
const QH = computed(() => Math.max(3, SH.value * 0.03))

const quicioConfig = computed(() => ({
  x: SW.value * 0.04, y: SH.value - QH.value * 0.5,
  width: QW.value, height: QH.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.6, cornerRadius: 1,
}))

// Bisagra Inferior: borde inferior del vidrio, junto al quicio
const bisagraInfConfig = computed(() => ({
  x: SW.value * 0.03,
  y: SH.value - BW.value * 0.6,
  width: BH.value, height: BW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.6, cornerRadius: 1,
}))

// ── Cerradura (piso, derecha) ────────────────────────────────
const CW = computed(() => Math.max(5, SW.value * 0.07))
const CH = computed(() => Math.max(3, SH.value * 0.03))

const cerraduraConfig = computed(() => ({
  x: SW.value * 0.72, y: SH.value - CH.value * 0.5,
  width: CW.value, height: CH.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.6, cornerRadius: 1,
}))

// ── Tirador Tipo H (derecha, centrado verticalmente) ─────────
const tiradorPx = computed(() => (props.tiradorMm / (props.alto || 1)) * SH.value)
const TW = computed(() => Math.max(2, SW.value * 0.025))
const TH = computed(() => Math.max(5, SW.value * 0.06))
const TX = computed(() => SW.value * 0.82)
const tiradorCY = computed(() => SH.value / 2)

const tiradorBarraConfig = computed(() => ({
  x: TX.value, y: tiradorCY.value - tiradorPx.value / 2,
  width: TW.value, height: tiradorPx.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.4, cornerRadius: 1,
}))
const tiradorTopConfig = computed(() => ({
  x: TX.value - TH.value / 2 + TW.value / 2,
  y: tiradorCY.value - tiradorPx.value / 2,
  width: TH.value, height: TW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.4, cornerRadius: 1,
}))
const tiradorBotConfig = computed(() => ({
  x: TX.value - TH.value / 2 + TW.value / 2,
  y: tiradorCY.value + tiradorPx.value / 2 - TW.value,
  width: TH.value, height: TW.value,
  fill: HARDWARE, stroke: SHADOW, strokeWidth: 0.4, cornerRadius: 1,
}))
</script>
