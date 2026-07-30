import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { Invoice, InvoicePayload, InvoiceStatus } from './types'

export const useInvoicesStore = defineStore('invoices', {
  state: () => ({
    items: [] as Invoice[],
  }),

  actions: {
    async fetchAll(): Promise<void> {
      const { data } = await httpClient.get<Invoice[]>('/invoices')
      this.items = data
    },

    async fetchOne(id: number): Promise<Invoice> {
      const { data } = await httpClient.get<Invoice>(`/invoices/${id}`)

      return data
    },

    async create(payload: InvoicePayload): Promise<Invoice> {
      const { data } = await httpClient.post<Invoice>('/invoices', payload)
      this.items.unshift(data)

      return data
    },

    async update(id: number, payload: InvoicePayload): Promise<Invoice> {
      const { data } = await httpClient.put<Invoice>(`/invoices/${id}`, payload)
      this.replaceInList(data)

      return data
    },

    async remove(id: number): Promise<void> {
      await httpClient.delete(`/invoices/${id}`)
      this.items = this.items.filter((i) => i.id !== id)
    },

    async transition(id: number, status: InvoiceStatus): Promise<Invoice> {
      const { data } = await httpClient.post<Invoice>(`/invoices/${id}/status`, { status })
      this.replaceInList(data)

      return data
    },

    // Triggers a real browser download rather than returning bytes to
    // the caller — the PDF has no further use in-app once fetched.
    async downloadPdf(id: number, filename: string): Promise<void> {
      const response = await httpClient.get(`/invoices/${id}/pdf`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      link.click()
      window.URL.revokeObjectURL(url)
    },

    replaceInList(invoice: Invoice): void {
      const index = this.items.findIndex((i) => i.id === invoice.id)

      if (index !== -1) {
        this.items[index] = invoice
      }
    },
  },
})
