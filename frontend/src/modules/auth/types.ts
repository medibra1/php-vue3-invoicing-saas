export interface AuthUser {
  id: number
  tenantId: number
  name: string
  email: string
  // Absent from the register/login response (the JWT carries no
  // name/avatar claim) — populated after an explicit GET /me call, see
  // AdminLayout.vue's onMounted. Optional rather than defaulted to
  // null so its absence is visible in the type, not silently coerced.
  avatarUrl?: string | null
}

/** Shape returned by all four /api/v1/auth/* endpoints (see AuthController). */
export interface AuthTokens {
  accessToken: string
  refreshToken: string
  user: AuthUser
}

export interface RegisterPayload {
  tenantName: string
  name: string
  email: string
  password: string
}

export interface LoginPayload {
  email: string
  password: string
}
