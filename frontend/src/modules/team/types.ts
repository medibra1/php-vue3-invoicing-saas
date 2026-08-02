export type RoleSlug = 'owner' | 'admin' | 'accountant' | 'viewer'

export interface TeamMember {
  id: number
  name: string
  email: string
  role: RoleSlug | null
}

export interface TeamMemberPayload {
  name: string
  email: string
  password: string
  role: RoleSlug
}
