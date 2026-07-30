<script setup lang="ts">
withDefaults(defineProps<{ label: string; value: string; tone?: 'neutral' | 'danger' }>(), {
  tone: 'neutral',
})
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-surface-2 p-6 shadow-sm">
    <p class="text-[13px] text-gray-500">{{ label }}</p>
    <p class="mt-1 text-2xl font-medium" :class="tone === 'danger' ? 'text-danger-600' : 'text-gray-900'">
      {{ value }}
    </p>
  </div>
</template>
