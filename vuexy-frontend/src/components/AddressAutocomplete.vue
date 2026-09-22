<!--
  Campo de dirección con autocompletado gratuito (OpenStreetMap vía Photon).
  Sin API key. Al elegir una sugerencia emite 'selected' con
  { direccion, comuna, ciudad, region, lat, lng } para rellenar el formulario.
  Se puede escribir a mano igual (para direcciones que el mapa no conoce);
  en ese caso no quedan coordenadas hasta ajustar el pin en el mapa (Fase 2).
-->
<template>
  <div style="position: relative;">
    <VTextField
      :model-value="modelValue"
      :label="label"
      :variant="variant"
      :density="density"
      prepend-inner-icon="mdi-map-marker-outline"
      :loading="loading"
      autocomplete="off"
      :hint="hint"
      persistent-hint
      @update:model-value="onInput"
      @focus="showList = suggestions.length > 0"
      @blur="cerrarConDelay"
    />
    <VCard v-if="showList && suggestions.length" class="addr-list" elevation="6">
      <VList density="compact" class="py-0">
        <VListItem
          v-for="(s, i) in suggestions"
          :key="i"
          @mousedown.prevent="elegir(s)"
        >
          <template #prepend>
            <VIcon size="18" color="primary" icon="mdi-map-marker" />
          </template>
          <VListItemTitle class="text-body-2">{{ s.direccion }}</VListItemTitle>
          <VListItemSubtitle class="text-caption">
            {{ [s.ciudad, s.region].filter(Boolean).join(' · ') }}
          </VListItemSubtitle>
        </VListItem>
      </VList>
    </VCard>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label:      { type: String, default: 'Dirección' },
  density:    { type: String, default: 'compact' },
  variant:    { type: String, default: 'outlined' },
})
const emit = defineEmits(['update:modelValue', 'selected'])

const suggestions = ref([])
const loading = ref(false)
const showList = ref(false)
let timer = null

// Sesgo hacia la zona de Vialum (Los Ángeles, Biobío)
const BIAS = { lat: -37.47, lon: -72.35 }

function onInput(val) {
  emit('update:modelValue', val)
  if (timer) clearTimeout(timer)
  if (!val || val.trim().length < 4) { suggestions.value = []; showList.value = false; return }
  timer = setTimeout(() => buscar(val.trim()), 350)
}

async function buscar(q) {
  loading.value = true
  try {
    const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&lang=default&limit=6&lat=${BIAS.lat}&lon=${BIAS.lon}`
    const res = await fetch(url)
    const data = await res.json()
    suggestions.value = (data.features || [])
      .filter(f => (f.properties?.countrycode || '').toUpperCase() === 'CL')
      .map(f => {
        const p = f.properties || {}
        return {
          direccion: formatDireccion(p),
          comuna:    p.city || p.district || p.locality || p.county || '',
          ciudad:    p.city || p.county || '',
          region:    p.state || '',
          lat:       f.geometry?.coordinates?.[1] ?? null,
          lng:       f.geometry?.coordinates?.[0] ?? null,
        }
      })
      .filter(s => s.direccion)
    showList.value = suggestions.value.length > 0
  } catch (e) {
    suggestions.value = []
    showList.value = false
  } finally {
    loading.value = false
  }
}

function formatDireccion(p) {
  const calle = p.street
    ? (p.housenumber ? `${p.street} ${p.housenumber}` : p.street)
    : ''
  // Preferir "calle número"; si no hay calle, usar el nombre del lugar
  return calle || p.name || ''
}

function elegir(s) {
  emit('update:modelValue', s.direccion)
  emit('selected', s)
  showList.value = false
}

function cerrarConDelay() {
  // Delay para que el mousedown de la lista alcance a dispararse
  setTimeout(() => { showList.value = false }, 180)
}

const hint = 'Escribe y elige una dirección real, o déjala a mano'
</script>

<style scoped>
.addr-list {
  position: absolute;
  z-index: 1000;
  inline-size: 100%;
  max-block-size: 280px;
  overflow-y: auto;
  margin-top: 2px;
}
</style>
