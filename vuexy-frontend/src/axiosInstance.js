// src/plugins/axiosInstance.js
import axios from 'axios'

const api = axios.create({
  baseURL: (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'http://localhost:8000'
    : 'https://proyectovialum-production.up.railway.app'
});

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Interceptor para manejar errores 401 (token expirado)
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      // Token expirado o inválido - cerrar sesión
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      
      // Redirigir al login
      window.location.href = '/#/login'
    }
    return Promise.reject(error)
  }
)

export default api
