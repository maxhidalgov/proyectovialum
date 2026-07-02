import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import timeGridPlugin from '@fullcalendar/timegrid'
import { useConfigStore } from '@core/stores/config'
import { useCalendarStore } from '@/views/apps/calendar/useCalendarStore'

export const blankEvent = {
  title: '',
  start: '',
  end: '',
  allDay: false,
  extendedProps: {
    fuente: 'recordatorio',
    tipo: 'tarea',
    descripcion: '',
    estado: 'pendiente',
    editable: true,
  },
}

// Color pastel por fuente (estilo Vuexy: bg-light-{color} text-{color})
const calendarsColor = {
  recordatorio: 'success',
  visita: 'info',
  entrega: 'warning',
  ausencia: 'secondary',
}

export const useCalendar = (event, isEventHandlerSidebarActive, isLeftSidebarOpen) => {
  const configStore = useConfigStore()
  const store = useCalendarStore()

  const refCalendar = ref()
  const calendarApi = ref(null)

  // Extrae datos del evento de FullCalendar a nuestro shape editable
  const extractEventDataFromEventApi = eventApi => {
    const { id, title, start, end, allDay, extendedProps } = eventApi

    return {
      id,
      // El backend antepone un emoji al título; lo quitamos para editar
      title: (title || '').replace(/^\p{Emoji_Presentation}️?\s*/u, '').trim() || title,
      start,
      end,
      allDay,
      extendedProps: { ...extendedProps },
    }
  }

  // Fetch: pasa los eventos del backend, quitando los colores hex para que
  // manden las clases pastel de eventClassNames
  const fetchEvents = (info, successCallback, failureCallback) => {
    store.fetchEvents(info)
      .then(events => {
        successCallback(events.map(({ backgroundColor, borderColor, textColor, ...e }) => e))
      })
      .catch(e => {
        console.error('Error al cargar eventos del calendario', e)
        failureCallback?.(e)
      })
  }

  const refetchEvents = () => {
    calendarApi.value?.refetchEvents()
  }

  watch(() => store.selectedCalendars, refetchEvents)

  const addEvent = _event => {
    store.addEvent(_event).then(refetchEvents)
  }

  const updateEvent = _event => {
    store.updateEvent(_event).then(refetchEvents)
  }

  const removeEvent = _event => {
    store.removeEvent(_event).then(refetchEvents)
  }

  const calendarOptions = {
    plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin, listPlugin],
    initialView: 'dayGridMonth',
    locale: 'es',
    firstDay: 1,
    headerToolbar: {
      start: 'drawerToggler,prev,next title',
      end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    buttonText: {
      today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día', list: 'Lista',
    },
    events: fetchEvents,
    forceEventDuration: false,
    editable: false,
    dayMaxEvents: 3,
    navLinks: true,
    height: 'auto',

    eventClassNames({ event: calendarEvent }) {
      const p = calendarEvent.extendedProps
      let color = calendarsColor[p.fuente] || 'primary'
      if (p.fuente === 'entrega' && p.vencida) color = 'error'

      return [`bg-light-${color} text-${color}`]
    },

    eventClick({ event: clickedEvent, jsEvent }) {
      jsEvent.preventDefault()
      event.value = extractEventDataFromEventApi(clickedEvent)
      isEventHandlerSidebarActive.value = true
    },

    dateClick(info) {
      event.value = {
        ...structuredClone(blankEvent),
        start: info.date,
        allDay: info.allDay,
      }
      isEventHandlerSidebarActive.value = true
    },

    customButtons: {
      drawerToggler: {
        text: 'calendarDrawerToggler',
        click() {
          isLeftSidebarOpen.value = true
        },
      },
    },
  }

  onMounted(() => {
    nextTick(() => {
      if (refCalendar.value)
        calendarApi.value = refCalendar.value.getApi()
    })
  })

  const jumpToDate = currentDate => {
    calendarApi.value?.gotoDate(new Date(currentDate))
  }

  watch(() => configStore.isAppRTL, val => {
    calendarApi.value?.setOption('direction', val ? 'rtl' : 'ltr')
  }, { immediate: true })

  return {
    refCalendar,
    calendarOptions,
    refetchEvents,
    fetchEvents,
    addEvent,
    updateEvent,
    removeEvent,
    jumpToDate,
  }
}
