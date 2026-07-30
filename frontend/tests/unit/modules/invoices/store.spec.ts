import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useInvoicesStore } from '@/modules/invoices/store'
import type { Invoice } from '@/modules/invoices/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

function makeInvoice(overrides: Partial<Invoice> = {}): Invoice {
  return {
    id: 1,
    tenant_id: 1,
    client_id: 1,
    quote_id: null,
    number: 'INV-2026-00001',
    status: 'draft',
    issue_date: '2026-01-01',
    due_date: null,
    notes: null,
    total: '100.00',
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    items: [],
    ...overrides,
  }
}

describe('useInvoicesStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAll populates items', async () => {
    const invoices = [makeInvoice()]
    vi.mocked(httpClient.get).mockResolvedValue({ data: invoices })

    const store = useInvoicesStore()
    await store.fetchAll()

    expect(httpClient.get).toHaveBeenCalledWith('/invoices')
    expect(store.items).toEqual(invoices)
  })

  it('create prepends the new invoice, standalone (no quote_id)', async () => {
    const created = makeInvoice({ id: 2 })
    vi.mocked(httpClient.post).mockResolvedValue({ data: created })

    const store = useInvoicesStore()
    await store.create({ client_id: 1, items: [{ description: 'X', quantity: 1, unit_price: 1 }] })

    expect(store.items[0]).toEqual(created)
    expect(store.items[0]?.quote_id).toBeNull()
  })

  it('transition through overdue to paid replaces the item in the list at each step', async () => {
    const store = useInvoicesStore()
    store.items = [makeInvoice({ id: 1, status: 'sent' })]

    vi.mocked(httpClient.post).mockResolvedValueOnce({ data: makeInvoice({ id: 1, status: 'overdue' }) })
    await store.transition(1, 'overdue')
    expect(store.items[0]?.status).toBe('overdue')

    vi.mocked(httpClient.post).mockResolvedValueOnce({ data: makeInvoice({ id: 1, status: 'paid' }) })
    await store.transition(1, 'paid')
    expect(store.items[0]?.status).toBe('paid')
  })

  it('downloadPdf requests a blob and triggers a browser download', async () => {
    const blob = new Blob(['%PDF-1.7 fake content'], { type: 'application/pdf' })
    vi.mocked(httpClient.get).mockResolvedValue({ data: blob })

    const createObjectURL = vi.fn(() => 'blob:mock-url')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })

    const clickSpy = vi.fn()
    const anchor = document.createElement('a')
    anchor.click = clickSpy
    vi.spyOn(document, 'createElement').mockReturnValue(anchor)

    const store = useInvoicesStore()
    await store.downloadPdf(1, 'INV-2026-00001.pdf')

    expect(httpClient.get).toHaveBeenCalledWith('/invoices/1/pdf', { responseType: 'blob' })
    expect(createObjectURL).toHaveBeenCalled()
    expect(anchor.download).toBe('INV-2026-00001.pdf')
    expect(clickSpy).toHaveBeenCalled()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')

    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('remove filters the deleted invoice out of items', async () => {
    vi.mocked(httpClient.delete).mockResolvedValue({ data: null })

    const store = useInvoicesStore()
    store.items = [makeInvoice({ id: 1 }), makeInvoice({ id: 2 })]
    await store.remove(1)

    expect(httpClient.delete).toHaveBeenCalledWith('/invoices/1')
    expect(store.items.map((i) => i.id)).toEqual([2])
  })
})
