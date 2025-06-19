// src/plugins/axiosInstance.js
import axios from 'axios'

const api = axios.create({
  baseURL: 'https://proyectovialum-production.up.railway.app', // Ajusta si usas otra URL
})

export default api