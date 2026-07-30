import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useClientsStore } from '@/modules/clients/store'
import type { Client } from '@/modules/clients/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

function makeClient(overrides: Partial<Client> = {}): Client {
  return {
    id: 1,
    tenant_id: 1,
    name: 'Acme',
    email: null,
    phone: null,
    address: null,
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    ...overrides,
  }
}

describe('useClientsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAll populates items from the API response', async () => {
    const clients = [makeClient()]
    vi.mocked(httpClient.get).mockResolvedValue({ data: clients })

    const store = useClientsStore()
    await store.fetchAll()

    expect(httpClient.get).toHaveBeenCalledWith('/clients', { params: undefined })
    expect(store.items).toEqual(clients)
  })

  it('fetchAll passes a search term as a query param', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })

    const store = useClientsStore()
    await store.fetchAll('globex')

    expect(httpClient.get).toHaveBeenCalledWith('/clients', { params: { search: 'globex' } })
  })

  it('create prepends the new client to items', async () => {
    const existing = makeClient({ id: 1, name: 'Existing' })
    const created = makeClient({ id: 2, name: 'New Co' })
    vi.mocked(httpClient.post).mockResolvedValue({ data: created })

    const store = useClientsStore()
    store.items = [existing]
    await store.create({ name: 'New Co' })

    expect(store.items).toEqual([created, existing])
  })

  it('update replaces the matching item in place', async () => {
    const original = makeClient({ id: 1, name: 'Old Name' })
    const updated = makeClient({ id: 1, name: 'New Name' })
    vi.mocked(httpClient.put).mockResolvedValue({ data: updated })

    const store = useClientsStore()
    store.items = [original]
    await store.update(1, { name: 'New Name' })

    expect(httpClient.put).toHaveBeenCalledWith('/clients/1', { name: 'New Name' })
    expect(store.items).toEqual([updated])
  })

  it('remove filters the deleted client out of items', async () => {
    vi.mocked(httpClient.delete).mockResolvedValue({ data: null })

    const store = useClientsStore()
    store.items = [makeClient({ id: 1 }), makeClient({ id: 2 })]
    await store.remove(1)

    expect(httpClient.delete).toHaveBeenCalledWith('/clients/1')
    expect(store.items.map((c) => c.id)).toEqual([2])
  })
})
