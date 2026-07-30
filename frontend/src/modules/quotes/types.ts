export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'

// quantity/unit_price/line_total/total come back as strings — MySQL
// DECIMAL columns are stringified by PDO, and that survives json_encode.
export interface QuoteItem {
  id: number
  description: string
  quantity: string
  unit_price: string
  line_total: string
  sort_order: number
}

export interface Quote {
  id: number
  tenant_id: number
  client_id: number
  number: string
  status: QuoteStatus
  issue_date: string
  expiry_date: string | null
  notes: string | null
  total: string
  created_at: string
  updated_at: string
  deleted_at: string | null
  items?: QuoteItem[]
}

export interface QuoteItemPayload {
  description: string
  quantity: number
  unit_price: number
}

export interface QuotePayload {
  client_id: number
  issue_date?: string
  expiry_date?: string | null
  notes?: string | null
  items: QuoteItemPayload[]
}

/** Only what QuoteDetail needs to show after a successful conversion. */
export interface ConvertedInvoice {
  number: string
}
