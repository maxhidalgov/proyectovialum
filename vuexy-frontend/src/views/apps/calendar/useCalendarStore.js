import { defineStore } from 'pinia'
import axios from '@/axiosInstance'

/**
 * Store del calendario. Las "fuentes" hacen de "calendars" filtrables.
 * Eventos vienen unificados desde /api/calendario/eventos.
 * Los recordatorios son CRUD (/api/recordatorios); las demás fuentes son read-only.
 */
export const useCalendarStore = defineStore('calendar', {
  state: () => ({
    availableCalendars: [
      { label: 'Recordatorios', value: 'recordatorio', color: 'success' },
      { label: 'Visitas',       value: 'visita',       color: 'info' },
      { label: 'Entregas',      value: 'entrega',      color: 'warning' },
      { label: 'Ausencias',     value: 'ausencia',     color: 'secondary' },
    ],
    selectedCalendars: ['recordatorio', 'visita', 'entrega', 'ausencia'],
  }),
  actions: {
    async fetchEvents(info) {
      const params = {
        fuentes: this.selectedCalendars.join(','),
      }
      if (info?.startStr) params.start = info.startStr.slice(0, 10)
      if (info?.endStr)   params.end = info.endStr.slice(0, 10)

      const { data } = await axios.get('/api/calendario/eventos', { params })

      return data
    },

    async addEvent(event) {
      const { data } = await axios.post('/api/recordatorios', this.toRecordatorioPayload(event))

      return data
    },

    async updateEvent(event) {
      const id = this.recordatorioId(event)
      const { data } = await axios.patch(`/api/recordatorios/${id}`, this.toRecordatorioPayload(event))

      return data
    },

    async removeEvent(event) {
      const id = this.recordatorioId(event)
      await axios.delete(`/api/recordatorios/${id}`)
    },

    // ── Helpers ──────────────────────────────────────────────
    recordatorioId(event) {
      // event.id puede ser numérico (ya extraído) o "rec_123"
      return event?.extendedProps?.recordatorio_id
        ?? String(event.id).replace(/^rec_/, '')
    },

    toRecordatorioPayload(event) {
      const start = event.start instanceof Date
        ? event.start
        : new Date(event.start)

      const fecha = start.toISOString().slice(0, 10)
      const hora = event.allDay ? null : start.toTimeString().slice(0, 5)

      return {
        titulo: event.title,
        tipo: event.extendedProps?.tipo || 'tarea',
        fecha,
        hora,
        descripcion: event.extendedProps?.descripcion || null,
        ...(event.extendedProps?.estado ? { estado: event.extendedProps.estado } : {}),
      }
    },
  },
})
