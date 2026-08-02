export interface ActivityLogEntry {
  id: number
  tenant_id: number
  user_id: number | null
  action: string
  subject_type: string
  subject_id: number
  description: string
  created_at: string
}

export interface ActivityLogPage {
  items: ActivityLogEntry[]
  total: number
  page: number
  perPage: number
}
