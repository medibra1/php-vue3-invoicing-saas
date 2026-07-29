<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'success' | 'warning' | 'danger' | 'neutral' }>(), {
  tone: 'neutral',
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="{
      'bg-success-100 text-success-600': tone === 'success',
      'bg-warning-100 text-warning-600': tone === 'warning',
      'bg-danger-100 text-danger-600': tone === 'danger',
      'bg-surface-1 text-gray-600': tone === 'neutral',
    }"
  >
    <slot />
  </span>
</template>
