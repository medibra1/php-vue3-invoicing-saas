import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { TeamMember, TeamMemberPayload } from './types'

export const useTeamStore = defineStore('team', {
  state: () => ({
    items: [] as TeamMember[],
  }),

  actions: {
    async fetchAll(): Promise<void> {
      const { data } = await httpClient.get<TeamMember[]>('/team')
      this.items = data
    },

    async create(payload: TeamMemberPayload): Promise<TeamMember> {
      const { data } = await httpClient.post<TeamMember>('/team', payload)
      this.items.push(data)

      return data
    },
  },
})
