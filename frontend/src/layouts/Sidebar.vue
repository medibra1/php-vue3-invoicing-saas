<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  ActivityIcon,
  FileTextIcon,
  LayoutDashboardIcon,
  PanelLeftCloseIcon,
  PanelLeftOpenIcon,
  ReceiptIcon,
  UsersIcon,
} from '@lucide/vue'

const COLLAPSED_KEY = 'invoicepro.sidebarCollapsed'

const route = useRoute()
const collapsed = ref(localStorage.getItem(COLLAPSED_KEY) === 'true')

watch(collapsed, (value) => {
  localStorage.setItem(COLLAPSED_KEY, String(value))
})

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboardIcon },
  { to: '/clients', label: 'Clients', icon: UsersIcon },
  { to: '/quotes', label: 'Quotes', icon: FileTextIcon },
  { to: '/invoices', label: 'Invoices', icon: ReceiptIcon },
  { to: '/activity', label: 'Activity', icon: ActivityIcon },
]

function isActive(to: string): boolean {
  return to === '/' ? route.path === '/' : route.path.startsWith(to)
}
</script>

<template>
  <aside
    class="flex shrink-0 flex-col border-r border-gray-200 bg-surface-2 transition-[width]"
    :class="collapsed ? 'w-16' : 'w-56'"
  >
    <button
      type="button"
      class="flex items-center gap-2 px-4 py-4 text-gray-500 hover:text-gray-700"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="collapsed = !collapsed"
    >
      <PanelLeftOpenIcon v-if="collapsed" class="size-5 shrink-0" />
      <PanelLeftCloseIcon v-else class="size-5 shrink-0" />
    </button>

    <nav class="flex flex-col gap-1 px-2">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium"
        :class="
          isActive(item.to) ? 'bg-primary-500/10 text-primary-600' : 'text-gray-600 hover:bg-surface-1'
        "
      >
        <component :is="item.icon" class="size-5 shrink-0" />
        <span v-if="!collapsed">{{ item.label }}</span>
      </RouterLink>
    </nav>
  </aside>
</template>
