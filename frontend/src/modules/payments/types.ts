// amount comes back as a string — same MySQL DECIMAL stringification as
// invoice/quote totals (see invoices/types.ts).
export interface Payment {
  id: number
  tenant_id: number
  invoice_id: number
  amount: string
  method: string | null
  paid_at: string
  notes: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface PaymentPayload {
  amount: number
  method?: string | null
  paid_at?: string
  notes?: string | null
}
