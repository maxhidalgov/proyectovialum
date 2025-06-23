// src/plugins/axiosInstance.js
import axios from 'axios'

const api = axios.create({
  baseURL: 'https://proyectovialum-production.up.railway.app', // Ajusta si usas otra URL
})

instance.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
