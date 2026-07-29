import type { AxiosInstance, AxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/modules/auth/store'

interface RetriableRequestConfig extends AxiosRequestConfig {
  _retried?: boolean
}

/**
 * Attaches the JWT to every outgoing request, and on a 401 tries a
 * single token refresh + retry before giving up and logging out. No
 * component ever manually handles tokens (CLAUDE.md decision) — this is
 * the one place that does.
 *
 * Concurrent 401s share a single in-flight refresh (via `refreshing`)
 * rather than each firing their own /auth/refresh call, which would
 * race and have the losing request revoke the token the winner just
 * received.
 */
export function registerAuthInterceptor(client: AxiosInstance): void {
  let refreshing: Promise<void> | null = null

  client.interceptors.request.use((config) => {
    const auth = useAuthStore()

    if (auth.accessToken) {
      config.headers.set('Authorization', `Bearer ${auth.accessToken}`)
    }

    return config
  })

  client.interceptors.response.use(
    (response) => response,
    async (error) => {
      const auth = useAuthStore()
      const originalRequest = error.config as RetriableRequestConfig | undefined

      const shouldAttemptRefresh =
        error.response?.status === 401 &&
        originalRequest !== undefined &&
        !originalRequest._retried &&
        auth.refreshToken !== null

      if (!shouldAttemptRefresh) {
        throw error
      }

      originalRequest._retried = true

      refreshing ??= auth.refresh().finally(() => {
        refreshing = null
      })

      try {
        await refreshing
      } catch {
        await auth.logout()
        throw error
      }

      return client(originalRequest)
    },
  )
}
