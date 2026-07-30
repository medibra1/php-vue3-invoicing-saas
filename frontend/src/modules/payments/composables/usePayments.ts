import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { usePaymentsStore } from '../store'
import type { Payment, PaymentPayload } from '../types'

/** Same store/composable split rationale as useInvoices/useQuotes. */
export function usePayments() {
  const store = usePaymentsStore()
  const { items: payments } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(invoiceId: number): Promise<void> {
    await run(() => store.fetchForInvoice(invoiceId))
  }

  // Returns the created payment (not just a boolean) so the caller can
  // react to the invoice status change it triggers server-side without a
  // second round trip to guess it — same reasoning as useQuotes' convert().
  async function record(invoiceId: number, payload: PaymentPayload): Promise<Payment | null> {
    let result: Payment | null = null
    const ok = await run(async () => {
      result = await store.record(invoiceId, payload)
    })

    return ok ? result : null
  }

  async function run(action: () => Promise<unknown>): Promise<boolean> {
    isLoading.value = true
    errorMessage.value = null

    try {
      await action()

      return true
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)

      return false
    } finally {
      isLoading.value = false
    }
  }

  return { payments, load, record, isLoading, errorMessage }
}
