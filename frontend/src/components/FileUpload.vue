<script setup lang="ts">
import { computed, ref } from 'vue'

const fileInputRef = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const uploadStatus = ref('')
const isUploading = ref(false)

const allowedExtensions = ['.json', '.csv', '.xml']

const importsUploadUrl = new URL(
  '/api/imports',
  import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || 'http://localhost:8080',
).href

/** 4xx – nie pokazujemy treści z backendu (np. szczegółów walidacji). */
const clientErrorMessage =
  'Nie udało się przesłać pliku. Sprawdź format pliku i spróbuj ponownie.'

function extensionOf(name: string): string {
  const i = name.lastIndexOf('.')
  return i >= 0 ? name.slice(i).toLowerCase() : ''
}

function isAllowedFile(file: File): boolean {
  const ext = extensionOf(file.name)
  if (allowedExtensions.includes(ext)) return true
  const t = file.type
  return (
    t === 'application/json' ||
    t === 'text/csv' ||
    t === 'application/xml' ||
    t === 'text/xml' ||
    t === 'application/vnd.ms-excel'
  )
}

const canUpload = computed(() => selectedFile.value !== null && !isUploading.value)

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) {
    selectedFile.value = null
    return
  }
  if (!isAllowedFile(file)) {
    selectedFile.value = null
    uploadStatus.value = 'Dozwolone formaty: JSON, CSV, XML.'
    if (fileInputRef.value) fileInputRef.value.value = ''
    return
  }
  selectedFile.value = file
  uploadStatus.value = ''
}

async function handleUpload() {
  if (!selectedFile.value) {
    uploadStatus.value = 'Wybierz plik.'
    return
  }

  uploadStatus.value = ''
  isUploading.value = true

  const formData = new FormData()
  formData.append('file', selectedFile.value)

  try {
    const response = await fetch(importsUploadUrl, {
      method: 'POST',
      body: formData,
    })

    if (response.ok) {
      uploadStatus.value = 'Plik został przesłany.'
      selectedFile.value = null
      if (fileInputRef.value) fileInputRef.value.value = ''
      return
    }

    const status = response.status
    if (status >= 400 && status < 500) {
      await response.text().catch(() => undefined)
      uploadStatus.value = clientErrorMessage
      return
    }

    const contentType = response.headers.get('content-type') ?? ''
    let message = response.statusText
    if (contentType.includes('application/json')) {
      const data = (await response.json()) as { message?: string }
      message = data.message ?? message
    } else {
      const text = await response.text()
      message = text.slice(0, 200) || message
    }
    uploadStatus.value = `Błąd: ${message}`
  } catch (error) {
    uploadStatus.value = `Błąd sieci: ${(error as Error).message}`
  } finally {
    isUploading.value = false
  }
}
</script>

<template>
  <div
    class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/50"
  >
    <label class="flex flex-col gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
      Plik do importu
      <input
        ref="fileInputRef"
        class="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-indigo-500 dark:hover:file:bg-indigo-400"
        type="file"
        accept=".json,.csv,.xml,application/json,text/csv,application/xml,text/xml"
        @change="handleFileChange"
      />
    </label>

    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="!canUpload"
        @click="handleUpload"
      >
        {{ isUploading ? 'Wysyłanie…' : 'Wyślij' }}
      </button>
      <span
        v-if="selectedFile"
        class="text-sm text-slate-600 dark:text-slate-400"
      >
        {{ selectedFile.name }}
      </span>
    </div>

    <p
      v-if="uploadStatus"
      class="text-sm text-slate-700 dark:text-slate-300"
      role="status"
    >
      {{ uploadStatus }}
    </p>
  </div>
</template>
