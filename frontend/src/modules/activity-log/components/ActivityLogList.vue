<script setup lang="ts">
import { computed, onMounted } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import { useActivityLog } from '../composables/useActivityLog'

const { items, total, page, perPage, load, isLoading, errorMessage } = useActivityLog()

onMounted(() => load(1))

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

function goTo(targetPage: number): void {
  void load(targetPage)
}

const columns = [
  { key: 'created_at', label: 'Date' },
  { key: 'subject_type', label: 'Type' },
  { key: 'description', label: 'Description' },
]
</script>

<template>
  <div class="px-6 py-8">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-6 text-xl font-medium text-gray-900">Activity</h1>

      <p v-if="errorMessage" class="mb-4 text-sm text-danger-600">{{ errorMessage }}</p>
      <p v-if="isLoading" class="mb-4 text-sm text-gray-500">Loading…</p>

      <Table :columns="columns" :rows="items" :row-key="'id'">
        <template #empty>No activity yet.</template>
        <template #cell-created_at="{ row }">{{ row.created_at }}</template>
        <template #cell-subject_type="{ row }">
          <Badge tone="neutral">{{ row.subject_type }}</Badge>
        </template>
        <template #cell-description="{ row }">{{ row.description }}</template>
      </Table>

      <div v-if="total > perPage" class="mt-4 flex items-center justify-between">
        <p class="text-sm text-gray-500">Page {{ page }} of {{ totalPages }} ({{ total }} entries)</p>
        <div class="flex gap-3">
          <Button variant="secondary" :disabled="isLoading || page <= 1" @click="goTo(page - 1)">
            Previous
          </Button>
          <Button variant="secondary" :disabled="isLoading || page >= totalPages" @click="goTo(page + 1)">
            Next
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
