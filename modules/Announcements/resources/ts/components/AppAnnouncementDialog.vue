<template>
  <v-dialog
    :model-value="true"
    max-width="560"
    :persistent="announcement.requiresAcknowledgement || !announcement.dismissible"
    @update:model-value="onClose"
  >
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon
          :color="announcement.level"
          :icon="icon"
        />
        <span>{{ announcement.title }}</span>
      </v-card-title>

      <v-card-text class="text-body-large text-pre-wrap">
        {{ announcement.body }}
      </v-card-text>

      <v-card-actions>
        <v-btn
          v-if="announcement.actionUrl && announcement.actionLabel"
          :href="isExternal ? announcement.actionUrl : undefined"
          :target="isExternal ? '_blank' : undefined"
          :to="isExternal ? undefined : announcement.actionUrl"
          variant="text"
        >
          {{ announcement.actionLabel }}
        </v-btn>

        <v-spacer />

        <v-btn
          :color="announcement.level"
          variant="flat"
          @click="$emit('dismiss', announcement)"
        >
          {{ announcement.requiresAcknowledgement ? 'I understand' : 'Got it' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {Announcement} from "@modules/Announcements/resources/ts/composables/useAnnouncements"
import {levelIcon} from "@modules/Announcements/resources/ts/composables/useAnnouncements"

export default defineComponent({
  name: "AppAnnouncementDialog",
  props: {
    announcement: {type: Object as PropType<Announcement>, required: true},
  },
  emits: ['dismiss'],
  computed: {
    icon(): string {
      return levelIcon(this.announcement.level)
    },
    isExternal(): boolean {
      return /^https?:\/\//i.test(this.announcement.actionUrl || '')
    },
  },
  methods: {
    onClose(open: boolean): void {
      // A required announcement is persistent, so this never fires for one —
      // but escape-closing a merely-dismissible modal must still record the
      // dismissal, or it reappears on the next page load.
      if (!open) this.$emit('dismiss', this.announcement)
    },
  },
})
</script>
