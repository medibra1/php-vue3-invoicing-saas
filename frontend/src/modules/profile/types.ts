export interface Profile {
  id: number
  tenantId: number
  name: string
  email: string
  avatarUrl: string | null
  role: string | null
}

export interface ProfileUpdatePayload {
  name: string
}

export interface PasswordChangePayload {
  current_password: string
  new_password: string
}
