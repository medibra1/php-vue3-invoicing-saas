import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { Payment, PaymentPayload } from './types'

// Like invoice_items, payments only ever get viewed in the context of one
// invoice at a time (InvoiceDetail) — items holds that single invoice's
// payments, not a global payments list, mirroring the invoices store's
// shape rather than needing a Map keyed by invoice id.
export const usePaymentsStore = defineStore('payments', {
  state: () => ({
    items: [] as Payment[],
  }),

  actions: {
    async fetchForInvoice(invoiceId: number): Promise<void> {
      const { data } = await httpClient.get<Payment[]>(`/invoices/${invoiceId}/payments`)
      this.items = data
    },

    async record(invoiceId: number, payload: PaymentPayload): Promise<Payment> {
      const { data } = await httpClient.post<Payment>(`/invoices/${invoiceId}/payments`, payload)
      this.items.unshift(data)

      return data
    },
  },
})
