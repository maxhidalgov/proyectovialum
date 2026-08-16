<script setup>
import { ref, watch } from 'vue'

// Campo de dinero: muestra $10.000 (signo adelante + miles) alineado a la derecha.
// Al enfocar deja editar el número crudo, así el cursor no salta al reformatear.
const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  prefix: { type: String, default: '$' },
  variant: { type: String, default: undefined },
})
const emit = defineEmits(['update:modelValue'])

const fmt = n => (props.prefix || '') + (Number(n) || 0).toLocaleString('es-CL', { maximumFractionDigits: 2 })
const parse = v => {
  const s = String(v).replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '')
  return s === '' ? 0 : parseFloat(s)
}

const display = ref(fmt(props.modelValue))
const focused = ref(false)

watch(() => props.modelValue, v => { if (!focused.value) display.value = fmt(v) })

function onFocus() { focused.value = true; display.value = props.modelValue ? String(props.modelValue) : '' }
function onInput(v) { display.value = v; emit('update:modelValue', parse(v)) }
function onBlur() { focused.value = false; display.value = fmt(props.modelValue) }
</script>

<template>
  <VTextField
    :model-value="display" :variant="variant"
    density="compact" hide-details inputmode="numeric"
    class="money-field"
    @update:model-value="onInput" @focus="onFocus" @blur="onBlur"
  />
</template>

<style scoped>
.money-field :deep(input) { text-align: end; }
</style>
