<template>
  <v-alert
    class="app-announcement-banner mb-2"
    :closable="announcement.dismissible"
    :icon="icon"
    :title="announcement.title"
    :type="announcement.level"
    variant="tonal"
    @click:close="$emit('dismiss', announcement)"
  >
    <div class="text-body-medium">
      {{ announcement.body }}
    </div>

    <template
      v-if="announcement.actionUrl && announcement.actionLabel"
      #append
    >
      <v-btn
        :href="isExternal ? announcement.actionUrl : undefined"
        :target="isExternal ? '_blank' : undefined"
        :to="isExternal ? undefined : announcement.actionUrl"
        size="small"
        variant="tonal"
      >
        {{ announcement.actionLabel }}
      </v-btn>
    </template>
  </v-alert>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {Announcement} from "@modules/Announcements/resources/ts/composables/useAnnouncements"
import {levelIcon} from "@modules/Announcements/resources/ts/composables/useAnnouncements"

export default defineComponent({
  name: "AppAnnouncementBanner",
  props: {
    announcement: {type: Object as PropType<Announcement>, required: true},
  },
  emits: ['dismiss'],
  computed: {
    icon(): string {
      return levelIcon(this.announcement.level)
    },
    isExternal(): boolean {
      // An absolute URL through vue-router silently resolves to a 404 route;
      // it has to become a real anchor instead.
      return /^https?:\/\//i.test(this.announcement.actionUrl || '')
    },
  },
})
</script>
