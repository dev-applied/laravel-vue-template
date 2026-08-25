<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AnnouncementFormDialog from "@modules/Announcements/resources/ts/components/AnnouncementFormDialog.vue"
import type {Announcement} from "@modules/Announcements/resources/ts/composables/useAnnouncements"

export default defineComponent({
  name: "AnnouncementsPage",
  components: {AppEmptyState, AppTimeAgo, AppSelect, AnnouncementFormDialog},
  data() {
    return {
      announcements: [] as Announcement[],
      loading:  false,
      saving:   false,
      dialog:   false,
      editing:  null as Announcement | null,
      status:   null as string | null,
      statuses: [
        {title: "All", value: null},
        {title: "Live", value: "live"},
        {title: "Draft", value: "draft"},
        {title: "Scheduled", value: "scheduled"},
        {title: "Expired", value: "expired"},
      ],
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/announcements", {
        params: {status: this.status ?? undefined},
      }).catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.announcements = response.data.data
    },
    create() {
      this.editing = null
      this.dialog = true
    },
    edit(announcement: Announcement) {
      this.editing = announcement
      this.dialog = true
    },
    async saved() {
      this.dialog = false
      await this.load()
    },
    async togglePublish(announcement: Announcement) {
      const action = announcement.publishedAt ? "unpublish" : "publish"
      this.saving = true
      const response = await this.$http.post(`/announcements/${announcement.id}/${action}`).catch(e => e)
      this.saving = false
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
    async remove(announcement: Announcement) {
      // Deleting drops every dismissal row with it — worth a confirm.
      if (!await this.$confirm("Delete announcement?", `"${announcement.title}" will be removed for everyone.`)) return

      const response = await this.$http.delete(`/announcements/${announcement.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
    stateLabel(announcement: Announcement): {text: string, color: string} {
      if (!announcement.publishedAt) return {text: "Draft", color: "default"}
      if (announcement.isLive) return {text: "Live", color: "success"}
      if (announcement.startsAt && new Date(announcement.startsAt) > new Date()) {
        return {text: "Scheduled", color: "info"}
      }
      return {text: "Expired", color: "warning"}
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center flex-wrap ga-2 mb-4">
      <h1 class="text-h4">
        Announcements
      </h1>
      <v-spacer />
      <v-btn
        color="primary"
        prepend-icon="add"
        @click="create"
      >
        New announcement
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text>
        <AppSelect
          v-model="status"
          hide-details
          :items="statuses"
          label="Status"
          style="max-width: 240px"
          @update:model-value="load"
        />
      </v-card-text>
    </v-card>

    <v-card>
      <v-progress-linear
        v-show="loading || saving"
        indeterminate
      />

      <v-list
        v-if="announcements.length"
        lines="two"
      >
        <v-list-item
          v-for="announcement in announcements"
          :key="announcement.id"
          :title="announcement.title"
        >
          <template #subtitle>
            <div class="text-truncate">
              {{ announcement.body }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Created <AppTimeAgo :value="announcement.createdAt" />
              <template v-if="announcement.endsAt">
                · ends <AppTimeAgo :value="announcement.endsAt" />
              </template>
            </div>
          </template>
          <template #prepend>
            <v-avatar
              :color="announcement.level"
              variant="tonal"
            >
              <v-icon :icon="announcement.placement === 'modal' ? 'web_asset' : 'campaign'" />
            </v-avatar>
          </template>

          <template #append>
            <div class="d-flex align-center ga-2">
              <v-chip
                v-if="announcement.dismissalCount"
                density="comfortable"
                prepend-icon="visibility"
                size="small"
                variant="tonal"
              >
                {{ announcement.dismissalCount }}
              </v-chip>

              <v-chip
                :color="stateLabel(announcement).color"
                density="comfortable"
                size="small"
              >
                {{ stateLabel(announcement).text }}
              </v-chip>

              <v-btn
                :icon="announcement.publishedAt ? 'visibility_off' : 'send'"
                :aria-label="announcement.publishedAt ? 'Unpublish announcement' : 'Publish announcement'"
                size="small"
                variant="text"
                @click="togglePublish(announcement)"
              />
              <v-btn
                icon="edit"
                aria-label="Edit announcement"
                size="small"
                variant="text"
                @click="edit(announcement)"
              />
              <v-btn
                color="error"
                icon="delete"
                aria-label="Delete announcement"
                size="small"
                variant="text"
                @click="remove(announcement)"
              />
            </div>
          </template>
        </v-list-item>
      </v-list>

      <AppEmptyState
        v-else-if="!loading"
        icon="campaign"
        description="Announcements appear as a banner or a modal until each person dismisses them."
        title="No announcements yet"
      />
    </v-card>

    <AnnouncementFormDialog
      v-model="dialog"
      :announcement="editing"
      @saved="saved"
    />
  </v-container>
</template>
