<script setup lang="ts">
defineProps<{
  modelValue: string
  label: string
  type?: string
  error?: string | null
  autocomplete?: string
  required?: boolean
}>()

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <label class="block text-left">
    <span class="mb-1 block text-sm font-medium text-gray-700">{{ label }}</span>
    <input
      :type="type ?? 'text'"
      :value="modelValue"
      :autocomplete="autocomplete"
      :required="required"
      class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
      :class="error ? 'border-danger-600' : 'border-gray-300'"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <span v-if="error" class="mt-1 block text-xs text-danger-600">{{ error }}</span>
  </label>
</template>
