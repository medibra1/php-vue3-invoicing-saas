<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import { useClientsStore } from '@/modules/clients/store'
import { useInvoices } from '../composables/useInvoices'
import { useInvoicesStore } from '../store'
import type { Invoice, InvoiceStatus } from '../types'

const route = useRoute()
const router = useRouter()
const invoicesStore = useInvoicesStore()
const clientsStore = useClientsStore()
const { transition, destroy, downloadPdf, isLoading, errorMessage } = useInvoices()

const id = Number(route.params.id)
const invoice = ref<Invoice | null>(null)
const isLoadingInvoice = ref(true)

onMounted(async () => {
  await clientsStore.fetchAll()
  invoice.value = await invoicesStore.fetchOne(id)
  isLoadingInvoice.value = false
})

const clientName = computed(
  () => clientsStore.items.find((c) => c.id === invoice.value?.client_id)?.name ?? '—',
)

const statusTone: Record<InvoiceStatus, 'neutral' | 'warning' | 'success' | 'danger'> = {
  draft: 'neutral',
  sent: 'warning',
  paid: 'success',
  overdue: 'danger',
  cancelled: 'danger',
}

async function moveTo(status: InvoiceStatus): Promise<void> {
  const ok = await transition(id, status)

  if (ok) {
    invoice.value = await invoicesStore.fetchOne(id)
  }
}

async function onDownload(): Promise<void> {
  if (!invoice.value) {
    return
  }

  await downloadPdf(invoice.value.id, `${invoice.value.number}.pdf`)
}

async function onDelete(): Promise<void> {
  if (!confirm('Delete this draft invoice?')) {
    return
  }

  const ok = await destroy(id)

  if (ok) {
    await router.push('/invoices')
  }
}
</script>

<template>
  <div class="min-h-screen bg-surface-0 px-6 py-8">
    <div class="mx-auto max-w-2xl">
      <RouterLink to="/invoices" class="mb-4 inline-block text-sm text-primary-500 hover:text-primary-600">
        ← Back to invoices
      </RouterLink>

      <p v-if="isLoadingInvoice" class="text-sm text-gray-500">Loading…</p>

      <Card v-else-if="invoice">
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h1 class="text-xl font-medium text-gray-900">{{ invoice.number }}</h1>
            <p class="text-sm text-gray-500">{{ clientName }}</p>
          </div>
          <Badge :tone="statusTone[invoice.status]">{{ invoice.status }}</Badge>
        </div>

        <table class="mb-4 w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 text-left text-gray-500">
              <th class="py-2 font-medium">Description</th>
              <th class="py-2 text-right font-medium">Qty</th>
              <th class="py-2 text-right font-medium">Unit price</th>
              <th class="py-2 text-right font-medium">Line total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in invoice.items" :key="item.id" class="border-b border-gray-100">
              <td class="py-2">{{ item.description }}</td>
              <td class="py-2 text-right">{{ item.quantity }}</td>
              <td class="py-2 text-right">{{ item.unit_price }}</td>
              <td class="py-2 text-right">{{ item.line_total }}</td>
            </tr>
          </tbody>
        </table>

        <p class="mb-6 text-right text-sm font-medium text-gray-900">
          Total: {{ Number(invoice.total).toFixed(2) }}
        </p>

        <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>

        <div class="flex flex-wrap gap-3">
          <Button variant="secondary" :disabled="isLoading" @click="onDownload">Download PDF</Button>

          <template v-if="invoice.status === 'draft'">
            <RouterLink :to="`/invoices/${invoice.id}/edit`">
              <Button variant="secondary" :disabled="isLoading">Edit</Button>
            </RouterLink>
            <Button :disabled="isLoading" @click="moveTo('sent')">Send</Button>
            <Button variant="secondary" :disabled="isLoading" @click="onDelete">Delete</Button>
          </template>

          <template v-else-if="invoice.status === 'sent'">
            <Button :disabled="isLoading" @click="moveTo('paid')">Mark as paid</Button>
            <Button variant="secondary" :disabled="isLoading" @click="moveTo('overdue')">Mark as overdue</Button>
            <Button variant="secondary" :disabled="isLoading" @click="moveTo('cancelled')">Cancel</Button>
          </template>

          <template v-else-if="invoice.status === 'overdue'">
            <Button :disabled="isLoading" @click="moveTo('paid')">Mark as paid</Button>
            <Button variant="secondary" :disabled="isLoading" @click="moveTo('cancelled')">Cancel</Button>
          </template>
        </div>
      </Card>
    </div>
  </div>
</template>
