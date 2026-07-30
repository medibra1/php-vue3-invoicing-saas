import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { DashboardStats } from './types'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: null as DashboardStats | null,
  }),

  actions: {
    async fetchStats(): Promise<void> {
      const { data } = await httpClient.get<DashboardStats>('/stats/dashboard')
      this.stats = data
    },
  },
})
