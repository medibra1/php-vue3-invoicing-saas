export interface DashboardStats {
  revenue: {
    thisMonth: number
    allTime: number
  }
  overdue: {
    count: number
    total: number
  }
  draftQuotes: number
  quoteAcceptanceRate: number | null
  revenueByMonth: Array<{ month: string; revenue: number }>
}
