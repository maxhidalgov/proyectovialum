// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'

// Importa tus vistas (pages)
import HomeView from '@/views/HomeView.vue'
//import Index from '@/views/productos/Index.vue'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: HomeView
  },
  {
    path: '/productos',
    name: 'productos',
    component: () => import('@/views/productos/Index.vue'), // ← Asegúrate de que exista
  },
  // Agrega más rutas según lo que vayas creando
]

const router = createRouter({
  history: createWebHistory(),
  routes
})
console.log(router.getRoutes()) // 👈 te debe listar rutas, incluyendo "productos"

export default router
