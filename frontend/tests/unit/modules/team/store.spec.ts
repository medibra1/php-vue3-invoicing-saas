import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useTeamStore } from '@/modules/team/store'
import type { TeamMember } from '@/modules/team/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

function makeMember(overrides: Partial<TeamMember> = {}): TeamMember {
  return {
    id: 1,
    name: 'Jane Doe',
    email: 'jane@example.test',
    role: 'owner',
    ...overrides,
  }
}

describe('useTeamStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAll populates items', async () => {
    const members = [makeMember()]
    vi.mocked(httpClient.get).mockResolvedValue({ data: members })

    const store = useTeamStore()
    await store.fetchAll()

    expect(httpClient.get).toHaveBeenCalledWith('/team')
    expect(store.items).toEqual(members)
  })

  it('create posts the payload and appends the new member', async () => {
    const created = makeMember({ id: 2, name: 'New Member', role: 'accountant' })
    vi.mocked(httpClient.post).mockResolvedValue({ data: created })

    const store = useTeamStore()
    store.items = [makeMember()]
    const payload = { name: 'New Member', email: 'new@example.test', password: 'password123', role: 'accountant' as const }
    const result = await store.create(payload)

    expect(httpClient.post).toHaveBeenCalledWith('/team', payload)
    expect(result).toEqual(created)
    expect(store.items).toHaveLength(2)
    expect(store.items[1]).toEqual(created)
  })
})
