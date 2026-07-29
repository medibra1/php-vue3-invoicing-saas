import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { extractErrorMessage } from '@/api/httpClient'
import { useAuthStore } from '../store'
import type { LoginPayload, RegisterPayload } from '../types'

/**
 * UI-facing wrapper around the auth store: adds loading/error state and
 * post-success navigation, which the store itself deliberately doesn't
 * own (see store.ts doc) so it stays usable outside component context.
 */
export function useAuth() {
  const store = useAuthStore()
  const router = useRouter()

  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function login(payload: LoginPayload): Promise<void> {
    await run(() => store.login(payload))
  }

  async function register(payload: RegisterPayload): Promise<void> {
    await run(() => store.register(payload))
  }

  /**
   * Router guards only re-run on navigation — clearing the session
   * while already sitting on a protected route wouldn't otherwise
   * redirect anywhere, so logout explicitly navigates to /login rather
   * than relying on the guard to notice.
   */
  async function logout(): Promise<void> {
    await store.logout()
    await router.push({ name: 'login' })
  }

  async function run(action: () => Promise<void>): Promise<void> {
    isLoading.value = true
    errorMessage.value = null

    try {
      await action()
      await router.push({ name: 'home' })
    } catch (error) {
      errorMessage.value = extractErrorMessage(error)
    } finally {
      isLoading.value = false
    }
  }

  return { login, register, logout, isLoading, errorMessage }
}
