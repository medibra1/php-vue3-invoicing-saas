<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useAuth } from '../composables/useAuth'

const form = reactive({ tenantName: '', name: '', email: '', password: '' })
const { register, isLoading, errorMessage } = useAuth()

function onSubmit(): void {
  void register({ ...form })
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-surface-0 px-4">
    <Card class="w-full max-w-sm">
      <h1 class="mb-6 text-xl font-medium text-gray-900">Create your InvoicePro account</h1>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <Input v-model="form.tenantName" label="Company name" autocomplete="organization" required />
        <Input v-model="form.name" label="Your name" autocomplete="name" required />
        <Input v-model="form.email" label="Email" type="email" autocomplete="email" required />
        <Input
          v-model="form.password"
          label="Password"
          type="password"
          autocomplete="new-password"
          required
        />

        <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

        <Button type="submit" class="w-full" :disabled="isLoading">
          {{ isLoading ? 'Creating account…' : 'Create account' }}
        </Button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">
        Already have an account?
        <RouterLink to="/login" class="font-medium text-primary-500 hover:text-primary-600">
          Log in
        </RouterLink>
      </p>
    </Card>
  </div>
</template>
