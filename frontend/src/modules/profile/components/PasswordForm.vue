<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useProfile } from '../composables/useProfile'

const router = useRouter()
const { changePassword, isLoading, errorMessage } = useProfile()

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const mismatchError = ref<string | null>(null)

async function onSubmit(): Promise<void> {
  mismatchError.value = null

  if (newPassword.value !== confirmPassword.value) {
    mismatchError.value = 'New password and confirmation do not match.'

    return
  }

  const ok = await changePassword({
    current_password: currentPassword.value,
    new_password: newPassword.value,
  })

  if (ok) {
    await router.push('/profile')
  }
}
</script>

<template>
  <div class="px-6 py-8">
    <div class="mx-auto max-w-md">
      <RouterLink to="/profile" class="mb-4 inline-block text-sm text-primary-500 hover:text-primary-600">
        ← Back to profile
      </RouterLink>

      <Card>
        <h1 class="mb-6 text-xl font-medium text-gray-900">Change password</h1>

        <form class="space-y-4" @submit.prevent="onSubmit">
          <Input
            v-model="currentPassword"
            type="password"
            label="Current password"
            required
            autocomplete="current-password"
          />
          <Input v-model="newPassword" type="password" label="New password" required autocomplete="new-password" />
          <Input
            v-model="confirmPassword"
            type="password"
            label="Confirm new password"
            required
            autocomplete="new-password"
          />

          <p v-if="mismatchError ?? errorMessage" class="text-sm text-danger-600">
            {{ mismatchError ?? errorMessage }}
          </p>

          <Button type="submit" :disabled="isLoading">Change password</Button>
        </form>
      </Card>
    </div>
  </div>
</template>
