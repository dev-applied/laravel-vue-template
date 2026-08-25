<script lang="ts" setup>
import {onMounted} from "vue"
import {useRouter} from "vue-router"
import AppNotificationsBell from "@modules/Notifications/resources/ts/components/AppNotificationsBell.vue"
import useNotifications, {type NotificationItem}
  from "@modules/Notifications/resources/ts/composables/useNotifications"

/**
 * The drop-in half of the module: wires the presentational AppNotificationsBell
 * to the API. Put <NotificationsBell /> in your layout's app bar.
 *
 * AppNotificationsBell stays props-in / events-out so it can be previewed and
 * tested without a backend; this container owns the fetching.
 */
const props = withDefaults(defineProps<{
  /** Unread-count poll interval. 0 disables polling. */
  pollMs?: number
}>(), {
  pollMs: 60_000,
})

const router = useRouter()

const {
  notifications, unreadCount, fetchFeed, fetchUnreadCount, markRead, markAllRead,
} = useNotifications({pollMs: props.pollMs || undefined})

onMounted(async () => {
  await fetchUnreadCount()
  await fetchFeed()
})

async function onItemClick(item: NotificationItem) {
  await markRead(item)
  if (item.url) await router.push(item.url)
}

async function onViewAll() {
  await router.push({name: "notifications.index"})
}
</script>

<template>
  <AppNotificationsBell
    :notifications="notifications"
    :unread-count="unreadCount"
    @item-click="onItemClick"
    @mark-all-read="markAllRead"
    @view-all="onViewAll"
  />
</template>
