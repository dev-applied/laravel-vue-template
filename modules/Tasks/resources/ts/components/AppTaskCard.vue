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
  },
  emits: ['open', 'dragstart'],
  computed: {
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
    @click="$emit('open', task)"
    @dragstart="$emit('dragstart', task, $event)"
  >
    <v-card-text class="pa-3">
      <div class="d-flex align-start ga-2">
        <div class="flex-grow-1 min-width-0">
          <div
            class="text-body-2"
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
              class="text-caption"
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
          <span class="text-caption">{{ initials }}</span>
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
