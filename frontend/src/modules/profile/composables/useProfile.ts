import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { extractErrorMessage } from '@/api/httpClient'
import { useAuthStore } from '@/modules/auth/store'
import { useProfileStore } from '../store'
import type { PasswordChangePayload, Profile, ProfileUpdatePayload } from '../types'

/**
 * UI-facing wrapper around the profile store, same split as every other
 * module — plus it keeps the auth store's `user` (what Topbar.vue
 * displays) in sync after a name/avatar change, since that's a
 * different store populated at login and otherwise never refreshed.
 */
export function useProfile() {
  const store = useProfileStore()
  const authStore = useAuthStore()
  const { profile } = storeToRefs(store)

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    await run(() => store.fetch())
  }

  async function save(payload: ProfileUpdatePayload): Promise<boolean> {
    return run(() => store.update(payload))
  }

  async function uploadAvatar(file: File): Promise<boolean> {
    return run(() => store.uploadAvatar(file))
  }

  async function deleteAvatar(): Promise<boolean> {
    return run(() => store.deleteAvatar())
  }

  async function changePassword(payload: PasswordChangePayload): Promise<boolean> {
    return run(() => store.changePassword(payload))
  }

  async function run(action: () => Promise<Profile | void>): Promise<boolean> {
    isLoading.value = true
    errorMessage.value = null

    try {
      const result = await action()

      if (result) {
        authStore.updateUser({ name: result.name, avatarUrl: result.avatarUrl })
      }

      return true
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)

      return false
    } finally {
      isLoading.value = false
    }
  }

  return { profile, load, save, uploadAvatar, deleteAvatar, changePassword, isLoading, errorMessage }
}
