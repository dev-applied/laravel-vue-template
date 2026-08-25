import {ref} from "vue"
import useHttp from "@/composables/useHttp"

export interface ExportRecord {
  id:           number
  source:       string
  format:       string
  status:       "pending" | "processing" | "completed" | "failed"
  rowCount:     number | null
  error:        string | null
  downloadable: boolean
  fileName:     string
  createdAt:    string
  completedAt:  string | null
}

export interface ExportSourceOption {
  key:   string
  label: string
}

/**
 * Start an export and follow it to completion.
 *
 * Generation is queued, so `start` returns a pending row and the caller polls.
 * Poll interval backs off from 1s to 5s: most exports finish almost
 * immediately, but a large one shouldn't be hammered for minutes.
 */
export default function useExport() {
  const {$http, $error} = useHttp()

  const running = ref(false)
  const current = ref<ExportRecord | null>(null)

  async function sources(): Promise<ExportSourceOption[]> {
    const response = await $http.get('/exports/sources').catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return []

    return response.data.sources
  }

  async function start(
    source: string,
    options: {format?: string, filters?: Record<string, any>} = {},
  ): Promise<ExportRecord | null> {
    running.value = true

    const response = await $http.post('/exports', {
      source,
      format:  options.format ?? 'csv',
      filters: options.filters ?? {},
    }).catch((e: any) => e)

    if ($error(response.status, response.data?.message, response.data?.errors)) {
      running.value = false

      return null
    }

    current.value = response.data.export

    return await poll(current.value!.id)
  }

  async function poll(id: number): Promise<ExportRecord | null> {
    let delay = 1000

    // ~2 minutes of backed-off polling before giving up on the queue worker.
    for (let attempt = 0; attempt < 40; attempt++) {
      await new Promise(resolve => setTimeout(resolve, delay))
      delay = Math.min(delay + 500, 5000)

      const response = await $http.get(`/exports/${id}`).catch((e: any) => e)
      if ($error(response.status, response.data?.message, response.data?.errors, false)) break

      const record: ExportRecord = response.data.export
      current.value = record

      if (record.status === 'completed' || record.status === 'failed') {
        running.value = false

        return record
      }
    }

    running.value = false

    return current.value
  }

  /**
   * The download route is authenticated, so this cannot be a bare <a href>.
   * $http.download carries the auth header and hands the browser a blob.
   */
  function download(record: ExportRecord): void {
    $http.download(`/exports/${record.id}/download`)
  }

  async function remove(record: ExportRecord): Promise<boolean> {
    const response = await $http.delete(`/exports/${record.id}`).catch((e: any) => e)

    return !$error(response.status, response.data?.message, response.data?.errors)
  }

  return {running, current, sources, start, poll, download, remove}
}
