// src/plugins/axiosInstance.js
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000', // Ajusta si usas otra URL
})

export default api