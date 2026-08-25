<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import {PRIORITY_COLORS, type Task} from "@modules/Tasks/resources/ts/composables/useTasks"

export default defineComponent({
  name: "AppTaskCard",
  components: {AppTimeAgo},
  props: {
    task:      {type: Object as PropType<Task>, required: true},
    draggable: {type: Boolean, default: false},
    /**
     * Whether opening the card does anything.
     *
     * The card emitted `open` and rendered as `v-card--link` unconditionally,
     * so on the board it had pointer-cursor link styling and clicking it did
     * NOTHING — TaskBoardPage never bound the listener, and there is no task
     * detail route for it to reach. A control that looks interactive and is
     * not is worse than no control.
     *
     * A project that adds a detail view sets this and binds @open; that also
     * turns on the keyboard path, because a mouse-only card is not a control
     * either.
     */
    clickable: {type: Boolean, default: false},
  },
  emits: ['open', 'dragstart'],
  computed: {
    /**
     * Bound with `v-on` rather than `@click`, because Vuetify infers
     * `v-card--link` — pointer cursor, hover elevation — from a click listener
     * EXISTING. An inert handler (`@click="clickable && ..."`) still counts, so
     * the card kept advertising a click it would not perform. Absent, not inert.
     */
    openListeners(): Record<string, (e: Event) => void> {
      if (! this.clickable) {
        return {}
      }

      const open = () => this.$emit('open', this.task)

      return {
        click: open,
        keydown: (e: Event) => {
          const key = (e as KeyboardEvent).key

          if (key === 'Enter' || key === ' ') {
            e.preventDefault()
            open()
          }
        },
      }
    },
    priorityColor(): string {
      return PRIORITY_COLORS[this.task.priority] ?? 'default'
    },
    initials(): string {
      const name = this.task.assignee?.name ?? ""
      return name.split(/\s+/).filter(Boolean).map((p) => p[0]).slice(0, 2).join("").toUpperCase()
    },
  },
})
</script>

<template>
  <v-card
    class="app-task-card mb-2"
    :draggable="draggable"
    :variant="task.isClosed ? 'tonal' : 'elevated'"
    :role="clickable ? 'button' : undefined"
    :tabindex="clickable ? 0 : undefined"
    v-on="openListeners"
    @dragstart="$emit('dragstart', task, $event)"
  >
    <v-card-text class="pa-3">
      <div class="d-flex align-start ga-2">
        <div class="flex-grow-1 min-width-0">
          <div
            class="text-body-medium"
            :class="{'text-decoration-line-through text-medium-emphasis': task.isClosed}"
          >
            {{ task.title }}
          </div>

          <div class="d-flex align-center ga-2 mt-1 flex-wrap">
            <v-chip
              v-if="task.priority !== 'normal'"
              :color="priorityColor"
              density="comfortable"
              size="x-small"
              variant="tonal"
            >
              {{ task.priority }}
            </v-chip>

            <!-- v-show, not v-if: this card carries drag listeners, and
                 tearing the subtree down mid-interaction kills the drag. -->
            <span
              v-show="task.dueAt"
              class="text-body-small"
              :class="task.isOverdue ? 'text-error' : 'text-medium-emphasis'"
            >
              <v-icon
                icon="schedule"
                size="12"
              />
              <AppTimeAgo :value="task.dueAt" />
            </span>
          </div>
        </div>

        <v-avatar
          v-if="task.assignee"
          color="primary"
          size="28"
          variant="tonal"
        >
          <span class="text-body-small">{{ initials }}</span>
        </v-avatar>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped lang="scss">
.min-width-0 {
  min-width: 0;
}

.app-task-card[draggable="true"] {
  cursor: grab;
}
</style>
