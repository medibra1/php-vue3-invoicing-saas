import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { Client, ClientPayload } from './types'

export const useClientsStore = defineStore('clients', {
  state: () => ({
    items: [] as Client[],
  }),

  actions: {
    async fetchAll(search?: string): Promise<void> {
      const { data } = await httpClient.get<Client[]>('/clients', {
        params: search ? { search } : undefined,
      })
      this.items = data
    },

    async fetchOne(id: number): Promise<Client> {
      const { data } = await httpClient.get<Client>(`/clients/${id}`)

      return data
    },

    async create(payload: ClientPayload): Promise<Client> {
      const { data } = await httpClient.post<Client>('/clients', payload)
      this.items.unshift(data)

      return data
    },

    async update(id: number, payload: ClientPayload): Promise<Client> {
      const { data } = await httpClient.put<Client>(`/clients/${id}`, payload)
      const index = this.items.findIndex((c) => c.id === id)

      if (index !== -1) {
        this.items[index] = data
      }

      return data
    },

    async remove(id: number): Promise<void> {
      await httpClient.delete(`/clients/${id}`)
      this.items = this.items.filter((c) => c.id !== id)
    },
  },
})
