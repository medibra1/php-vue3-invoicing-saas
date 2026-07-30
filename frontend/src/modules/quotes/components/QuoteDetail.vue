<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import { useClientsStore } from '@/modules/clients/store'
import { useQuotes } from '../composables/useQuotes'
import { useQuotesStore } from '../store'
import type { Quote, QuoteStatus } from '../types'

const route = useRoute()
const router = useRouter()
const quotesStore = useQuotesStore()
const clientsStore = useClientsStore()
const { transition, convert, destroy, isLoading, errorMessage } = useQuotes()

const id = Number(route.params.id)
const quote = ref<Quote | null>(null)
const isLoadingQuote = ref(true)
const convertedInvoiceNumber = ref<string | null>(null)

onMounted(async () => {
  await clientsStore.fetchAll()
  quote.value = await quotesStore.fetchOne(id)
  isLoadingQuote.value = false
})

const clientName = computed(
  () => clientsStore.items.find((c) => c.id === quote.value?.client_id)?.name ?? '—',
)

const statusTone: Record<QuoteStatus, 'neutral' | 'warning' | 'success' | 'danger'> = {
  draft: 'neutral',
  sent: 'warning',
  accepted: 'success',
  rejected: 'danger',
  expired: 'danger',
}

async function moveTo(status: QuoteStatus): Promise<void> {
  const ok = await transition(id, status)

  if (ok) {
    quote.value = await quotesStore.fetchOne(id)
  }
}

async function onConvert(): Promise<void> {
  const invoice = await convert(id)

  if (invoice) {
    convertedInvoiceNumber.value = invoice.number
  }
}

async function onDelete(): Promise<void> {
  if (!confirm('Delete this draft quote?')) {
    return
  }

  const ok = await destroy(id)

  if (ok) {
    await router.push('/quotes')
  }
}
</script>

<template>
  <div class="min-h-screen bg-surface-0 px-6 py-8">
    <div class="mx-auto max-w-2xl">
      <RouterLink to="/quotes" class="mb-4 inline-block text-sm text-primary-500 hover:text-primary-600">
        ← Back to quotes
      </RouterLink>

      <p v-if="isLoadingQuote" class="text-sm text-gray-500">Loading…</p>

      <Card v-else-if="quote">
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h1 class="text-xl font-medium text-gray-900">{{ quote.number }}</h1>
            <p class="text-sm text-gray-500">{{ clientName }}</p>
          </div>
          <Badge :tone="statusTone[quote.status]">{{ quote.status }}</Badge>
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
            <tr v-for="item in quote.items" :key="item.id" class="border-b border-gray-100">
              <td class="py-2">{{ item.description }}</td>
              <td class="py-2 text-right">{{ item.quantity }}</td>
              <td class="py-2 text-right">{{ item.unit_price }}</td>
              <td class="py-2 text-right">{{ item.line_total }}</td>
            </tr>
          </tbody>
        </table>

        <p class="mb-6 text-right text-sm font-medium text-gray-900">
          Total: {{ Number(quote.total).toFixed(2) }}
        </p>

        <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>

        <p v-if="convertedInvoiceNumber" class="mb-4 text-sm text-success-600">
          Converted to invoice {{ convertedInvoiceNumber }}.
        </p>

        <div class="flex flex-wrap gap-3">
          <template v-if="quote.status === 'draft'">
            <RouterLink :to="`/quotes/${quote.id}/edit`">
              <Button variant="secondary" :disabled="isLoading">Edit</Button>
            </RouterLink>
            <Button :disabled="isLoading" @click="moveTo('sent')">Send</Button>
            <Button variant="secondary" :disabled="isLoading" @click="onDelete">Delete</Button>
          </template>

          <template v-else-if="quote.status === 'sent'">
            <Button :disabled="isLoading" @click="moveTo('accepted')">Accept</Button>
            <Button variant="secondary" :disabled="isLoading" @click="moveTo('rejected')">Reject</Button>
            <Button variant="secondary" :disabled="isLoading" @click="moveTo('expired')">Mark as expired</Button>
          </template>

          <template v-else-if="quote.status === 'accepted' && !convertedInvoiceNumber">
            <Button :disabled="isLoading" @click="onConvert">Convert to invoice</Button>
          </template>
        </div>
      </Card>
    </div>
  </div>
</template>
