export interface AuthUser {
  id: number
  tenantId: number
  name: string
  email: string
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
