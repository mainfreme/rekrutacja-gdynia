<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const apiBase =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || 'http://localhost:8080'

/** Zgodne z domyślną wartością w `ImportListService::details`. */
const ITEMS_PER_PAGE = 30

interface ImportSummary {
  id: number
  file_name: string
  total_records: number
  successful_records: number
  failed_records: number
  status: string
  created_at: string
}

interface PaginationSlice {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

interface TransactionRow {
  id?: number
  import_id?: number | null
  transaction_id: string
  account_number: string
  transaction_date: string | null
  amount: string | number
  currency: string
}

interface ImportLogRow {
  id?: number
  import_id?: number
  transaction_id: string
  error_message: string | null
  created_at: string | null
}

/** Odpowiedź GET /api/imports/{id} — `ImportListService::details`. */
interface ImportDetailApiBody {
  success: boolean
  message: string
  data: {
    import: ImportSummary
    transactions: TransactionRow[]
    transactions_pagination: PaginationSlice
    logs: ImportLogRow[]
    logs_pagination: PaginationSlice
  }
}

const route = useRoute()

const loading = ref(false)
const loadingLists = ref(false)
const error = ref('')
const importSummary = ref<ImportSummary | null>(null)
const transactions = ref<TransactionRow[]>([])
const importLogs = ref<ImportLogRow[]>([])

const transactionsPagination = ref<PaginationSlice | null>(null)
const logsPagination = ref<PaginationSlice | null>(null)

const activeTab = ref<'transactions' | 'logs'>('transactions')

const transactionsPage = ref(1)
const logsPage = ref(1)

const importId = computed(() => String(route.params.id ?? ''))

const canTransactionsPrev = computed(
  () => (transactionsPagination.value?.current_page ?? 1) > 1,
)
const canTransactionsNext = computed(() => {
  const p = transactionsPagination.value
  if (!p) return false
  return p.current_page < p.last_page
})

const canLogsPrev = computed(
  () => (logsPagination.value?.current_page ?? 1) > 1,
)
const canLogsNext = computed(() => {
  const p = logsPagination.value
  if (!p) return false
  return p.current_page < p.last_page
})

function buildDetailUrl(): string {
  const id = importId.value
  const url = new URL(`/api/imports/${encodeURIComponent(id)}`, apiBase)
  url.searchParams.set('transactions_page', String(transactionsPage.value))
  url.searchParams.set('transactions_per_page', String(ITEMS_PER_PAGE))
  url.searchParams.set('logs_page', String(logsPage.value))
  url.searchParams.set('logs_per_page', String(ITEMS_PER_PAGE))
  return url.href
}

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

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return new Intl.DateTimeFormat('pl-PL', {
    dateStyle: 'short',
    timeStyle: 'medium',
  }).format(d)
}

function formatDateOnly(iso: string | null | undefined): string {
  if (!iso) return '—'
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim())
  if (m) {
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
    return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium' }).format(d)
  }
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium' }).format(d)
}

function formatAmount(amount: string | number): string {
  const n = typeof amount === 'number' ? amount : Number(amount)
  if (Number.isNaN(n)) return String(amount)
  return new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n)
}

async function fetchDetail(): Promise<void> {
  const url = buildDetailUrl()
  if (!importId.value) {
    error.value = 'Brak identyfikatora importu.'
    return
  }

  const isInitial = importSummary.value === null
  if (isInitial) {
    loading.value = true
  } else {
    loadingLists.value = true
  }
  error.value = ''
  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
    })
    if (response.status === 404) {
      error.value = 'Nie znaleziono importu.'
      importSummary.value = null
      transactions.value = []
      importLogs.value = []
      transactionsPagination.value = null
      logsPagination.value = null
      transactionsPage.value = 1
      logsPage.value = 1
      return
    }
    if (!response.ok) {
      error.value = `Nie udało się pobrać szczegółów (${response.status}).`
      return
    }
    const body = (await response.json()) as ImportDetailApiBody
    if (!body.success || !body.data?.import) {
      error.value = body.message || 'Nieprawidłowa odpowiedź serwera.'
      return
    }
    importSummary.value = body.data.import
    transactions.value = body.data.transactions ?? []
    importLogs.value = body.data.logs ?? []
    transactionsPagination.value = body.data.transactions_pagination
    logsPagination.value = body.data.logs_pagination

    if (body.data.transactions_pagination) {
      transactionsPage.value = body.data.transactions_pagination.current_page
    }
    if (body.data.logs_pagination) {
      logsPage.value = body.data.logs_pagination.current_page
    }
  } catch (e) {
    error.value = `Błąd sieci: ${(e as Error).message}`
  } finally {
    loading.value = false
    loadingLists.value = false
  }
}

function goTransactionsPrev(): void {
  if (!canTransactionsPrev.value) return
  transactionsPage.value -= 1
  void fetchDetail()
}

function goTransactionsNext(): void {
  if (!canTransactionsNext.value) return
  transactionsPage.value += 1
  void fetchDetail()
}

function goLogsPrev(): void {
  if (!canLogsPrev.value) return
  logsPage.value -= 1
  void fetchDetail()
}

function goLogsNext(): void {
  if (!canLogsNext.value) return
  logsPage.value += 1
  void fetchDetail()
}

watch(importId, () => {
  importSummary.value = null
  transactions.value = []
  importLogs.value = []
  transactionsPagination.value = null
  logsPagination.value = null
  transactionsPage.value = 1
  logsPage.value = 1
  void fetchDetail()
})

onMounted(() => {
  void fetchDetail()
})
</script>

<template>
  <main
    class="mx-auto flex min-h-dvh max-w-5xl flex-col gap-6 px-4 py-12 sm:px-6"
  >
    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          Import
        </p>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white">
          Szczegóły importu
          <span
            v-if="importSummary"
            class="ml-1 tabular-nums text-slate-500 dark:text-slate-400"
          >#{{ importSummary.id }}</span>
        </h1>
      </div>
      <router-link
        to="/imports"
        class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
      >
        ← Lista importów
      </router-link>
    </header>

    <section
      v-if="loading && !importSummary"
      class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-400"
    >
      Ładowanie…
    </section>

    <section
      v-else-if="error && !importSummary"
      class="rounded-2xl border border-red-200 bg-red-50/80 p-6 text-center text-red-800 shadow-sm dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
    >
      {{ error }}
    </section>

    <template v-else-if="importSummary">
      <!-- Podsumowanie (imports) -->
      <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
      >
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Podsumowanie
        </h2>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
              Nazwa pliku
            </dt>
            <dd class="mt-1 break-all text-sm font-medium text-slate-900 dark:text-slate-100">
              {{ importSummary.file_name }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
              Liczba rekordów
            </dt>
            <dd class="mt-1 tabular-nums text-sm text-slate-900 dark:text-slate-100">
              {{ importSummary.total_records }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
              Status
            </dt>
            <dd class="mt-1">
              <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                :class="statusClass(importSummary.status)"
              >
                {{ importSummary.status }}
              </span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
              Zapisane poprawnie
            </dt>
            <dd class="mt-1 tabular-nums text-sm text-emerald-700 dark:text-emerald-300">
              {{ importSummary.successful_records }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
              Błędy
            </dt>
            <dd class="mt-1 tabular-nums text-sm text-red-700 dark:text-red-300">
              {{ importSummary.failed_records }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- Zakładki: transakcje / logi -->
      <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
      >
        <div
          class="flex border-b border-slate-200 dark:border-slate-700"
          role="tablist"
        >
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'transactions'"
            class="flex-1 px-4 py-3 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            :class="
              activeTab === 'transactions'
                ? 'border-b-2 border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
            "
            @click="activeTab = 'transactions'"
          >
            Transakcje
          </button>
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'logs'"
            class="flex-1 px-4 py-3 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            :class="
              activeTab === 'logs'
                ? 'border-b-2 border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
            "
            @click="activeTab = 'logs'"
          >
            Logi błędów
          </button>
        </div>

        <div
          v-show="activeTab === 'transactions'"
          class="flex flex-col"
        >
          <div
            class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
          >
            <p class="text-sm text-slate-600 dark:text-slate-400">
              <template v-if="transactionsPagination && transactionsPagination.total > 0">
                Wyświetlanie {{ transactionsPagination.from }}–{{ transactionsPagination.to }} z
                {{ transactionsPagination.total }}
              </template>
              <template v-else>
                Brak rekordów
              </template>
            </p>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canTransactionsPrev || loadingLists"
                @click="goTransactionsPrev"
              >
                Poprzednia
              </button>
              <span class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
                Strona {{ transactionsPagination?.current_page ?? 1 }} z {{ transactionsPagination?.last_page ?? 1 }}
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canTransactionsNext || loadingLists"
                @click="goTransactionsNext"
              >
                Następna
              </button>
            </div>
          </div>
          <div class="overflow-x-auto">
            <p
              v-if="loadingLists"
              class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400"
            >
              Ładowanie…
            </p>
            <table
              v-else
              class="w-full min-w-[720px] text-left text-sm"
            >
              <thead>
                <tr class="border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/50">
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    ID transakcji
                  </th>
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Numer konta
                  </th>
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Data
                  </th>
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Kwota
                  </th>
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Waluta
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in transactions"
                  :key="row.id ?? `tx-${row.transaction_id}`"
                  class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                >
                  <td class="px-4 py-3 font-mono text-xs text-slate-900 dark:text-slate-100">
                    {{ row.transaction_id }}
                  </td>
                  <td class="px-4 py-3 text-slate-900 dark:text-slate-100">
                    {{ row.account_number }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-slate-700 dark:text-slate-300">
                    {{ formatDateOnly(row.transaction_date) }}
                  </td>
                  <td class="px-4 py-3 tabular-nums text-slate-900 dark:text-slate-100">
                    {{ formatAmount(row.amount) }}
                  </td>
                  <td class="px-4 py-3 uppercase text-slate-700 dark:text-slate-300">
                    {{ row.currency }}
                  </td>
                </tr>
                <tr v-if="!loadingLists && transactions.length === 0">
                  <td
                    colspan="5"
                    class="px-4 py-8 text-center text-slate-500 dark:text-slate-400"
                  >
                    Brak zapisanych transakcji dla tego importu.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div
          v-show="activeTab === 'logs'"
          class="flex flex-col"
        >
          <div
            class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
          >
            <p class="text-sm text-slate-600 dark:text-slate-400">
              <template v-if="logsPagination && logsPagination.total > 0">
                Wyświetlanie {{ logsPagination.from }}–{{ logsPagination.to }} z
                {{ logsPagination.total }}
              </template>
              <template v-else>
                Brak rekordów
              </template>
            </p>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canLogsPrev || loadingLists"
                @click="goLogsPrev"
              >
                Poprzednia
              </button>
              <span class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
                Strona {{ logsPagination?.current_page ?? 1 }} z {{ logsPagination?.last_page ?? 1 }}
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canLogsNext || loadingLists"
                @click="goLogsNext"
              >
                Następna
              </button>
            </div>
          </div>
          <div class="overflow-x-auto">
            <p
              v-if="loadingLists"
              class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400"
            >
              Ładowanie…
            </p>
            <table
              v-else
              class="w-full min-w-[640px] text-left text-sm"
            >
              <thead>
                <tr class="border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/50">
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    ID transakcji
                  </th>
                  <th class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Komunikat
                  </th>
                  <th class="whitespace-nowrap px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                    Czas
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in importLogs"
                  :key="row.id ?? `log-${row.transaction_id}`"
                  class="border-b border-slate-100 align-top last:border-0 dark:border-slate-800"
                >
                  <td class="px-4 py-3 font-mono text-xs text-slate-900 dark:text-slate-100">
                    {{ row.transaction_id }}
                  </td>
                  <td class="max-w-md break-words px-4 py-3 text-slate-800 dark:text-slate-200">
                    {{ row.error_message || '—' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                    {{ formatDate(row.created_at) }}
                  </td>
                </tr>
                <tr v-if="!loadingLists && importLogs.length === 0">
                  <td
                    colspan="3"
                    class="px-4 py-8 text-center text-slate-500 dark:text-slate-400"
                  >
                    Brak wpisów w logach dla tego importu.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </template>
  </main>
</template>
