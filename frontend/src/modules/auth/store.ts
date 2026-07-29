import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { AuthTokens, AuthUser, LoginPayload, RegisterPayload } from './types'

const ACCESS_TOKEN_KEY = 'invoicepro.accessToken'
const REFRESH_TOKEN_KEY = 'invoicepro.refreshToken'
const USER_KEY = 'invoicepro.user'

function readStoredUser(): AuthUser | null {
  const raw = localStorage.getItem(USER_KEY)

  return raw ? (JSON.parse(raw) as AuthUser) : null
}

/**
 * Owns session state (tokens + current user) and the raw API calls.
 * UI-facing concerns (loading flags, form error messages, post-login
 * navigation) live in composables/useAuth.ts instead — this store stays
 * usable from anywhere (router guards, interceptors) without pulling in
 * Vue Router.
 *
 * Tokens are persisted to localStorage so a page refresh doesn't log
 * the user out; this is what authInterceptor.ts reads from indirectly
 * (via this store) on every request.
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    accessToken: localStorage.getItem(ACCESS_TOKEN_KEY),
    refreshToken: localStorage.getItem(REFRESH_TOKEN_KEY),
    user: readStoredUser(),
  }),

  getters: {
    isAuthenticated: (state): boolean => state.accessToken !== null,
  },

  actions: {
    setSession(tokens: AuthTokens): void {
      this.accessToken = tokens.accessToken
      this.refreshToken = tokens.refreshToken
      this.user = tokens.user

      localStorage.setItem(ACCESS_TOKEN_KEY, tokens.accessToken)
      localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken)
      localStorage.setItem(USER_KEY, JSON.stringify(tokens.user))
    },

    clearSession(): void {
      this.accessToken = null
      this.refreshToken = null
      this.user = null

      localStorage.removeItem(ACCESS_TOKEN_KEY)
      localStorage.removeItem(REFRESH_TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
    },

    async register(payload: RegisterPayload): Promise<void> {
      const { data } = await httpClient.post<AuthTokens>('/auth/register', payload)
      this.setSession(data)
    },

    async login(payload: LoginPayload): Promise<void> {
      const { data } = await httpClient.post<AuthTokens>('/auth/login', payload)
      this.setSession(data)
    },

    /** Called by authInterceptor on a 401 — never call directly from a component. */
    async refresh(): Promise<void> {
      if (!this.refreshToken) {
        throw new Error('No refresh token available.')
      }

      const { data } = await httpClient.post<AuthTokens>('/auth/refresh', {
        refreshToken: this.refreshToken,
      })
      this.setSession(data)
    },

    async logout(): Promise<void> {
      if (this.refreshToken) {
        try {
          await httpClient.post('/auth/logout', { refreshToken: this.refreshToken })
        } catch {
          // Best-effort: the local session is cleared regardless of
          // whether the server call succeeds.
        }
      }

      this.clearSession()
    },
  },
})
