import type { NavigationGuardWithThis } from 'vue-router'
import { useAuthStore } from '@/modules/auth/store'

/**
 * UX-only gate — reads auth state to redirect (protected route without
 * a session -> /login, login/register while already authenticated ->
 * home). Never the actual security boundary: every protected endpoint
 * enforces this again server-side via AuthMiddleware/TenantResolverMiddleware/
 * PermissionMiddleware regardless of what this guard decides.
 */
export const authGuard: NavigationGuardWithThis<undefined> = (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }

  return true
}
