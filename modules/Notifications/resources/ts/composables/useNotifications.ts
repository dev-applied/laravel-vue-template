import {computed, onScopeDispose, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface NotificationItem {
  id:        string
  type?:     string
  title:     string
  body?:     string | null
  icon?:     string | null
  color?:    string | null
  url?:      string | null
  readAt?:   string | null
  createdAt: string
}

/**
 * Feed + unread count for the current user, with optional polling.
 *
 * Polling hits /notifications/unread-count, which returns a bare integer rather
 * than a page of rows — cheap enough to run on an interval. The full feed is
 * only fetched when something actually opens it.
 */
export default function useNotifications(options: {pollMs?: number} = {}) {
  const {$http, $error} = useHttp()

  const notifications = shallowRef<NotificationItem[]>([])
  const unreadCount   = ref(0)
  const loading       = ref(false)

  const hasUnread = computed(() => unreadCount.value > 0)

  async function fetchUnreadCount(): Promise<void> {
    const response = await $http.get('/notifications/unread-count').catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    unreadCount.value = response.data.count
  }

  async function fetchFeed(unreadOnly = false): Promise<void> {
    loading.value = true
    const response = await $http
      .get('/notifications', {params: unreadOnly ? {unread: 1} : {}})
      .catch((e: any) => e)
    loading.value = false

    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    notifications.value = response.data.data
  }

  async function markRead(item: NotificationItem): Promise<void> {
    if (item.readAt) return

    // Optimistic: the bell should close and the badge drop immediately.
    const previous = notifications.value
    notifications.value = previous.map(n => n.id === item.id ? {...n, readAt: new Date().toISOString()} : n)
    unreadCount.value = Math.max(0, unreadCount.value - 1)

    const response = await $http.post(`/notifications/${item.id}/read`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) {
      notifications.value = previous
      await fetchUnreadCount()
    }
  }

  async function markAllRead(): Promise<void> {
    const previous = notifications.value
    const stamp = new Date().toISOString()
    notifications.value = previous.map(n => n.readAt ? n : {...n, readAt: stamp})
    unreadCount.value = 0

    const response = await $http.post('/notifications/read-all').catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) {
      notifications.value = previous
      await fetchUnreadCount()
    }
  }

  async function dismiss(item: NotificationItem): Promise<void> {
    const previous = notifications.value
    notifications.value = previous.filter(n => n.id !== item.id)
    if (!item.readAt) unreadCount.value = Math.max(0, unreadCount.value - 1)

    const response = await $http.delete(`/notifications/${item.id}`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) {
      notifications.value = previous
      await fetchUnreadCount()
    }
  }

  let timer: ReturnType<typeof setInterval> | undefined
  if (options.pollMs) {
    timer = setInterval(() => { void fetchUnreadCount() }, options.pollMs)
    // Tie the interval to the consuming component's scope so a torn-down bell
    // doesn't keep polling for the life of the tab.
    onScopeDispose(() => { if (timer) clearInterval(timer) })
  }

  return {
    notifications, unreadCount, loading, hasUnread,
    fetchFeed, fetchUnreadCount, markRead, markAllRead, dismiss,
  }
}
