import { createRouter, createWebHistory } from 'vue-router'
import { useSession } from './stores/session'
import Login from './views/Login.vue'
import Dashboard from './views/Dashboard.vue'
import Recepcion from './views/Recepcion.vue'
import Inventario from './views/Inventario.vue'
import Picking from './views/Picking.vue'
import Transporte from './views/Transporte.vue'
import Terminal from './views/Terminal.vue'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/', name: 'tablero', component: Dashboard },
    { path: '/recepcion', name: 'recepcion', component: Recepcion },
    { path: '/inventario', name: 'inventario', component: Inventario },
    { path: '/picking', name: 'picking', component: Picking },
    { path: '/transporte', name: 'transporte', component: Transporte },
    { path: '/terminal', name: 'terminal', component: Terminal, meta: { handheld: true } },
  ],
})

router.beforeEach((to) => {
  const session = useSession()
  if (!to.meta.guest && !session.user) return { name: 'login' }
  if (to.meta.guest && session.user) return { name: 'tablero' }
  return true
})
