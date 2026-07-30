export interface Client {
  id: number
  tenant_id: number
  name: string
  email: string | null
  phone: string | null
  address: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface ClientPayload {
  name: string
  email?: string | null
  phone?: string | null
  address?: string | null
}
