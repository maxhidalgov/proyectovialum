<script setup>
import FullCalendar from '@fullcalendar/vue3'
import { useDisplay } from 'vuetify'
import { blankEvent, useCalendar } from '@/views/apps/calendar/useCalendar'
import { useCalendarStore } from '@/views/apps/calendar/useCalendarStore'
import CalendarEventHandler from '@/views/apps/calendar/CalendarEventHandler.vue'

const store = useCalendarStore()

// Evento en edición + estado del drawer
const event = ref(structuredClone(blankEvent))
const isEventHandlerSidebarActive = ref(false)

watch(isEventHandlerSidebarActive, val => {
  if (!val) event.value = structuredClone(blankEvent)
})

// Sidebar responsivo (reemplaza useResponsiveLeftSidebar del template)
const { mdAndUp } = useDisplay()
const isLeftSidebarOpen = ref(true)
watch(mdAndUp, v => { isLeftSidebarOpen.value = v }, { immediate: true })

const {
  refCalendar, calendarOptions,
  addEvent, updateEvent, removeEvent, jumpToDate,
} = useCalendar(event, isEventHandlerSidebarActive, isLeftSidebarOpen)

// Check-all de filtros
const checkAll = computed({
  get: () => store.selectedCalendars.length === store.availableCalendars.length,
  set: val => {
    store.selectedCalendars = val
      ? store.availableCalendars.map(i => i.value)
      : []
  },
})

const jumpToDateFn = date => jumpToDate(date)
</script>

<template>
  <div>
    <VCard>
      <!-- z-index: 0 permite superponer el nav vertical sobre el calendario -->
      <VLayout style="z-index: 0;">
        <!-- 👉 Drawer izquierdo -->
        <VNavigationDrawer
          v-model="isLeftSidebarOpen"
          data-allow-mismatch
          width="292"
          absolute
          touchless
          location="start"
          class="calendar-add-event-drawer"
          :temporary="$vuetify.display.mdAndDown"
        >
          <div style="margin: 1.5rem;">
            <VBtn
              block
              prepend-icon="tabler-plus"
              @click="isEventHandlerSidebarActive = true"
            >
              Nuevo recordatorio
            </VBtn>
          </div>

          <VDivider />

          <div class="d-flex align-center justify-center pa-2">
            <AppDateTimePicker
              id="calendar-date-picker"
              :model-value="new Date().toJSON().slice(0, 10)"
              :config="{ inline: true }"
              class="calendar-date-picker"
              @update:model-value="jumpToDateFn"
            />
          </div>

          <VDivider />

          <div class="pa-6">
            <h6 class="text-lg font-weight-medium mb-4">
              Filtros
            </h6>

            <div class="d-flex flex-column calendars-checkbox">
              <VCheckbox
                v-model="checkAll"
                label="Ver todo"
              />
              <VCheckbox
                v-for="calendar in store.availableCalendars"
                :key="calendar.value"
                v-model="store.selectedCalendars"
                :value="calendar.value"
                :color="calendar.color"
                :label="calendar.label"
              />
            </div>
          </div>
        </VNavigationDrawer>

        <VMain>
          <VCard flat>
            <FullCalendar
              ref="refCalendar"
              :options="calendarOptions"
            />
          </VCard>
        </VMain>
      </VLayout>
    </VCard>

    <CalendarEventHandler
      v-model:is-drawer-open="isEventHandlerSidebarActive"
      :event="event"
      @add-event="addEvent"
      @update-event="updateEvent"
      @remove-event="removeEvent"
    />
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/libs/full-calendar";

.calendars-checkbox {
  .v-label {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    opacity: var(--v-high-emphasis-opacity);
  }
}

.calendar-add-event-drawer {
  &.v-navigation-drawer:not(.v-navigation-drawer--temporary) {
    border-end-start-radius: 0.375rem;
    border-start-start-radius: 0.375rem;
  }

  &.v-navigation-drawer--temporary:not(.v-navigation-drawer--active) {
    transform: translateX(-110%) !important;
  }
}

.calendar-date-picker {
  display: none;

  + .flatpickr-input + .flatpickr-calendar.inline {
    border: none;
    box-shadow: none;

    .flatpickr-months {
      border-block-end: none;
    }
  }

  & ~ .flatpickr-calendar .flatpickr-weekdays {
    margin-block: 0 4px;
  }
}

@media screen and (max-width: 1279px) {
  .calendar-add-event-drawer {
    border-width: 0;
  }
}
</style>

<style lang="scss" scoped>
.v-layout {
  overflow: visible !important;

  .v-card {
    overflow: visible;
  }
}
</style>
