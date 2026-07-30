import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useClientsStore } from '../store'
import type { ClientPayload } from '../types'

/**
 * UI-facing wrapper around the clients store: loading/error state, same
 * split rationale as useAuth. `storeToRefs` (not a plain destructure of
 * store.items) — fetchAll() reassigns the store's `items` array
 * wholesale, and a plain destructure would keep pointing at the array
 * that existed at call time instead of tracking the store's current one.
 */
export function useClients() {
  const store = useClientsStore()
  const { items: clients } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(search?: string): Promise<void> {
    await run(() => store.fetchAll(search))
  }

  async function save(payload: ClientPayload, id?: number): Promise<boolean> {
    return run(() => (id ? store.update(id, payload) : store.create(payload)))
  }

  async function destroy(id: number): Promise<boolean> {
    return run(() => store.remove(id))
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

  return { clients, load, save, destroy, isLoading, errorMessage }
}
