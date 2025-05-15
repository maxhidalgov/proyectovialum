import { createApp } from 'vue'
import App from '@/App.vue'
import api from '@/axiosInstance'

// Estilos
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

// Plugins
import { registerPlugins } from '@core/utils/plugins'

const app = createApp(App)

// ✅ Solo usa registerPlugins si ahí están vuetify y pinia
registerPlugins(app)

// Global API
app.config.globalProperties.$api = api

app.mount('#app')
