<script setup>
import { ref, watch } from 'vue'

// Campo de dinero: muestra $10.000 (miles) pero al enfocar deja editar el número
// crudo, así el cursor no salta al reformatear en cada tecla.
const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  prefix: { type: String, default: '$' },
})
const emit = defineEmits(['update:modelValue'])

const miles = n => (Number(n) || 0).toLocaleString('es-CL', { maximumFractionDigits: 2 })
const parse = v => {
  const s = String(v).replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '')
  return s === '' ? 0 : parseFloat(s)
}

const display = ref(miles(props.modelValue))
const focused = ref(false)

watch(() => props.modelValue, v => { if (!focused.value) display.value = miles(v) })

function onFocus() { focused.value = true; display.value = props.modelValue ? String(props.modelValue) : '' }
function onInput(v) { display.value = v; emit('update:modelValue', parse(v)) }
function onBlur() { focused.value = false; display.value = miles(props.modelValue) }
</script>

<template>
  <VTextField
    :model-value="display" :prefix="prefix"
    density="compact" hide-details reverse inputmode="numeric"
    @update:model-value="onInput" @focus="onFocus" @blur="onBlur"
  />
</template>
