import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useDashboardStore } from '../store'

/** Same store/composable split rationale as every other module. */
export function useDashboard() {
  const store = useDashboardStore()
  const { stats } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    isLoading.value = true
    errorMessage.value = null

    try {
      await store.fetchStats()
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)
    } finally {
      isLoading.value = false
    }
  }

  return { stats, load, isLoading, errorMessage }
}
