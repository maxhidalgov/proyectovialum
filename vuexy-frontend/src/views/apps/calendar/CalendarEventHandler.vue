<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'
import { requiredValidator } from '@core/utils/validators'

const props = defineProps({
  isDrawerOpen: { type: Boolean, required: true },
  event: { type: null, required: true },
})

const emit = defineEmits([
  'update:isDrawerOpen',
  'addEvent',
  'updateEvent',
  'removeEvent',
])

const refForm = ref()
const event = ref(structuredClone(toRaw(props.event)))

const tiposRecordatorio = [
  { title: '📞 Llamada',     value: 'llamada' },
  { title: '👥 Reunión',     value: 'reunion' },
  { title: '✓ Tarea',       value: 'tarea' },
  { title: '💰 Pago',        value: 'pago' },
  { title: '🔄 Seguimiento', value: 'seguimiento' },
  { title: '🚚 Entrega',     value: 'entrega' },
  { title: '· Otro',         value: 'otro' },
]

const estadosRecordatorio = [
  { title: 'Pendiente',  value: 'pendiente' },
  { title: 'Completado', value: 'completado' },
  { title: 'Cancelado',  value: 'cancelado' },
]

// Solo los recordatorios (o eventos nuevos) son editables
const esRecordatorio = computed(() => {
  const fuente = event.value.extendedProps?.fuente ?? 'recordatorio'

  return fuente === 'recordatorio'
})

const esNuevo = computed(() => !event.value.id)

// Título del header
const tituloHeader = computed(() => {
  if (!esRecordatorio.value) return 'Detalle del evento'

  return esNuevo.value ? 'Nuevo recordatorio' : 'Editar recordatorio'
})

// Campos read-only para eventos no editables (visitas/entregas/ausencias)
const camposDetalle = computed(() => {
  const p = event.value.extendedProps ?? {}
  const campos = []
  const push = (label, valor) => { if (valor) campos.push({ label, valor }) }

  push('Tipo', p.tipo)
  push('Cliente', p.cliente)
  push('Empleado', p.empleado)
  push('Empleados', p.empleados)
  push('Estado', p.estado)
  push('Estado producción', p.estado_produccion)
  push('Motivo', p.motivo)
  push('Notas', p.notas)
  if (p.vencida) push('⚠️', 'Entrega vencida')

  return campos
})

const resetEvent = () => {
  event.value = structuredClone(toRaw(props.event))
  nextTick(() => refForm.value?.resetValidation())
}

watch(() => props.isDrawerOpen, resetEvent)

const startDateTimePickerConfig = computed(() => ({
  enableTime: !event.value.allDay,
  dateFormat: `Y-m-d${event.value.allDay ? '' : ' H:i'}`,
}))

const removeEvent = () => {
  emit('removeEvent', toRaw(event.value))
  emit('update:isDrawerOpen', false)
}

const handleSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (!valid) return

    if (event.value.id)
      emit('updateEvent', toRaw(event.value))
    else
      emit('addEvent', toRaw(event.value))

    emit('update:isDrawerOpen', false)
  })
}

const onCancel = () => {
  emit('update:isDrawerOpen', false)
  nextTick(() => {
    refForm.value?.reset()
    resetEvent()
  })
}

const dialogModelValueUpdate = val => emit('update:isDrawerOpen', val)
</script>

<template>
  <VNavigationDrawer
    data-allow-mismatch
    temporary
    location="end"
    :model-value="props.isDrawerOpen"
    width="370"
    :border="0"
    class="scrollable-content"
    @update:model-value="dialogModelValueUpdate"
  >
    <AppDrawerHeaderSection
      :title="tituloHeader"
      @cancel="$emit('update:isDrawerOpen', false)"
    >
      <template #beforeClose>
        <VBtn
          v-show="esRecordatorio && event.id"
          icon
          variant="text"
          size="small"
          color="default"
          @click="removeEvent"
        >
          <VIcon size="18" icon="tabler-trash" />
        </VBtn>
      </template>
    </AppDrawerHeaderSection>

    <VDivider />

    <PerfectScrollbar :options="{ wheelPropagation: false }">
      <VCard flat>
        <VCardText>
          <!-- ─── Recordatorio: form editable ─── -->
          <VForm
            v-if="esRecordatorio"
            ref="refForm"
            @submit.prevent="handleSubmit"
          >
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="event.title"
                  label="Título"
                  placeholder="Llamar a cliente..."
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol cols="12">
                <AppSelect
                  v-model="event.extendedProps.tipo"
                  label="Tipo"
                  :items="tiposRecordatorio"
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol cols="12">
                <AppDateTimePicker
                  :key="JSON.stringify(startDateTimePickerConfig)"
                  v-model="event.start"
                  label="Fecha y hora"
                  placeholder="Selecciona fecha"
                  :config="startDateTimePickerConfig"
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol cols="12">
                <VSwitch
                  v-model="event.allDay"
                  label="Todo el día"
                />
              </VCol>

              <VCol v-if="!esNuevo" cols="12">
                <AppSelect
                  v-model="event.extendedProps.estado"
                  label="Estado"
                  :items="estadosRecordatorio"
                />
              </VCol>

              <VCol cols="12">
                <AppTextarea
                  v-model="event.extendedProps.descripcion"
                  label="Descripción"
                  placeholder="Detalle del recordatorio"
                />
              </VCol>

              <VCol cols="12">
                <VBtn type="submit" class="me-3">Guardar</VBtn>
                <VBtn variant="outlined" color="secondary" @click="onCancel">Cancelar</VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- ─── Otras fuentes: solo lectura ─── -->
          <div v-else>
            <div class="text-h6 mb-3">{{ event.title }}</div>
            <VList density="compact" class="pa-0">
              <VListItem
                v-for="(campo, i) in camposDetalle"
                :key="i"
                class="px-0"
              >
                <template #prepend>
                  <span class="text-caption text-medium-emphasis me-2" style="min-width:120px">{{ campo.label }}</span>
                </template>
                <VListItemTitle class="text-body-2 text-wrap">{{ campo.valor }}</VListItemTitle>
              </VListItem>
            </VList>
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="mt-4 text-caption"
            >
              Este evento se gestiona desde su propio módulo.
            </VAlert>
          </div>
        </VCardText>
      </VCard>
    </PerfectScrollbar>
  </VNavigationDrawer>
</template>
