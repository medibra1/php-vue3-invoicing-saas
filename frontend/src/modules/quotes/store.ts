import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { ConvertedInvoice, Quote, QuotePayload, QuoteStatus } from './types'

export const useQuotesStore = defineStore('quotes', {
  state: () => ({
    items: [] as Quote[],
  }),

  actions: {
    async fetchAll(): Promise<void> {
      const { data } = await httpClient.get<Quote[]>('/quotes')
      this.items = data
    },

    async fetchOne(id: number): Promise<Quote> {
      const { data } = await httpClient.get<Quote>(`/quotes/${id}`)

      return data
    },

    async create(payload: QuotePayload): Promise<Quote> {
      const { data } = await httpClient.post<Quote>('/quotes', payload)
      this.items.unshift(data)

      return data
    },

    async update(id: number, payload: QuotePayload): Promise<Quote> {
      const { data } = await httpClient.put<Quote>(`/quotes/${id}`, payload)
      this.replaceInList(data)

      return data
    },

    async remove(id: number): Promise<void> {
      await httpClient.delete(`/quotes/${id}`)
      this.items = this.items.filter((q) => q.id !== id)
    },

    async transition(id: number, status: QuoteStatus): Promise<Quote> {
      const { data } = await httpClient.post<Quote>(`/quotes/${id}/status`, { status })
      this.replaceInList(data)

      return data
    },

    async convert(id: number): Promise<ConvertedInvoice> {
      const { data } = await httpClient.post<ConvertedInvoice>(`/quotes/${id}/convert`)

      return data
    },

    replaceInList(quote: Quote): void {
      const index = this.items.findIndex((q) => q.id === quote.id)

      if (index !== -1) {
        this.items[index] = quote
      }
    },
  },
})
