<script setup lang="ts">
import { useAppStore } from '@/stores/app'

import FileUpload from '@/components/FileUpload.vue'

const app = useAppStore()

</script>

<template>
  <main
    class="mx-auto flex min-h-dvh max-w-3xl flex-col gap-8 px-4 py-12 sm:px-6"
  >
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          {{ app.title }}
        </p>
      </div>
    </header>

    <section
      class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
    >
      <h2 class="text-lg font-medium text-slate-900 dark:text-white">
        Import tranzakcji bankowych
      </h2>
        
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Prześlij plik CSV, JSON lub XML z transakcjami bankowymi.
      </p>
      <FileUpload />
    </section>
  </main>
</template>
