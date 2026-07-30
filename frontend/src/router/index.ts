import { createRouter, createWebHistory } from 'vue-router'
import { authGuard } from './guards'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/auth/components/LoginForm.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/modules/auth/components/RegisterForm.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      name: 'home',
      component: () => import('@/modules/dashboard/DashboardPlaceholder.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/clients',
      name: 'clients.index',
      component: () => import('@/modules/clients/components/ClientList.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/clients/new',
      name: 'clients.create',
      component: () => import('@/modules/clients/components/ClientForm.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/clients/:id/edit',
      name: 'clients.edit',
      component: () => import('@/modules/clients/components/ClientForm.vue'),
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach(authGuard)

export default router
