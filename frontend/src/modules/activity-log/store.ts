import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { ActivityLogPage } from './types'

export const useActivityLogStore = defineStore('activityLog', {
  state: () => ({
    items: [] as ActivityLogPage['items'],
    total: 0,
    page: 1,
    perPage: 20,
  }),

  actions: {
    async fetchPage(page = 1): Promise<void> {
      const { data } = await httpClient.get<ActivityLogPage>('/activity-logs', {
        params: { page, perPage: this.perPage },
      })
      this.items = data.items
      this.total = data.total
      this.page = data.page
      this.perPage = data.perPage
    },
  },
})
