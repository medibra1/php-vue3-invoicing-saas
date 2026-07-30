import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useInvoicesStore } from '../store'
import type { InvoicePayload, InvoiceStatus } from '../types'

/** Same store/composable split rationale as useAuth, useClients, useQuotes. */
export function useInvoices() {
  const store = useInvoicesStore()
  const { items: invoices } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    await run(() => store.fetchAll())
  }

  async function save(payload: InvoicePayload, id?: number): Promise<boolean> {
    return run(() => (id ? store.update(id, payload) : store.create(payload)))
  }

  async function destroy(id: number): Promise<boolean> {
    return run(() => store.remove(id))
  }

  async function transition(id: number, status: InvoiceStatus): Promise<boolean> {
    return run(() => store.transition(id, status))
  }

  async function downloadPdf(id: number, filename: string): Promise<boolean> {
    return run(() => store.downloadPdf(id, filename))
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

  return { invoices, load, save, destroy, transition, downloadPdf, isLoading, errorMessage }
}
