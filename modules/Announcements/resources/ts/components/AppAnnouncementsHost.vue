<template>
  <div class="app-announcements-host">
    <AppAnnouncementBanner
      v-for="announcement in banners"
      :key="announcement.id"
      :announcement="announcement"
      @dismiss="dismiss"
    />

    <!-- One at a time. Stacked modals are unreadable and the second one
         usually swallows the first one's click. -->
    <AppAnnouncementDialog
      v-if="currentModal"
      :key="currentModal.id"
      :announcement="currentModal"
      @dismiss="dismiss"
    />
  </div>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import useAnnouncements from "@modules/Announcements/resources/ts/composables/useAnnouncements"
import AppAnnouncementBanner from "@modules/Announcements/resources/ts/components/AppAnnouncementBanner.vue"
import AppAnnouncementDialog from "@modules/Announcements/resources/ts/components/AppAnnouncementDialog.vue"

/**
 * Drop once into the app layout. It fetches on mount and renders whatever the
 * current user has not dealt with yet.
 */
export default defineComponent({
  name: "AppAnnouncementsHost",
  components: {AppAnnouncementBanner, AppAnnouncementDialog},
  props: {
    /** Seconds between re-checks. 0 disables polling. */
    pollSeconds: {type: Number, default: 0},
  },
  setup() {
    return useAnnouncements()
  },
  data() {
    return {timer: null as ReturnType<typeof setInterval> | null}
  },
  computed: {
    currentModal() {
      return this.modals[0] ?? null
    },
  },
  mounted() {
    this.fetch()

    if (this.pollSeconds > 0) {
      this.timer = setInterval(() => this.fetch(), this.pollSeconds * 1000)
    }
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer)
  },
})
</script>
