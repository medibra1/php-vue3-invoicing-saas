<script setup lang="ts">
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import { useAuth } from '@/modules/auth/composables/useAuth'
import { useAuthStore } from '@/modules/auth/store'

// Stand-in landing page so the auth flow (register/login -> protected
// route) has somewhere real to land and be verified end-to-end. The
// actual dashboard (revenue, overdue invoices, charts) is Phase 5 —
// this file gets replaced then, not extended.
const auth = useAuthStore()
const { logout } = useAuth()
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-surface-0 px-4">
    <Card class="w-full max-w-md text-left">
      <h1 class="mb-2 text-xl font-medium text-gray-900">You're logged in</h1>
      <p class="mb-1 text-sm text-gray-600">{{ auth.user?.name }} &lt;{{ auth.user?.email }}&gt;</p>
      <p class="mb-6 text-sm text-gray-500">Tenant #{{ auth.user?.tenantId }}</p>
      <div class="flex flex-wrap gap-3">
        <RouterLink to="/clients">
          <Button variant="secondary">View clients</Button>
        </RouterLink>
        <RouterLink to="/quotes">
          <Button variant="secondary">View quotes</Button>
        </RouterLink>
        <RouterLink to="/invoices">
          <Button variant="secondary">View invoices</Button>
        </RouterLink>
        <Button variant="secondary" @click="logout">Log out</Button>
      </div>
    </Card>
  </div>
</template>
