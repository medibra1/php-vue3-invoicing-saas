import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useActivityLogStore } from '@/modules/activity-log/store'
import type { ActivityLogPage } from '@/modules/activity-log/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
  },
}))

function makePage(overrides: Partial<ActivityLogPage> = {}): ActivityLogPage {
  return {
    items: [
      {
        id: 1,
        tenant_id: 1,
        user_id: 3,
        action: 'invoice.status_changed',
        subject_type: 'Invoice',
        subject_id: 7,
        description: 'Invoice INV-2026-00001 moved to paid',
        created_at: '2026-08-02 10:00:00',
      },
    ],
    total: 1,
    page: 1,
    perPage: 20,
    ...overrides,
  }
}

describe('useActivityLogStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchPage populates items and pagination state', async () => {
    const page = makePage()
    vi.mocked(httpClient.get).mockResolvedValue({ data: page })

    const store = useActivityLogStore()
    await store.fetchPage(1)

    expect(httpClient.get).toHaveBeenCalledWith('/activity-logs', { params: { page: 1, perPage: 20 } })
    expect(store.items).toEqual(page.items)
    expect(store.total).toBe(1)
    expect(store.page).toBe(1)
  })

  it('fetchPage requests the given page with the current perPage', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: makePage({ page: 2 }) })

    const store = useActivityLogStore()
    await store.fetchPage(2)

    expect(httpClient.get).toHaveBeenCalledWith('/activity-logs', { params: { page: 2, perPage: 20 } })
  })
})
