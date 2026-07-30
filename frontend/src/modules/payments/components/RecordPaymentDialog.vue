<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { usePayments } from '../composables/usePayments'
import type { Payment } from '../types'

const props = defineProps<{
  open: boolean
  invoiceId: number
  remainingBalance: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  recorded: [payment: Payment]
}>()

const { record, isLoading, errorMessage } = usePayments()

const amount = ref('')
const method = ref('')
const paidAt = ref(new Date().toISOString().slice(0, 10))
const notes = ref('')

// Pre-fill with the full remaining balance each time the dialog opens —
// the common case is a single full payment, and partial payers just
// overwrite the field.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      amount.value = props.remainingBalance.toFixed(2)
      method.value = ''
      paidAt.value = new Date().toISOString().slice(0, 10)
      notes.value = ''
    }
  },
)

const isValid = computed(() => Number(amount.value) > 0)

async function onSubmit(): Promise<void> {
  if (!isValid.value) {
    return
  }

  const payment = await record(props.invoiceId, {
    amount: Number(amount.value),
    method: method.value || null,
    paid_at: paidAt.value,
    notes: notes.value || null,
  })

  if (payment) {
    emit('recorded', payment)
    emit('update:open', false)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Record a payment</DialogTitle>
      </DialogHeader>

      <form class="grid gap-4" @submit.prevent="onSubmit">
        <Input v-model="amount" type="number" label="Amount" required autocomplete="off" />
        <p class="-mt-2 text-xs text-gray-500">Remaining balance: {{ remainingBalance.toFixed(2) }}</p>

        <Input v-model="method" type="text" label="Method (optional)" autocomplete="off" />
        <Input v-model="paidAt" type="date" label="Payment date" required autocomplete="off" />
        <Input v-model="notes" type="text" label="Notes (optional)" autocomplete="off" />

        <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

        <DialogFooter>
          <Button type="submit" :disabled="isLoading || !isValid">Record payment</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
