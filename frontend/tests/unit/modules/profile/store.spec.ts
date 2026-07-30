import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useProfileStore } from '@/modules/profile/store'
import type { Profile } from '@/modules/profile/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

function makeProfile(overrides: Partial<Profile> = {}): Profile {
  return {
    id: 1,
    tenantId: 1,
    name: 'Jane Doe',
    email: 'jane@example.test',
    avatarUrl: null,
    role: 'owner',
    ...overrides,
  }
}

describe('useProfileStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetch populates the profile from GET /me', async () => {
    const profile = makeProfile()
    vi.mocked(httpClient.get).mockResolvedValue({ data: profile })

    const store = useProfileStore()
    await store.fetch()

    expect(httpClient.get).toHaveBeenCalledWith('/me')
    expect(store.profile).toEqual(profile)
  })

  it('update PUTs the name and replaces the profile', async () => {
    const updated = makeProfile({ name: 'Jane Smith' })
    vi.mocked(httpClient.put).mockResolvedValue({ data: updated })

    const store = useProfileStore()
    const result = await store.update({ name: 'Jane Smith' })

    expect(httpClient.put).toHaveBeenCalledWith('/me', { name: 'Jane Smith' })
    expect(result).toEqual(updated)
    expect(store.profile).toEqual(updated)
  })

  it('uploadAvatar posts a FormData body with the Content-Type header unset', async () => {
    const updated = makeProfile({ avatarUrl: 'http://localhost:8000/uploads/avatars/1/1.png' })
    vi.mocked(httpClient.post).mockResolvedValue({ data: updated })

    const store = useProfileStore()
    const file = new File(['fake image bytes'], 'avatar.png', { type: 'image/png' })
    await store.uploadAvatar(file)

    expect(httpClient.post).toHaveBeenCalledWith('/me/avatar', expect.any(FormData), {
      headers: { 'Content-Type': undefined },
    })
    const formData = vi.mocked(httpClient.post).mock.calls[0]?.[1] as FormData
    expect(formData.get('avatar')).toBe(file)
    expect(store.profile).toEqual(updated)
  })

  it('deleteAvatar clears the avatar on the profile', async () => {
    const updated = makeProfile({ avatarUrl: null })
    vi.mocked(httpClient.delete).mockResolvedValue({ data: updated })

    const store = useProfileStore()
    await store.deleteAvatar()

    expect(httpClient.delete).toHaveBeenCalledWith('/me/avatar')
    expect(store.profile).toEqual(updated)
  })

  it('changePassword PUTs current and new password', async () => {
    vi.mocked(httpClient.put).mockResolvedValue({ data: null })

    const store = useProfileStore()
    await store.changePassword({ current_password: 'old-pw', new_password: 'new-pw-12345' })

    expect(httpClient.put).toHaveBeenCalledWith('/me/password', {
      current_password: 'old-pw',
      new_password: 'new-pw-12345',
    })
  })
})
