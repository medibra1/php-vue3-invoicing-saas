import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useQuotesStore } from '../store'
import type { ConvertedInvoice, QuotePayload, QuoteStatus } from '../types'

/** Same store/composable split rationale as useAuth and useClients. */
export function useQuotes() {
  const store = useQuotesStore()
  const { items: quotes } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    await run(() => store.fetchAll())
  }

  async function save(payload: QuotePayload, id?: number): Promise<boolean> {
    return run(() => (id ? store.update(id, payload) : store.create(payload)))
  }

  async function destroy(id: number): Promise<boolean> {
    return run(() => store.remove(id))
  }

  async function transition(id: number, status: QuoteStatus): Promise<boolean> {
    return run(() => store.transition(id, status))
  }

  // Doesn't go through run(): the caller needs the converted invoice's
  // number, which a boolean-returning helper would discard.
  async function convert(id: number): Promise<ConvertedInvoice | null> {
    isLoading.value = true
    errorMessage.value = null

    try {
      return await store.convert(id)
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)

      return null
    } finally {
      isLoading.value = false
    }
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

  return { quotes, load, save, destroy, transition, convert, isLoading, errorMessage }
}
