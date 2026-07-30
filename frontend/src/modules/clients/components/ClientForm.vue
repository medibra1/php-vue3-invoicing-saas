<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import { useClients } from '../composables/useClients'
import { useClientsStore } from '../store'

const route = useRoute()
const router = useRouter()
const clientsStore = useClientsStore()
const { save, isLoading, errorMessage } = useClients()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = id !== null

const form = reactive({ name: '', email: '', phone: '', address: '' })
const loadingExisting = ref(isEdit)

onMounted(async () => {
  if (id === null) {
    return
  }

  try {
    const client = await clientsStore.fetchOne(id)
    form.name = client.name
    form.email = client.email ?? ''
    form.phone = client.phone ?? ''
    form.address = client.address ?? ''
  } finally {
    loadingExisting.value = false
  }
})

async function onSubmit(): Promise<void> {
  const ok = await save(
    {
      name: form.name,
      email: form.email || null,
      phone: form.phone || null,
      address: form.address || null,
    },
    id ?? undefined,
  )

  if (ok) {
    await router.push('/clients')
  }
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center px-4">
    <Card class="w-full max-w-md">
      <h1 class="mb-6 text-xl font-medium text-gray-900">
        {{ isEdit ? 'Edit client' : 'New client' }}
      </h1>

      <p v-if="loadingExisting" class="text-sm text-gray-500">Loading…</p>

      <form v-else class="space-y-4" @submit.prevent="onSubmit">
        <Input v-model="form.name" label="Name" required />
        <Input v-model="form.email" label="Email" type="email" />
        <Input v-model="form.phone" label="Phone" />
        <Input v-model="form.address" label="Address" />

        <p v-if="errorMessage" class="text-sm text-danger-600">{{ errorMessage }}</p>

        <div class="flex gap-3">
          <Button type="submit" :disabled="isLoading">
            {{ isLoading ? 'Saving…' : 'Save' }}
          </Button>
          <RouterLink to="/clients">
            <Button variant="secondary" type="button">Cancel</Button>
          </RouterLink>
        </div>
      </form>
    </Card>
  </div>
</template>
