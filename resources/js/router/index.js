import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import Eventos from '../pages/Eventos.vue'
import Stands from '../pages/Stands.vue'
import Usuarios from '../pages/Usuarios.vue'
import Reservaciones from '../pages/Reservaciones.vue'
import Reportes from '../pages/Reportes.vue'
import Gastos from '../pages/Gastos.vue'
import Mobiliario from '../pages/Mobiliario.vue'

const routes = [
    { path: '/login', component: Login, name: 'Login', meta: { guest: true } },
    { path: '/', component: Dashboard, name: 'Dashboard', meta: { requiresAuth: true } },
    { path: '/eventos', component: Eventos, name: 'Eventos', meta: { requiresAuth: true } },
    { path: '/stands', component: Stands, name: 'Stands', meta: { requiresAuth: true } },
    { path: '/usuarios', component: Usuarios, name: 'Usuarios', meta: { requiresAuth: true } },
    { path: '/reservaciones', component: Reservaciones, name: 'Reservaciones', meta: { requiresAuth: true } },
    { path: '/mobiliario', component: Mobiliario, name: 'Mobiliario', meta: { requiresAuth: true } },
    { path: '/gastos', component: Gastos, name: 'Gastos', meta: { requiresAuth: true } },
    { path: '/reportes', component: Reportes, name: 'Reportes', meta: { requiresAuth: true } },
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'Login' })
    } else if (to.meta.guest && authStore.isAuthenticated) {
        next({ name: 'Dashboard' })
    } else {
        next()
    }
})

export default router
