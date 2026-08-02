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
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/modules/dashboard/components/Dashboard.vue'),
        },
        {
          path: 'clients',
          name: 'clients.index',
          component: () => import('@/modules/clients/components/ClientList.vue'),
        },
        {
          path: 'clients/new',
          name: 'clients.create',
          component: () => import('@/modules/clients/components/ClientForm.vue'),
        },
        {
          path: 'clients/:id/edit',
          name: 'clients.edit',
          component: () => import('@/modules/clients/components/ClientForm.vue'),
        },
        {
          path: 'quotes',
          name: 'quotes.index',
          component: () => import('@/modules/quotes/components/QuoteList.vue'),
        },
        {
          path: 'quotes/new',
          name: 'quotes.create',
          component: () => import('@/modules/quotes/components/QuoteForm.vue'),
        },
        {
          path: 'quotes/:id/edit',
          name: 'quotes.edit',
          component: () => import('@/modules/quotes/components/QuoteForm.vue'),
        },
        {
          path: 'quotes/:id',
          name: 'quotes.show',
          component: () => import('@/modules/quotes/components/QuoteDetail.vue'),
        },
        {
          path: 'invoices',
          name: 'invoices.index',
          component: () => import('@/modules/invoices/components/InvoiceList.vue'),
        },
        {
          path: 'invoices/new',
          name: 'invoices.create',
          component: () => import('@/modules/invoices/components/InvoiceForm.vue'),
        },
        {
          path: 'invoices/:id/edit',
          name: 'invoices.edit',
          component: () => import('@/modules/invoices/components/InvoiceForm.vue'),
        },
        {
          path: 'invoices/:id',
          name: 'invoices.show',
          component: () => import('@/modules/invoices/components/InvoiceDetail.vue'),
        },
        {
          path: 'activity',
          name: 'activity.index',
          component: () => import('@/modules/activity-log/components/ActivityLogList.vue'),
        },
        {
          path: 'profile',
          name: 'profile.show',
          component: () => import('@/modules/profile/components/ProfileForm.vue'),
        },
        {
          path: 'profile/password',
          name: 'profile.password',
          component: () => import('@/modules/profile/components/PasswordForm.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(authGuard)

export default router
