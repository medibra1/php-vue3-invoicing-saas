import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { usePaymentsStore } from '@/modules/payments/store'
import type { Payment } from '@/modules/payments/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

function makePayment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: 1,
    tenant_id: 1,
    invoice_id: 7,
    amount: '400.00',
    method: 'bank_transfer',
    paid_at: '2026-01-01',
    notes: null,
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    ...overrides,
  }
}

describe('usePaymentsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchForInvoice replaces items with the invoice-scoped list', async () => {
    const payments = [makePayment()]
    vi.mocked(httpClient.get).mockResolvedValue({ data: payments })

    const store = usePaymentsStore()
    await store.fetchForInvoice(7)

    expect(httpClient.get).toHaveBeenCalledWith('/invoices/7/payments')
    expect(store.items).toEqual(payments)
  })

  it('record posts the payload and prepends the created payment', async () => {
    const created = makePayment({ id: 2, amount: '600.00' })
    vi.mocked(httpClient.post).mockResolvedValue({ data: created })

    const store = usePaymentsStore()
    store.items = [makePayment({ id: 1 })]
    const result = await store.record(7, { amount: 600, method: 'card' })

    expect(httpClient.post).toHaveBeenCalledWith('/invoices/7/payments', { amount: 600, method: 'card' })
    expect(result).toEqual(created)
    expect(store.items[0]).toEqual(created)
    expect(store.items).toHaveLength(2)
  })
})
