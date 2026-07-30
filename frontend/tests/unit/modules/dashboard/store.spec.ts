import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { httpClient } from '@/api/httpClient'
import { useDashboardStore } from '@/modules/dashboard/store'
import type { DashboardStats } from '@/modules/dashboard/types'

vi.mock('@/api/httpClient', () => ({
  httpClient: {
    get: vi.fn(),
  },
}))

function makeStats(overrides: Partial<DashboardStats> = {}): DashboardStats {
  return {
    revenue: { thisMonth: 300, allTime: 1200 },
    overdue: { count: 1, total: 500 },
    draftQuotes: 2,
    quoteAcceptanceRate: 0.5,
    revenueByMonth: [{ month: '2026-07', revenue: 300 }],
    ...overrides,
  }
}

describe('useDashboardStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchStats populates stats from the dashboard endpoint', async () => {
    const stats = makeStats()
    vi.mocked(httpClient.get).mockResolvedValue({ data: stats })

    const store = useDashboardStore()
    await store.fetchStats()

    expect(httpClient.get).toHaveBeenCalledWith('/stats/dashboard')
    expect(store.stats).toEqual(stats)
  })
})
