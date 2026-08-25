<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppTaskCard from "@modules/Tasks/resources/ts/components/AppTaskCard.vue"
import {STATUS_LABELS, type Task, type TaskStatus} from "@modules/Tasks/resources/ts/composables/useTasks"

/**
 * One board column. Drop target for cards dragged from other columns.
 */
export default defineComponent({
  name: "AppTaskColumn",
  components: {AppTaskCard},
  props: {
    status: {type: String as PropType<TaskStatus>, required: true},
    tasks:  {type: Array as PropType<Task[]>, default: () => []},
    /** The card currently being dragged, so the column can refuse it up front. */
    dragging: {type: Object as PropType<Task | null>, default: null},
    /** Passed through to the cards — see AppTaskCard's `clickable`. */
    clickable: {type: Boolean, default: false},
  },
  emits: ['drop', 'dragstart', 'open'],
  data() {
    return {over: false}
  },
  computed: {
    label(): string {
      return STATUS_LABELS[this.status] ?? this.status
    },
    accepts(): boolean {
      if (!this.dragging) return false
      if (this.dragging.status === this.status) return true

      // Refuse up front rather than letting the drop 422 — the server says
      // exactly which moves are legal, so the column can grey itself out.
      return this.dragging.nextStatuses.includes(this.status)
    },
  },
  methods: {
    onDragOver(event: DragEvent) {
      if (!this.accepts) return

      // preventDefault is what marks this a valid drop target.
      event.preventDefault()
      this.over = true
    },
    onDrop(event: DragEvent) {
      this.over = false
      if (!this.accepts) return

      event.preventDefault()
      this.$emit('drop', this.status, this.tasks.length)
    },
  },
})
</script>

<template>
  <div
    class="app-task-column pa-2 rounded"
    :class="{'app-task-column--over': over, 'app-task-column--blocked': dragging && !accepts}"
    @dragenter.prevent
    @dragleave="over = false"
    @dragover="onDragOver"
    @drop="onDrop"
  >
    <div class="d-flex align-center ga-2 mb-2 px-1">
      <span class="text-subtitle-2">{{ label }}</span>
      <v-chip
        density="comfortable"
        size="x-small"
        variant="tonal"
      >
        {{ tasks.length }}
      </v-chip>
    </div>

    <AppTaskCard
      :clickable="clickable"
      v-for="task in tasks"
      :key="task.id"
      draggable
      :task="task"
      @dragstart="(t, e) => $emit('dragstart', t, e)"
      @open="$emit('open', $event)"
    />

    <div
      v-show="!tasks.length"
      class="text-caption text-disabled text-center py-6"
    >
      Nothing here
    </div>
  </div>
</template>

<style scoped lang="scss">
.app-task-column {
  background: rgba(var(--v-theme-on-surface), 0.04);
  min-height: 200px;
  transition: background 120ms ease, opacity 120ms ease;
}

.app-task-column--over {
  background: rgba(var(--v-theme-primary), 0.12);
}

.app-task-column--blocked {
  opacity: 0.45;
}
</style>
