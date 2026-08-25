<script lang="ts">
import {defineComponent} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import type {NotificationItem} from "@modules/Notifications/resources/ts/composables/useNotifications"

/**
 * Full notification history. Pages are Options API in this codebase — they
 * depend on the this.$* globals, which <script setup> cannot reach. The
 * useNotifications composable is deliberately NOT used here: it owns optimistic
 * state for the bell, whereas this page is a plain paginated list.
 */
export default defineComponent({
  name: "NotificationsPage",
  components: {AppTimeAgo, AppEmptyState},
  data() {
    return {
      notifications: [] as NotificationItem[],
      loading: false,
      unreadOnly: false,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http
        .get("/notifications", {params: this.unreadOnly ? {unread: 1} : {}})
        .catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.notifications = response.data.data
    },
    async toggleFilter() {
      this.unreadOnly = !this.unreadOnly
      await this.load()
    },
    async open(item: NotificationItem) {
      if (!item.readAt) {
        const response = await this.$http.post(`/notifications/${item.id}/read`).catch(e => e)
        if (this.$error(response.status, response.data?.message)) return
        item.readAt = new Date().toISOString()
      }

      if (item.url) await this.$router.push(item.url)
    },
    async markAllRead() {
      const response = await this.$http.post("/notifications/read-all").catch(e => e)
      if (this.$error(response.status, response.data?.message)) return
      await this.load()
    },
    async dismiss(item: NotificationItem) {
      const response = await this.$http.delete(`/notifications/${item.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return
      this.notifications = this.notifications.filter(n => n.id !== item.id)
    },
  },
})
</script>

<template>
  <v-container>
    <v-row>
      <v-col
        cols="12"
        md="8"
      >
        <div class="d-flex align-center mb-4">
          <h1 class="text-h4">
            Notifications
          </h1>
          <v-spacer />
          <v-btn
            variant="text"
            :active="unreadOnly"
            @click="toggleFilter"
          >
            {{ unreadOnly ? "Showing unread" : "Showing all" }}
          </v-btn>
          <v-btn
            variant="text"
            @click="markAllRead"
          >
            Mark all read
          </v-btn>
        </div>

        <v-card :loading="loading">
          <v-list v-if="notifications.length">
            <v-list-item
              v-for="item in notifications"
              :key="item.id"
              :class="{ 'notif--unread': !item.readAt }"
              @click="open(item)"
            >
              <template
                v-if="item.icon"
                #prepend
              >
                <v-icon :color="item.color ?? 'primary'">
                  {{ item.icon }}
                </v-icon>
              </template>

              <v-list-item-title>{{ item.title }}</v-list-item-title>
              <v-list-item-subtitle v-if="item.body">
                {{ item.body }}
              </v-list-item-subtitle>

              <template #append>
                <span class="text-caption text-medium-emphasis mr-2">
                  <AppTimeAgo :value="item.createdAt" />
                </span>
                <v-btn
                  icon="close"
                  size="x-small"
                  variant="text"
                  aria-label="Dismiss notification"
                  @click.stop="dismiss(item)"
                />
              </template>
            </v-list-item>
          </v-list>

          <AppEmptyState
            v-else-if="!loading"
            icon="inbox"
            title="Nothing here"
            :description="unreadOnly ? 'No unread notifications.' : 'You have no notifications yet.'"
          />
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<style lang="scss" scoped>
.notif--unread {
  background-color: rgba(var(--v-theme-primary), 0.06);
}
</style>
