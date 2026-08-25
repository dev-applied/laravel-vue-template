import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export type WidgetType = 'stat' | 'queue' | 'activity'

export interface StatData {
  value:     number | string
  suffix?:   string | null
  caption?:  string | null
  /** Percentage change vs the previous period. Negative renders as a down arrow. */
  change?:   number | null
  url?:      string | null
}

export interface QueueItem {
  id:        number | string
  title:     string
  subtitle?: string | null
  url?:      string | null
  badge?:    string | null
  color?:    string | null
}

export interface ActivityItem {
  id:        number | string
  title:     string
  subtitle?: string | null
  icon?:     string | null
  color?:    string | null
  at:        string
}

export interface DashboardWidget {
  key:   string
  label: string
  type:  WidgetType
  icon?: string | null
  color?: string | null
  data:  StatData | {items: QueueItem[]; total?: number} | {items: ActivityItem[]} | null
  /** Set when this one panel failed. The rest of the dashboard still rendered. */
  error: string | null
}

/**
 * The whole dashboard in one request.
 *
 * A tile-per-request dashboard fires eight round trips on load and feels slow
 * no matter how fast each one is; the endpoint batches instead. `only` exists
 * for the refresh-one-tile case so a poll doesn't re-resolve everything.
 */
export default function useDashboard() {
  const {$http, $error} = useHttp()

  const widgets = shallowRef<DashboardWidget[]>([])
  const loading = ref(false)
  const loaded  = ref(false)

  const stats      = computed(() => widgets.value.filter((w) => w.type === 'stat'))
  const queues     = computed(() => widgets.value.filter((w) => w.type === 'queue'))
  const activities = computed(() => widgets.value.filter((w) => w.type === 'activity'))

  function widget(key: string): DashboardWidget | undefined {
    return widgets.value.find((w) => w.key === key)
  }

  async function fetch(only: string[] = []): Promise<void> {
    loading.value = true

    const response = await $http
      .get('/dashboard', {params: only.length ? {only} : {}})
      .catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    const incoming: DashboardWidget[] = response.data.widgets

    // A narrowed refresh must patch in place, not replace the list — otherwise
    // refreshing one tile blanks the other seven.
    widgets.value = only.length
      ? widgets.value.map((existing) => incoming.find((w) => w.key === existing.key) ?? existing)
      : incoming

    loaded.value = true
  }

  return {widgets, stats, queues, activities, loading, loaded, widget, fetch}
}
