<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import { useTeam } from '../composables/useTeam'
import AddMemberDialog from './AddMemberDialog.vue'
import type { RoleSlug } from '../types'

const { members, load, isLoading, errorMessage } = useTeam()

const isDialogOpen = ref(false)

onMounted(load)

const roleTone: Record<RoleSlug, 'neutral' | 'warning' | 'success' | 'danger'> = {
  owner: 'success',
  admin: 'success',
  accountant: 'warning',
  viewer: 'neutral',
}

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'role', label: 'Role' },
]
</script>

<template>
  <div class="px-6 py-8">
    <div class="mx-auto max-w-4xl">
      <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-medium text-gray-900">Team</h1>
        <Button @click="isDialogOpen = true">Add member</Button>
      </div>

      <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>
      <p v-if="isLoading" class="mb-4 text-sm text-gray-500">Loading…</p>

      <Table :columns="columns" :rows="members" :row-key="'id'">
        <template #empty>No team members yet.</template>
        <template #cell-role="{ row }">
          <Badge :tone="row.role ? roleTone[row.role] : 'neutral'">{{ row.role ?? '—' }}</Badge>
        </template>
      </Table>

      <AddMemberDialog v-model:open="isDialogOpen" />
    </div>
  </div>
</template>
