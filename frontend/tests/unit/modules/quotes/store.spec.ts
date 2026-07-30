import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useQuotesStore } from '@/modules/quotes/store'
import type { Quote } from '@/modules/quotes/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

function makeQuote(overrides: Partial<Quote> = {}): Quote {
  return {
    id: 1,
    tenant_id: 1,
    client_id: 1,
    number: 'QUO-2026-00001',
    status: 'draft',
    issue_date: '2026-01-01',
    expiry_date: null,
    notes: null,
    total: '100.00',
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    items: [],
    ...overrides,
  }
}

describe('useQuotesStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAll populates items', async () => {
    const quotes = [makeQuote()]
    vi.mocked(httpClient.get).mockResolvedValue({ data: quotes })

    const store = useQuotesStore()
    await store.fetchAll()

    expect(httpClient.get).toHaveBeenCalledWith('/quotes')
    expect(store.items).toEqual(quotes)
  })

  it('create prepends the new quote', async () => {
    const created = makeQuote({ id: 2 })
    vi.mocked(httpClient.post).mockResolvedValue({ data: created })

    const store = useQuotesStore()
    store.items = [makeQuote({ id: 1 })]
    await store.create({ client_id: 1, items: [{ description: 'X', quantity: 1, unit_price: 1 }] })

    expect(store.items[0]).toEqual(created)
  })

  it('transition posts the new status and replaces the item in the list', async () => {
    const sent = makeQuote({ id: 1, status: 'sent' })
    vi.mocked(httpClient.post).mockResolvedValue({ data: sent })

    const store = useQuotesStore()
    store.items = [makeQuote({ id: 1, status: 'draft' })]
    const result = await store.transition(1, 'sent')

    expect(httpClient.post).toHaveBeenCalledWith('/quotes/1/status', { status: 'sent' })
    expect(result.status).toBe('sent')
    expect(store.items[0]?.status).toBe('sent')
  })

  it('convert posts to the convert endpoint and returns the new invoice, without touching the quotes list', async () => {
    vi.mocked(httpClient.post).mockResolvedValue({ data: { number: 'INV-2026-00001' } })

    const store = useQuotesStore()
    store.items = [makeQuote({ id: 1, status: 'accepted' })]
    const invoice = await store.convert(1)

    expect(httpClient.post).toHaveBeenCalledWith('/quotes/1/convert')
    expect(invoice).toEqual({ number: 'INV-2026-00001' })
    expect(store.items[0]?.status).toBe('accepted') // converting doesn't change the quote itself
  })

  it('remove filters the deleted quote out of items', async () => {
    vi.mocked(httpClient.delete).mockResolvedValue({ data: null })

    const store = useQuotesStore()
    store.items = [makeQuote({ id: 1 }), makeQuote({ id: 2 })]
    await store.remove(1)

    expect(httpClient.delete).toHaveBeenCalledWith('/quotes/1')
    expect(store.items.map((q) => q.id)).toEqual([2])
  })
})
