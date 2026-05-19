<template>
  <v-menu
    :close-on-content-click="false"
    location="bottom end"
    transition="slide-y-transition"
  >
    <template #activator="{ props: act }">
      <v-btn
        v-bind="act"
        :icon="true"
        variant="text"
        :aria-label="unreadCount ? `${unreadCount} unread notifications` : 'Notifications'"
      >
        <v-badge
          :model-value="unreadCount > 0"
          :content="badgeContent"
          color="error"
          location="top end"
          offset-x="-2"
          offset-y="-2"
        >
          <v-icon>{{ unreadCount ? "notifications_active" : "notifications" }}</v-icon>
        </v-badge>
      </v-btn>
    </template>

    <v-card
      width="380"
      max-width="95vw"
    >
      <v-card-title class="d-flex align-center pa-3">
        Notifications
        <v-spacer />
        <v-btn
          v-if="unreadCount > 0"
          size="small"
          variant="text"
          @click="$emit('markAllRead')"
        >
          Mark all read
        </v-btn>
      </v-card-title>

      <v-divider />

      <div
        v-if="!notifications.length"
        class="pa-6 text-center text-medium-emphasis"
      >
        <v-icon
          size="36"
          class="mb-2"
        >
          inbox
        </v-icon>
        <div class="text-body-2">
          You're all caught up.
        </div>
      </div>

      <v-list
        v-else
        density="compact"
        max-height="420"
        class="overflow-y-auto"
      >
        <v-list-item
          v-for="n in notifications"
          :key="n.id"
          :class="{ 'app-notif--unread': !n.readAt }"
          @click="onItemClick(n)"
        >
          <template
            v-if="n.icon"
            #prepend
          >
            <v-icon :color="n.color ?? 'primary'">
              {{ n.icon }}
            </v-icon>
          </template>
          <v-list-item-title class="text-body-2">
            {{ n.title }}
          </v-list-item-title>
          <v-list-item-subtitle
            v-if="n.body"
            class="text-caption text-truncate"
          >
            {{ n.body }}
          </v-list-item-subtitle>
          <template #append>
            <span class="text-caption text-medium-emphasis">
              <AppTimeAgo :value="n.createdAt" />
            </span>
          </template>
        </v-list-item>
      </v-list>

      <v-divider v-if="notifications.length" />
      <v-card-actions v-if="notifications.length">
        <v-btn
          block
          variant="text"
          @click="$emit('viewAll')"
        >
          View all
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-menu>
</template>

<script lang="ts" setup>
import { computed } from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

export interface NotificationItem {
  id:        string | number
  title:     string
  body?:     string
  icon?:     string
  color?:    string
  readAt?:   string | null
  createdAt: string
}

const props = withDefaults(defineProps<{
  notifications: NotificationItem[]
  unreadCount?:  number
  /** Badge caps at this number for cleaner display. */
  badgeCap?:     number
}>(), {
  unreadCount: 0,
  badgeCap:    99,
})

const emit = defineEmits<{
  /** User clicked a single notification. Caller decides routing / marking. */
  itemClick:   [item: NotificationItem]
  markAllRead: []
  viewAll:     []
}>()

const badgeContent = computed(() =>
  props.unreadCount > props.badgeCap ? `${props.badgeCap}+` : String(props.unreadCount),
)

function onItemClick(item: NotificationItem) {
  emit("itemClick", item)
}
</script>

<style lang="scss" scoped>
.app-notif--unread {
  background-color: rgba(var(--v-theme-primary), 0.06);
}
</style>
