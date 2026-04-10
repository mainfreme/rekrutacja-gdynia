<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const apiBase =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || 'http://localhost:8080'

/** Liczba wierszy na stronę w zakładkach Transakcje i Logi (paginacja po stronie klienta). */
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

interface TransactionRow {
  transaction_id: string
  account_number: string
  transaction_date: string | null
  amount: string
  currency: string
}

interface ImportLogRow {
  transaction_id: string
  error_message: string | null
  created_at: string | null
}

/** Odpowiedź GET /api/imports/{id} — jeden request, pełne dane. */
interface ImportDetailApiBody {
  success: boolean
  message: string
  data: {
    import: ImportSummary
    transactions: TransactionRow[]
    import_logs: ImportLogRow[]
  }
}

const route = useRoute()

const loading = ref(false)
const error = ref('')
const importSummary = ref<ImportSummary | null>(null)
const transactions = ref<TransactionRow[]>([])
const importLogs = ref<ImportLogRow[]>([])

const activeTab = ref<'transactions' | 'logs'>('transactions')

const transactionsPage = ref(1)
const logsPage = ref(1)

const importId = computed(() => String(route.params.id ?? ''))

function pageCount(total: number): number {
  return Math.max(1, Math.ceil(total / ITEMS_PER_PAGE))
}

const transactionsPagination = computed(() => {
  const total = transactions.value.length
  const lastPage = pageCount(total)
  const currentPage = Math.min(Math.max(1, transactionsPage.value), lastPage)
  if (total === 0) {
    return {
      total: 0,
      lastPage: 1,
      currentPage: 1,
      from: 0,
      to: 0,
    }
  }
  const from = (currentPage - 1) * ITEMS_PER_PAGE + 1
  const to = Math.min(currentPage * ITEMS_PER_PAGE, total)
  return { total, lastPage, currentPage, from, to }
})

const logsPagination = computed(() => {
  const total = importLogs.value.length
  const lastPage = pageCount(total)
  const currentPage = Math.min(Math.max(1, logsPage.value), lastPage)
  if (total === 0) {
    return {
      total: 0,
      lastPage: 1,
      currentPage: 1,
      from: 0,
      to: 0,
    }
  }
  const from = (currentPage - 1) * ITEMS_PER_PAGE + 1
  const to = Math.min(currentPage * ITEMS_PER_PAGE, total)
  return { total, lastPage, currentPage, from, to }
})

const canTransactionsPrev = computed(
  () => transactionsPagination.value.currentPage > 1,
)
const canTransactionsNext = computed(
  () =>
    transactionsPagination.value.currentPage < transactionsPagination.value.lastPage,
)

const canLogsPrev = computed(() => logsPagination.value.currentPage > 1)
const canLogsNext = computed(
  () => logsPagination.value.currentPage < logsPagination.value.lastPage,
)

const paginatedTransactions = computed(() => {
  const total = transactions.value.length
  const lastPage = pageCount(total)
  const currentPage = Math.min(Math.max(1, transactionsPage.value), lastPage)
  const start = (currentPage - 1) * ITEMS_PER_PAGE
  return transactions.value.slice(start, start + ITEMS_PER_PAGE)
})

const paginatedLogs = computed(() => {
  const total = importLogs.value.length
  const lastPage = pageCount(total)
  const currentPage = Math.min(Math.max(1, logsPage.value), lastPage)
  const start = (currentPage - 1) * ITEMS_PER_PAGE
  return importLogs.value.slice(start, start + ITEMS_PER_PAGE)
})

function goTransactionsPrev(): void {
  if (!canTransactionsPrev.value) return
  transactionsPage.value -= 1
}

function goTransactionsNext(): void {
  if (!canTransactionsNext.value) return
  transactionsPage.value += 1
}

function goLogsPrev(): void {
  if (!canLogsPrev.value) return
  logsPage.value -= 1
}

function goLogsNext(): void {
  if (!canLogsNext.value) return
  logsPage.value += 1
}

const detailUrl = computed(() => {
  const id = importId.value
  if (!id) return ''
  return new URL(`/api/imports/${encodeURIComponent(id)}`, apiBase).href
})

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

function formatAmount(amount: string): string {
  const n = Number(amount)
  if (Number.isNaN(n)) return amount
  return new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n)
}

async function fetchDetail(): Promise<void> {
  if (!detailUrl.value) {
    error.value = 'Brak identyfikatora importu.'
    return
  }

  loading.value = true
  error.value = ''
  try {
    const response = await fetch(detailUrl.value, {
      headers: { Accept: 'application/json' },
    })
    if (response.status === 404) {
      error.value = 'Nie znaleziono importu.'
      importSummary.value = null
      transactions.value = []
      importLogs.value = []
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
    importLogs.value = body.data.import_logs ?? []
    transactionsPage.value = 1
    logsPage.value = 1
  } catch (e) {
    error.value = `Błąd sieci: ${(e as Error).message}`
  } finally {
    loading.value = false
  }
}

watch(importId, () => {
  importSummary.value = null
  transactions.value = []
  importLogs.value = []
  transactionsPage.value = 1
  logsPage.value = 1
  void fetchDetail()
})

watch(
  () => transactions.value.length,
  () => {
    const last = pageCount(transactions.value.length)
    if (transactionsPage.value > last) {
      transactionsPage.value = last
    }
  },
)

watch(
  () => importLogs.value.length,
  () => {
    const last = pageCount(importLogs.value.length)
    if (logsPage.value > last) {
      logsPage.value = last
    }
  },
)

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
              <template v-if="transactionsPagination.total > 0">
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
                :disabled="!canTransactionsPrev"
                @click="goTransactionsPrev"
              >
                Poprzednia
              </button>
              <span class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
                Strona {{ transactionsPagination.currentPage }} z {{ transactionsPagination.lastPage }}
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canTransactionsNext"
                @click="goTransactionsNext"
              >
                Następna
              </button>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
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
                  v-for="(row, idx) in paginatedTransactions"
                  :key="`tx-${transactionsPagination.from + idx}-${row.transaction_id}`"
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
                <tr v-if="transactions.length === 0">
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
              <template v-if="logsPagination.total > 0">
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
                :disabled="!canLogsPrev"
                @click="goLogsPrev"
              >
                Poprzednia
              </button>
              <span class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
                Strona {{ logsPagination.currentPage }} z {{ logsPagination.lastPage }}
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                :disabled="!canLogsNext"
                @click="goLogsNext"
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
                  v-for="(row, idx) in paginatedLogs"
                  :key="`log-${logsPagination.from + idx}-${row.transaction_id}`"
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
                <tr v-if="importLogs.length === 0">
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
