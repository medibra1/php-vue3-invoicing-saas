<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import { useClientsStore } from '@/modules/clients/store'
import { useInvoices } from '../composables/useInvoices'
import type { InvoiceStatus } from '../types'

const { invoices, load, isLoading, errorMessage } = useInvoices()
const clientsStore = useClientsStore()

onMounted(() => {
  void load()
  void clientsStore.fetchAll()
})

function clientName(clientId: number): string {
  return clientsStore.items.find((c) => c.id === clientId)?.name ?? `#${clientId}`
}

const statusTone: Record<InvoiceStatus, 'neutral' | 'warning' | 'success' | 'danger'> = {
  draft: 'neutral',
  sent: 'warning',
  partially_paid: 'warning',
  paid: 'success',
  overdue: 'danger',
  cancelled: 'danger',
}

const columns = [
  { key: 'number', label: 'Number' },
  { key: 'client', label: 'Client' },
  { key: 'status', label: 'Status' },
  { key: 'total', label: 'Total' },
  { key: 'actions', label: '' },
]
</script>

<template>
  <div class="min-h-screen bg-surface-0 px-6 py-8">
    <div class="mx-auto max-w-4xl">
      <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-medium text-gray-900">Invoices</h1>
        <RouterLink to="/invoices/new">
          <Button>New invoice</Button>
        </RouterLink>
      </div>

      <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>
      <p v-if="isLoading" class="mb-4 text-sm text-gray-500">Loading…</p>

      <Table :columns="columns" :rows="invoices" :row-key="'id'">
        <template #empty>No invoices yet.</template>
        <template #cell-client="{ row }">{{ clientName(row.client_id) }}</template>
        <template #cell-status="{ row }">
          <Badge :tone="statusTone[row.status]">{{ row.status }}</Badge>
        </template>
        <template #cell-total="{ row }">{{ Number(row.total).toFixed(2) }}</template>
        <template #cell-actions="{ row }">
          <RouterLink
            :to="`/invoices/${row.id}`"
            class="text-sm font-medium text-primary-500 hover:text-primary-600"
          >
            View
          </RouterLink>
        </template>
      </Table>
    </div>
  </div>
</template>
