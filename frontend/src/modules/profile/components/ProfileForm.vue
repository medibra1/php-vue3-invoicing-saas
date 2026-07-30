<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useProfile } from '../composables/useProfile'

const { profile, load, save, uploadAvatar, deleteAvatar, isLoading, errorMessage } = useProfile()

const name = ref('')
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)

onMounted(async () => {
  await load()
  name.value = profile.value?.name ?? ''
})

onUnmounted(() => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
})

const displayAvatarUrl = computed(() => previewUrl.value ?? profile.value?.avatarUrl ?? null)

const initials = computed(() => {
  const source = name.value || profile.value?.name || ''

  return source
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
})

function onFileSelected(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  selectedFile.value = file

  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }

  previewUrl.value = file ? URL.createObjectURL(file) : null
}

async function onSubmit(): Promise<void> {
  if (selectedFile.value) {
    const ok = await uploadAvatar(selectedFile.value)

    if (ok) {
      if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
      }

      selectedFile.value = null
      previewUrl.value = null
    }
  }

  await save({ name: name.value })
}

async function onRemoveAvatar(): Promise<void> {
  await deleteAvatar()
}
</script>

<template>
  <div class="px-6 py-8">
    <div class="mx-auto max-w-md">
      <RouterLink to="/" class="mb-4 inline-block text-sm text-primary-500 hover:text-primary-600">
        ← Back to dashboard
      </RouterLink>

      <Card>
        <h1 class="mb-6 text-xl font-medium text-gray-900">Edit profile</h1>

        <form class="space-y-4" @submit.prevent="onSubmit">
          <div class="flex items-center gap-4">
            <span v-if="displayAvatarUrl" class="size-16 shrink-0 overflow-hidden rounded-full">
              <img :src="displayAvatarUrl" alt="" class="size-full object-cover" />
            </span>
            <span
              v-else
              class="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary-500 text-lg font-medium text-white"
            >
              {{ initials || '?' }}
            </span>

            <div class="flex flex-col items-start gap-2">
              <label class="cursor-pointer text-sm font-medium text-primary-500 hover:text-primary-600">
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="hidden"
                  @change="onFileSelected"
                />
                Choose photo…
              </label>
              <button
                v-if="profile?.avatarUrl && !selectedFile"
                type="button"
                class="text-sm text-danger-600 hover:underline"
                :disabled="isLoading"
                @click="onRemoveAvatar"
              >
                Remove photo
              </button>
            </div>
          </div>

          <Input v-model="name" label="Name" required />
          <p class="text-sm text-gray-500">{{ profile?.email }}</p>

          <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

          <div class="flex gap-3">
            <Button type="submit" :disabled="isLoading">Save</Button>
            <RouterLink to="/profile/password">
              <Button type="button" variant="secondary">Change password</Button>
            </RouterLink>
          </div>
        </form>
      </Card>
    </div>
  </div>
</template>
