<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

const apiBase =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || 'http://localhost:8080'

/** Stały rozmiar strony listy importów (zgodny z zapytaniem `per_page`). */
const PER_PAGE = 10

export interface ImportRow {
  id: number
  file_name: string
  total_records: number
  successful_records: number
  failed_records: number
  status: string
  created_at: string
}

interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number
  to: number
}

interface ListApiBody {
  success: boolean
  message: string
  data: {
    imports: ImportRow[]
    pagination: PaginationMeta
  }
}

const imports = ref<ImportRow[]>([])
const pagination = ref<PaginationMeta | null>(null)
const loading = ref(false)
const error = ref('')

const currentPage = ref(1)

const listUrl = computed(() => {
  const url = new URL('/api/imports', apiBase)
  url.searchParams.set('current_page', String(currentPage.value))
  url.searchParams.set('per_page', String(PER_PAGE))
  return url.href
})

const canPrev = computed(
  () => pagination.value !== null && pagination.value.current_page > 1,
)
const canNext = computed(
  () =>
    pagination.value !== null &&
    pagination.value.current_page < pagination.value.last_page,
)

function statusClass(status: string): string {
  const s = status.toLowerCase()
  if (s === 'completed') {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200'
  }
  if (s === 'pending' || s === 'processing') {
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
  }
  if (s === 'failed' || s === 'error') {
    return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200'
  }
  return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return new Intl.DateTimeFormat('pl-PL', {
    dateStyle: 'short',
    timeStyle: 'medium',
  }).format(d)
}

async function fetchImports() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch(listUrl.value, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      error.value = `Nie udało się pobrać listy (${response.status}).`
      return
    }
    const body = (await response.json()) as ListApiBody
    if (!body.success || !body.data) {
      error.value = body.message || 'Nieprawidłowa odpowiedź serwera.'
      return
    }
    imports.value = body.data.imports
    pagination.value = body.data.pagination
  } catch (e) {
    error.value = `Błąd sieci: ${(e as Error).message}`
  } finally {
    loading.value = false
  }
}

function goPrev() {
  if (!canPrev.value) return
  currentPage.value -= 1
}

function goNext() {
  if (!canNext.value) return
  currentPage.value += 1
}

watch(currentPage, () => {
  void fetchImports()
})

onMounted(() => {
  void fetchImports()
})
</script>

<template>
  <main
    class="mx-auto flex min-h-dvh max-w-5xl flex-col gap-6 px-4 py-12 sm:px-6"
  >
    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          Importy
        </p>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white">
          Lista importów
        </h1>
      </div>
      <router-link
        to="/"
        class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
      >
        ← Strona główna
      </router-link>
    </header>

    <section
      class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
    >
      <div
        class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
      >
        <p class="text-sm text-slate-600 dark:text-slate-400">
          <template v-if="pagination && pagination.total > 0">
            Wyświetlanie {{ pagination.from }}–{{ pagination.to }} z
            {{ pagination.total }}
          </template>
          <template v-else-if="pagination && pagination.total === 0">
            Brak rekordów
          </template>
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            :disabled="!canPrev || loading"
            @click="goPrev"
          >
            Poprzednia
          </button>
          <span
            v-if="pagination"
            class="text-sm tabular-nums text-slate-600 dark:text-slate-400"
          >
            Strona {{ pagination.current_page }} z {{ pagination.last_page }}
          </span>
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            :disabled="!canNext || loading"
            @click="goNext"
          >
            Następna
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/50">
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                ID
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                Plik
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                Razem
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                OK
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                Błędy
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                Status
              </th>
              <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                Utworzono
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td
                colspan="7"
                class="px-4 py-8 text-center text-slate-500 dark:text-slate-400"
              >
                Ładowanie…
              </td>
            </tr>
            <tr v-else-if="error">
              <td
                colspan="7"
                class="px-4 py-8 text-center text-red-600 dark:text-red-400"
              >
                {{ error }}
              </td>
            </tr>
            <tr
              v-for="row in imports"
              v-else
              :key="row.id"
              class="border-b border-slate-100 last:border-0 dark:border-slate-800"
            >
              <td class="px-4 py-3 tabular-nums text-slate-900 dark:text-slate-100">
                {{ row.id }}
              </td>
              <td class="max-w-[200px] truncate px-4 py-3 text-slate-900 dark:text-slate-100">
                {{ row.file_name }}
              </td>
              <td class="px-4 py-3 tabular-nums text-slate-700 dark:text-slate-300">
                {{ row.total_records }}
              </td>
              <td class="px-4 py-3 tabular-nums text-slate-700 dark:text-slate-300">
                {{ row.successful_records }}
              </td>
              <td class="px-4 py-3 tabular-nums text-slate-700 dark:text-slate-300">
                {{ row.failed_records }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                  :class="statusClass(row.status)"
                >
                  {{ row.status }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                {{ formatDate(row.created_at) }}
              </td>
              <td>
                <router-link
                  to="/import/details/{{ row.id }}"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                  Szczegóły
                </router-link>
              </td>
            </tr>
            <tr v-if="!loading && !error && imports.length === 0">
              <td
                colspan="7"
                class="px-4 py-8 text-center text-slate-500 dark:text-slate-400"
              >
                Brak importów do wyświetlenia.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
