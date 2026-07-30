<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDownIcon, LogOutIcon, UserIcon } from '@lucide/vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/modules/auth/composables/useAuth'
import { useAuthStore } from '@/modules/auth/store'

const auth = useAuthStore()
const router = useRouter()
const { logout } = useAuth()

const initials = computed(() => {
  const name = auth.user?.name ?? ''

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
})

function goToProfile(): void {
  void router.push('/profile')
}
</script>

<template>
  <header class="flex h-14 shrink-0 items-center justify-end border-b border-gray-200 bg-surface-2 px-6">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-surface-1">
          <span v-if="auth.user?.avatarUrl" class="size-8 shrink-0 overflow-hidden rounded-full">
            <img :src="auth.user.avatarUrl" alt="" class="size-full object-cover" />
          </span>
          <span
            v-else
            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-500 text-xs font-medium text-white"
          >
            {{ initials || '?' }}
          </span>
          <span class="text-sm font-medium text-gray-700">{{ auth.user?.name }}</span>
          <ChevronDownIcon class="size-4 text-gray-400" />
        </button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" class="w-56">
        <DropdownMenuLabel>
          <p class="text-sm font-medium text-gray-900">{{ auth.user?.name }}</p>
          <p class="text-xs font-normal text-gray-500">{{ auth.user?.email }}</p>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem @click="goToProfile">
          <UserIcon class="size-4" />
          Edit profile
        </DropdownMenuItem>
        <DropdownMenuItem @click="logout">
          <LogOutIcon class="size-4" />
          Log out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </header>
</template>
