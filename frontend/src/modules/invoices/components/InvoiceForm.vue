<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useClientsStore } from '@/modules/clients/store'
import { useInvoices } from '../composables/useInvoices'
import { useInvoicesStore } from '../store'
import type { InvoiceItemPayload } from '../types'

const route = useRoute()
const router = useRouter()
const clientsStore = useClientsStore()
const invoicesStore = useInvoicesStore()
const { save, isLoading, errorMessage } = useInvoices()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = id !== null

const clientId = ref<number | null>(null)
const notes = ref('')
const dueDate = ref('')
const items = reactive<InvoiceItemPayload[]>([{ description: '', quantity: 1, unit_price: 0 }])
const loadingExisting = ref(isEdit)

const total = computed(() =>
  items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0),
)

onMounted(async () => {
  await clientsStore.fetchAll()

  if (id === null) {
    loadingExisting.value = false

    return
  }

  try {
    const invoice = await invoicesStore.fetchOne(id)
    clientId.value = invoice.client_id
    notes.value = invoice.notes ?? ''
    dueDate.value = invoice.due_date ?? ''
    items.splice(
      0,
      items.length,
      ...(invoice.items ?? []).map((item) => ({
        description: item.description,
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
      })),
    )
  } finally {
    loadingExisting.value = false
  }
})

function addItem(): void {
  items.push({ description: '', quantity: 1, unit_price: 0 })
}

function removeItem(index: number): void {
  if (items.length > 1) {
    items.splice(index, 1)
  }
}

async function onSubmit(): Promise<void> {
  if (clientId.value === null) {
    return
  }

  const ok = await save(
    {
      client_id: clientId.value,
      due_date: dueDate.value || null,
      notes: notes.value || null,
      items,
    },
    id ?? undefined,
  )

  if (ok) {
    await router.push('/invoices')
  }
}
</script>

<template>
  <div class="min-h-screen bg-surface-0 px-6 py-8">
    <div class="mx-auto max-w-2xl">
      <Card>
        <h1 class="mb-6 text-xl font-medium text-gray-900">
          {{ isEdit ? 'Edit invoice' : 'New invoice' }}
        </h1>

        <p v-if="loadingExisting" class="text-sm text-gray-500">Loading…</p>

        <form v-else class="space-y-6" @submit.prevent="onSubmit">
          <label class="block text-left">
            <span class="mb-1 block text-sm font-medium text-gray-700">Client</span>
            <select
              v-model="clientId"
              required
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option :value="null" disabled>Select a client…</option>
              <option v-for="client in clientsStore.items" :key="client.id" :value="client.id">
                {{ client.name }}
              </option>
            </select>
          </label>

          <Input v-model="dueDate" label="Due date" type="date" />
          <Input v-model="notes" label="Notes" />

          <div>
            <div class="mb-2 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700">Line items</span>
              <button
                type="button"
                class="text-sm font-medium text-primary-500 hover:text-primary-600"
                @click="addItem"
              >
                + Add item
              </button>
            </div>

            <div class="space-y-2">
              <div v-for="(item, index) in items" :key="index" class="flex items-end gap-2">
                <div class="flex-1">
                  <Input v-model="item.description" label="Description" />
                </div>
                <div class="w-20">
                  <Input v-model.number="item.quantity" label="Qty" type="number" />
                </div>
                <div class="w-28">
                  <Input v-model.number="item.unit_price" label="Unit price" type="number" />
                </div>
                <button
                  type="button"
                  class="mb-2 text-sm text-danger-600 hover:text-danger-700 disabled:cursor-not-allowed disabled:text-gray-300"
                  :disabled="items.length === 1"
                  @click="removeItem(index)"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>

          <p class="text-right text-sm font-medium text-gray-900">Total: {{ total.toFixed(2) }}</p>

          <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

          <div class="flex gap-3">
            <Button type="submit" :disabled="isLoading">{{ isLoading ? 'Saving…' : 'Save' }}</Button>
            <RouterLink to="/invoices">
              <Button variant="secondary" type="button">Cancel</Button>
            </RouterLink>
          </div>
        </form>
      </Card>
    </div>
  </div>
</template>
