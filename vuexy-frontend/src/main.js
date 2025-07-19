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

const app = createApp(App)

// ✅ REGISTRA TODOS LOS PLUGINS EN ESA APP
// ✅ Solo usa registerPlugins si ahí están vuetify y pinia
app.use(VueKonva)
registerPlugins(app)

// Global API
app.config.globalProperties.$api = api
app.config.globalProperties.$axios = axios

app.mount('#app')
