<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary'
    type?: 'button' | 'submit'
    disabled?: boolean
  }>(),
  {
    variant: 'primary',
    type: 'button',
    disabled: false,
  },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
    :class="
      variant === 'primary'
        ? 'bg-primary-500 text-white hover:bg-primary-600'
        : 'border border-gray-200 bg-surface-1 text-gray-700 hover:bg-surface-2'
    "
  >
    <slot />
  </button>
</template>
