import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useActivityLogStore } from '../store'

/** Same store/composable split as every other module. */
export function useActivityLog() {
  const store = useActivityLogStore()
  const { items, total, page, perPage } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(page = 1): Promise<void> {
    isLoading.value = true
    errorMessage.value = null

    try {
      await store.fetchPage(page)
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)
    } finally {
      isLoading.value = false
    }
  }

  return { items, total, page, perPage, load, isLoading, errorMessage }
}
