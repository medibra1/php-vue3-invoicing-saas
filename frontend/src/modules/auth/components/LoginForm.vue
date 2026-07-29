<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useAuth } from '../composables/useAuth'

const form = reactive({ email: '', password: '' })
const { login, isLoading, errorMessage } = useAuth()

function onSubmit(): void {
  void login({ ...form })
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-surface-0 px-4">
    <Card class="w-full max-w-sm">
      <h1 class="mb-6 text-xl font-medium text-gray-900">Log in to InvoicePro</h1>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <Input v-model="form.email" label="Email" type="email" autocomplete="email" required />
        <Input v-model="form.password" label="Password" type="password" autocomplete="current-password" required />

        <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

        <Button type="submit" class="w-full" :disabled="isLoading">
          {{ isLoading ? 'Logging in…' : 'Log in' }}
        </Button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">
        No account yet?
        <RouterLink to="/register" class="font-medium text-primary-500 hover:text-primary-600">
          Sign up
        </RouterLink>
      </p>
    </Card>
  </div>
</template>
