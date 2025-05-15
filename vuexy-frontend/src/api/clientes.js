import axios from 'axios'
import api from '@/axiosInstance'

export async function fetchBsaleClientes() {
    try {
      const response = await api.get('/api/bsale-clientes')
      return response.data // Asegúrate que sea un array de objetos válidos
    } catch (error) {
      console.error('Error al obtener clientes de Bsale:', error)
      return []
    }
}

    export async function importarCliente(cliente) {
        try {
          const response = await api.post('/api/clientes', cliente)
          return response.data
        } catch (error) {
          console.error('Error al guardar cliente:', error)
          throw error
        }
    }

    export async function importarTodosClientes() {
      try {
        const res = await api.post('/api/clientes/importar-todos')
        return res.data
      } catch (err) {
        console.error('❌ Error al importar todos los clientes:', err)
        throw err
      }
    }

    export const crearClienteBsale = async (clienteData) => {
        const res = await api.post(`${import.meta.env.VITE_API_URL}/bsale-clientes/crear`, clienteData)
        return res.data
    }
    
      
  