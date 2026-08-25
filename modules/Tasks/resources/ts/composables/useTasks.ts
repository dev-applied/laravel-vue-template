import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export type TaskStatus = 'todo' | 'in_progress' | 'blocked' | 'done' | 'cancelled'

export interface Task {
  id:            number
  title:         string
  description?:  string | null
  status:        TaskStatus
  priority:      'low' | 'normal' | 'high' | 'urgent'
  dueAt?:        string | null
  completedAt?:  string | null
  isOverdue:     boolean
  isClosed:      boolean
  /** Exactly the statuses this task may move to. The UI offers no others. */
  nextStatuses:  TaskStatus[]
  position:      number
  assignee?:     {id: number, name: string} | null
  taskableType?: string | null
  taskableId?:   number | null
  createdAt?:    string | null
}

export const STATUS_LABELS: Record<TaskStatus, string> = {
  todo:        'To do',
  in_progress: 'In progress',
  blocked:     'Blocked',
  done:        'Done',
  cancelled:   'Cancelled',
}

export const PRIORITY_COLORS: Record<string, string> = {
  low: 'default', normal: 'info', high: 'warning', urgent: 'error',
}

export default function useTasks(defaults: Record<string, unknown> = {}) {
  const {$http, $error} = useHttp()

  const tasks   = shallowRef<Task[]>([])
  const loading = ref(false)
  const loaded  = ref(false)

  const overdueCount = computed(() => tasks.value.filter((t) => t.isOverdue).length)

  function byStatus(status: TaskStatus): Task[] {
    return tasks.value
      .filter((t) => t.status === status)
      .sort((a, b) => a.position - b.position || a.id - b.id)
  }

  async function fetch(params: Record<string, unknown> = {}): Promise<void> {
    loading.value = true

    const response = await $http.get('/tasks', {params: {...defaults, ...params}}).catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    tasks.value = response.data.data
    loaded.value = true
  }

  async function create(payload: Record<string, unknown>): Promise<Task | null> {
    const response = await $http.post('/tasks', {...defaults, ...payload}).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return null

    tasks.value = [...tasks.value, response.data]

    return response.data
  }

  async function update(task: Task, payload: Record<string, unknown>): Promise<boolean> {
    const response = await $http.put(`/tasks/${task.id}`, {title: task.title, ...payload}).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    replace(response.data)

    return true
  }

  /**
   * Status + position in one call, for the board.
   *
   * Optimistic, then reconciled: a drag has to land instantly or it feels
   * broken, but the server is what decides whether the transition was legal.
   */
  async function move(task: Task, status: TaskStatus, position: number): Promise<boolean> {
    const before = {...task}

    replace({...task, status, position})

    const response = await $http.post(`/tasks/${task.id}/move`, {status, position}).catch((e: any) => e)

    if ($error(response.status, response.data?.message, response.data?.errors)) {
      replace(before)   // an illegal transition snaps the card back

      return false
    }

    replace(response.data)

    return true
  }

  async function remove(task: Task): Promise<boolean> {
    const response = await $http.delete(`/tasks/${task.id}`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    tasks.value = tasks.value.filter((t) => t.id !== task.id)

    return true
  }

  function replace(task: Task): void {
    tasks.value = tasks.value.map((t) => (t.id === task.id ? task : t))
  }

  return {tasks, loading, loaded, overdueCount, byStatus, fetch, create, update, move, remove}
}
