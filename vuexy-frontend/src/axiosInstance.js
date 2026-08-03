// src/plugins/axiosInstance.js
import axios from 'axios'

const baseURL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
  ? 'http://localhost:8000'
  : 'https://proyectovialum-production.up.railway.app'

const api = axios.create({ baseURL })

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// ── Renovación automática de sesión ─────────────────────────────────────────
// Ante un 401, intentamos renovar el token UNA vez (endpoint /api/refresh, que
// acepta un token vencido dentro del refresh_ttl = 14 días) y reintentamos la
// petición. Solo cerramos sesión si la renovación también falla.
//
// Serializado: si varias peticiones fallan a la vez, solo se hace UN refresh y
// las demás esperan el nuevo token (evita blacklistear el token en carrera, ya
// que JWT_BLACKLIST_GRACE_PERIOD=0).
let isRefreshing = false
let pendingQueue = []

const flushQueue = (newToken) => {
  pendingQueue.forEach(cb => cb(newToken))
  pendingQueue = []
}

const forzarLogout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  window.location.href = '/login'
}

api.interceptors.response.use(
  response => response,
  error => {
    const original = error.config || {}
    const status = error.response?.status
    const url = original.url || ''
    const esAuth = url.includes('/login') || url.includes('/refresh')

    if (status !== 401 || esAuth || original._retry) {
      return Promise.reject(error)
    }

    // Ya hay un refresh en curso → esperar el nuevo token y reintentar
    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        pendingQueue.push(newToken => {
          if (!newToken) return reject(error)
          original._retry = true
          original.headers.Authorization = `Bearer ${newToken}`
          resolve(api(original))
        })
      })
    }

    original._retry = true
    isRefreshing = true

    return new Promise((resolve, reject) => {
      const token = localStorage.getItem('token')

      // axios "limpio" (sin este interceptor) para no recursar
      axios.post(`${baseURL}/api/refresh`, {}, { headers: { Authorization: `Bearer ${token}` } })
        .then(({ data }) => {
          const newToken = data.token
          localStorage.setItem('token', newToken)
          isRefreshing = false
          flushQueue(newToken)

          original.headers.Authorization = `Bearer ${newToken}`
          resolve(api(original))
        })
        .catch(err => {
          isRefreshing = false
          flushQueue(null)
          forzarLogout()
          reject(err)
        })
    })
  }
)

export default api
