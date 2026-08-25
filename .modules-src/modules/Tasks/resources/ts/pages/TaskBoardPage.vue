<script lang="ts">
import {defineComponent} from "vue"
import AppTaskColumn from "@modules/Tasks/resources/ts/components/AppTaskColumn.vue"
import useTasks, {type Task, type TaskStatus} from "@modules/Tasks/resources/ts/composables/useTasks"
import {ROUTES} from "@modules/Tasks/resources/ts/routes"

export default defineComponent({
  name: "TaskBoardPage",
  components: {AppTaskColumn},
  setup() {
    return useTasks()
  },
  data() {
    // No glob guard in this direction: TasksPage exists in every variant, so
    // the way back is always safe to render.
    return {
      ROUTES,
      dragging: null as Task | null,
      columns: ['todo', 'in_progress', 'blocked', 'done'] as TaskStatus[],
    }
  },
  mounted() {
    this.fetch({open: undefined})
  },
  methods: {
    onDragStart(task: Task, event: DragEvent) {
      this.dragging = task
      // Firefox will not start a drag without data set.
      event.dataTransfer?.setData('text/plain', String(task.id))
      if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
    },
    async onDrop(status: TaskStatus, position: number) {
      const task = this.dragging
      this.dragging = null
      if (!task || task.status === status) return

      await this.move(task, status, position)
    },
  },
})
</script>

<template>
  <v-container fluid>
    <div class="d-flex align-center ga-2 mb-4">
      <v-btn
        aria-label="Back to the task list"
        icon="list"
        variant="text"
        @click="$router.push($routeTo(ROUTES.TASKS))"
      />
      <h1 class="text-h5">
        Board
      </h1>
      <v-chip
        v-if="overdueCount"
        color="error"
        density="comfortable"
        size="small"
        variant="tonal"
      >
        {{ overdueCount }} overdue
      </v-chip>
      <v-spacer />
      <v-btn
        :loading="loading"
        icon="refresh"
        size="small"
        variant="text"
        @click="fetch({open: undefined})"
      />
    </div>

    <!-- The whole grid stays mounted while dragging. Swapping columns with
         v-if would tear out the element the pointer is over. -->
    <v-row>
      <v-col
        v-for="status in columns"
        :key="status"
        cols="12"
        md="3"
        sm="6"
      >
        <AppTaskColumn
          :dragging="dragging"
          :status="status"
          :tasks="byStatus(status)"
          @dragstart="onDragStart"
          @drop="onDrop"
        />
      </v-col>
    </v-row>
  </v-container>
</template>
