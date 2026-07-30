import { defineStore } from 'pinia'
import { httpClient } from '@/api/httpClient'
import type { PasswordChangePayload, Profile, ProfileUpdatePayload } from './types'

export const useProfileStore = defineStore('profile', {
  state: () => ({
    profile: null as Profile | null,
  }),

  actions: {
    async fetch(): Promise<Profile> {
      const { data } = await httpClient.get<Profile>('/me')
      this.profile = data

      return data
    },

    async update(payload: ProfileUpdatePayload): Promise<Profile> {
      const { data } = await httpClient.put<Profile>('/me', payload)
      this.profile = data

      return data
    },

    async uploadAvatar(file: File): Promise<Profile> {
      const formData = new FormData()
      formData.append('avatar', file)

      // The instance-level default Content-Type (application/json, see
      // httpClient.ts) must be unset for this one call — otherwise axios
      // JSON-stringifies the FormData instead of sending it as
      // multipart. Leaving it undefined lets the browser compute the
      // correct multipart boundary itself.
      const { data } = await httpClient.post<Profile>('/me/avatar', formData, {
        headers: { 'Content-Type': undefined },
      })
      this.profile = data

      return data
    },

    async deleteAvatar(): Promise<Profile> {
      const { data } = await httpClient.delete<Profile>('/me/avatar')
      this.profile = data

      return data
    },

    async changePassword(payload: PasswordChangePayload): Promise<void> {
      await httpClient.put('/me/password', payload)
    },
  },
})
