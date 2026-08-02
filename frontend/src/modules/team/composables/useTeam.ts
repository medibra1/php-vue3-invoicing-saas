import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useTeamStore } from '../store'
import type { TeamMemberPayload } from '../types'

/** Same store/composable split as every other module. */
export function useTeam() {
  const store = useTeamStore()
  const { items: members } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    await run(() => store.fetchAll())
  }

  async function addMember(payload: TeamMemberPayload): Promise<boolean> {
    return run(() => store.create(payload))
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

  return { members, load, addMember, isLoading, errorMessage }
}
