<script setup lang="ts">
import { computed, onMounted } from 'vue'
import Card from '@/components/ui/Card.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import { useDashboard } from '../composables/useDashboard'
import RevenueChart from './RevenueChart.vue'

const { stats, load, isLoading, errorMessage } = useDashboard()

onMounted(load)

function money(value: number): string {
  return value.toFixed(2)
}

const acceptanceRateLabel = computed(() => {
  const rate = stats.value?.quoteAcceptanceRate

  return rate === null || rate === undefined ? '—' : `${Math.round(rate * 100)}%`
})
</script>

<template>
  <div class="px-6 py-8">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-6 text-xl font-medium text-gray-900">Dashboard</h1>

      <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>
      <p v-if="isLoading && !stats" class="text-sm text-gray-500">Loading…</p>

      <template v-if="stats">
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <MetricCard label="Revenue this month" :value="money(stats.revenue.thisMonth)" />
          <MetricCard label="Revenue all-time" :value="money(stats.revenue.allTime)" />
          <MetricCard
            label="Overdue"
            :value="`${stats.overdue.count} / ${money(stats.overdue.total)}`"
            :tone="stats.overdue.count > 0 ? 'danger' : 'neutral'"
          />
          <MetricCard label="Draft quotes" :value="String(stats.draftQuotes)" />
        </div>

        <Card class="mb-6">
          <h2 class="mb-4 text-sm font-medium text-gray-700">Revenue, last 6 months</h2>
          <RevenueChart :series="stats.revenueByMonth" />
        </Card>

        <p class="text-sm text-gray-500">Quote acceptance rate: {{ acceptanceRateLabel }}</p>
      </template>
    </div>
  </div>
</template>
