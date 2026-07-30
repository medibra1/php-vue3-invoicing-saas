<script setup lang="ts">
import { onMounted } from 'vue'
import { useProfile } from '@/modules/profile/composables/useProfile'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'

// The JWT carries no name/avatar claim, so the auth store's `user` (set
// at login) never has an avatarUrl — this is the one place guaranteed
// to mount whenever an authenticated session is active (fresh login or
// a reload with a token already in localStorage), so it's the natural
// spot to fetch the full profile and sync it into the auth store for
// Topbar.vue to render.
const { load } = useProfile()

onMounted(load)
</script>

<template>
  <div class="flex h-screen bg-surface-0">
    <Sidebar />
    <div class="flex min-w-0 flex-1 flex-col">
      <Topbar />
      <main class="flex-1 overflow-y-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>
