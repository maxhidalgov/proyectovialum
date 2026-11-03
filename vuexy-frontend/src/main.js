import { createApp } from 'vue'
import App from '@/App.vue'
import api from '@/axiosInstance'
import axios from 'axios'
import VueKonva from 'vue-konva'

// Estilos
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import InlineSvg from 'vue-inline-svg'


// Plugins
import { registerPlugins } from '@core/utils/plugins'
import { router } from '@/plugins/1.router'

// ── Recuperación ante chunks viejos tras un deploy ──────────────────────────
// Si la app estuvo abierta desde antes de un deploy, los chunks lazy cambian de
// hash y los viejos dejan de existir → al abrir un módulo falla la carga
// ("Failed to load module script" / "Cannot read properties of null"). En ese
// caso recargamos una vez para tomar el index.html nuevo. Guard anti-loop: máximo
// una recarga cada 15s. Se recarga EN LA PÁGINA A LA QUE SE QUERÍA IR (no en la actual):
// si no, al hacer clic en un módulo tras días sin usar la app, volvía al módulo anterior.
let destinoPendiente = null
router.beforeEach(to => { destinoPendiente = to.fullPath })
router.afterEach(() => { destinoPendiente = null })

const recargarPorChunkViejo = () => {
  const last = Number(sessionStorage.getItem('chunkReloadAt') || 0)
  if (Date.now() - last > 15000) {
    sessionStorage.setItem('chunkReloadAt', String(Date.now()))
    if (destinoPendiente) window.location.assign(router.resolve(destinoPendiente).href)
    else window.location.reload()
  }
}

window.addEventListener('vite:preloadError', event => {
  event.preventDefault()
  recargarPorChunkViejo()
})

window.addEventListener('unhandledrejection', event => {
  const msg = String(event?.reason?.message || event?.reason || '')
  if (/dynamically imported module|Failed to fetch dynamically|Importing a module script failed|Expected a JavaScript|module script/i.test(msg)) {
    recargarPorChunkViejo()
  }
})

const app = createApp(App)

// ✅ REGISTRA TODOS LOS PLUGINS EN ESA APP
// ✅ Solo usa registerPlugins si ahí están vuetify y pinia
app.use(VueKonva)
registerPlugins(app)

// Global API
app.config.globalProperties.$api = api
app.config.globalProperties.$axios = axios

app.mount('#app')
