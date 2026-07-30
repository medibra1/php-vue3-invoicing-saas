export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'

// quantity/unit_price/line_total/total come back as strings — MySQL
// DECIMAL columns are stringified by PDO, and that survives json_encode.
export interface InvoiceItem {
  id: number
  description: string
  quantity: string
  unit_price: string
  line_total: string
  sort_order: number
}

export interface Invoice {
  id: number
  tenant_id: number
  client_id: number
  quote_id: number | null
  number: string
  status: InvoiceStatus
  issue_date: string
  due_date: string | null
  notes: string | null
  total: string
  created_at: string
  updated_at: string
  deleted_at: string | null
  items?: InvoiceItem[]
}

export interface InvoiceItemPayload {
  description: string
  quantity: number
  unit_price: number
}

export interface InvoicePayload {
  client_id: number
  issue_date?: string
  due_date?: string | null
  notes?: string | null
  items: InvoiceItemPayload[]
}
