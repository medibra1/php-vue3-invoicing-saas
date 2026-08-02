<script setup lang="ts">
import { ref, watch } from 'vue'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useTeam } from '../composables/useTeam'
import type { RoleSlug } from '../types'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const { addMember, isLoading, errorMessage } = useTeam()

const name = ref('')
const email = ref('')
const password = ref('')
const role = ref<RoleSlug>('viewer')

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      name.value = ''
      email.value = ''
      password.value = ''
      role.value = 'viewer'
    }
  },
)

async function onSubmit(): Promise<void> {
  const ok = await addMember({ name: name.value, email: email.value, password: password.value, role: role.value })

  if (ok) {
    emit('update:open', false)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Add a team member</DialogTitle>
      </DialogHeader>

      <form class="grid gap-4" @submit.prevent="onSubmit">
        <Input v-model="name" label="Name" required autocomplete="off" />
        <Input v-model="email" type="email" label="Email" required autocomplete="off" />
        <Input v-model="password" type="password" label="Temporary password" required autocomplete="new-password" />
        <p class="-mt-2 text-xs text-gray-500">
          Share this password with them directly — there's no invitation email.
        </p>

        <label class="block text-left">
          <span class="mb-1 block text-sm font-medium text-gray-700">Role</span>
          <select
            v-model="role"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="owner">Owner</option>
            <option value="admin">Admin</option>
            <option value="accountant">Accountant</option>
            <option value="viewer">Viewer</option>
          </select>
        </label>

        <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

        <DialogFooter>
          <Button type="submit" :disabled="isLoading">Add member</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
