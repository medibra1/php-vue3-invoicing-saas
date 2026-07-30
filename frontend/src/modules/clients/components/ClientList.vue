<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Table from '@/components/ui/Table.vue'
import { useClients } from '../composables/useClients'
import type { Client } from '../types'

const { clients, load, destroy, isLoading, errorMessage } = useClients()
const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | undefined

onMounted(() => load())

function onSearchInput(): void {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(search.value || undefined), 300)
}

// confirm() rather than a Modal component — no Modal exists in
// components/ui/ yet, and building one just for this one confirmation
// would be speculative ahead of an actual second use case.
async function onDelete(client: Client): Promise<void> {
  if (!confirm(`Delete ${client.name}?`)) {
    return
  }

  await destroy(client.id)
}

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'actions', label: '' },
]
</script>

<template>
  <div class="min-h-screen bg-surface-0 px-6 py-8">
    <div class="mx-auto max-w-4xl">
      <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-medium text-gray-900">Clients</h1>
        <RouterLink to="/clients/new">
          <Button>New client</Button>
        </RouterLink>
      </div>

      <div class="mb-4 max-w-xs">
        <Input v-model="search" label="Search by name" @input="onSearchInput" />
      </div>

      <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>
      <p v-if="isLoading" class="mb-4 text-sm text-gray-500">Loading…</p>

      <Table :columns="columns" :rows="clients" :row-key="'id'">
        <template #empty>No clients yet.</template>
        <template #cell-email="{ row }">{{ row.email ?? '—' }}</template>
        <template #cell-phone="{ row }">{{ row.phone ?? '—' }}</template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-3">
            <RouterLink
              :to="`/clients/${row.id}/edit`"
              class="text-sm font-medium text-primary-500 hover:text-primary-600"
            >
              Edit
            </RouterLink>
            <button
              type="button"
              class="text-sm font-medium text-danger-600 hover:text-danger-700"
              @click="onDelete(row)"
            >
              Delete
            </button>
          </div>
        </template>
      </Table>
    </div>
  </div>
</template>
