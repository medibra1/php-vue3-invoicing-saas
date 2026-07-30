<script setup lang="ts" generic="T extends object">
const props = defineProps<{
  columns: { key: string; label: string }[]
  rows: T[]
  rowKey: keyof T
}>()

// T has no index signature (row types like Client are concrete
// interfaces, not Record<string, unknown>), so dynamic column-key
// access needs one explicit, controlled cast rather than losing type
// safety on every row/column throughout the template.
function cell(row: T, key: string): unknown {
  return (row as Record<string, unknown>)[key]
}

function rowKeyValue(row: T): string {
  return String(row[props.rowKey])
}
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200 bg-surface-2">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-surface-1">
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            class="px-4 py-3 text-left font-medium text-gray-500"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <tr v-if="rows.length === 0">
          <td :colspan="columns.length" class="px-4 py-8 text-center text-gray-400">
            <slot name="empty">No data.</slot>
          </td>
        </tr>
        <tr v-for="row in rows" :key="rowKeyValue(row)" class="hover:bg-surface-1">
          <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-gray-700">
            <slot :name="`cell-${column.key}`" :row="row">
              {{ cell(row, column.key) }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
