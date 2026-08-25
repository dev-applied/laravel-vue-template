<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import useTasks, {PRIORITY_COLORS, STATUS_LABELS, type Task} from "@modules/Tasks/resources/ts/composables/useTasks"
import {ROUTES} from "@modules/Tasks/resources/ts/routes"

// Same detection routes.ts uses to decide whether to REGISTER /tasks/board. The
// `list` choice deletes TaskBoardPage.vue, so without this the toggle would be a
// visible button that navigates to a route which does not exist — the module's
// own rule is that a visible button can never produce an error.
const boardGlob = import.meta.glob("/modules/Tasks/resources/ts/pages/TaskBoardPage.vue")
const hasBoard  = !!boardGlob["/modules/Tasks/resources/ts/pages/TaskBoardPage.vue"]

export default defineComponent({
  name: "TasksPage",
  components: {AppEmptyState, AppTimeAgo, AppSelect, AppTextField},
  setup() {
    return useTasks()
  },
  data() {
    return {
      hasBoard,
      ROUTES,
      filters: {status: null as string | null, search: "", mine: false, overdue: false},
      newTitle: "",
      creating: false,
      statuses: [
        {title: "All statuses", value: null},
        ...Object.entries(STATUS_LABELS).map(([value, title]) => ({title, value})),
      ],
    }
  },
  mounted() {
    this.load()
  },
  methods: {
    load() {
      this.fetch({
        status:  this.filters.status ?? undefined,
        search:  this.filters.search || undefined,
        mine:    this.filters.mine ? 1 : undefined,
        overdue: this.filters.overdue ? 1 : undefined,
      })
    },
    async add() {
      if (!this.newTitle.trim()) return

      this.creating = true
      const task = await this.create({title: this.newTitle.trim()})
      this.creating = false

      if (task) this.newTitle = ""
    },
    async advance(task: Task, status: string) {
      await this.update(task, {status})
    },
    async destroy(task: Task) {
      if (!await this.$confirm("Delete task?", `"${task.title}" will be removed.`)) return
      await this.remove(task)
    },
    statusLabel(status: string): string {
      return STATUS_LABELS[status as keyof typeof STATUS_LABELS] ?? status
    },
    priorityColor(priority: string): string {
      return PRIORITY_COLORS[priority] ?? 'default'
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4 ga-3">
      <h1 class="text-h4">
        Tasks
      </h1>
      <v-spacer />
      <!-- Only in the list+kanban variant. The board shipped with nothing
           linking to it in either direction, so the option's whole point was
           reachable only by typing the URL. -->
      <v-btn
        v-if="hasBoard"
        prepend-icon="view_kanban"
        variant="tonal"
        @click="$router.push($routeTo(ROUTES.TASK_BOARD))"
      >
        Board view
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text class="d-flex ga-3 flex-wrap align-center">
        <AppTextField
          v-model="filters.search"
          clearable
          density="compact"
          hide-details
          label="Search"
          name="search"
          style="max-width: 240px"
          @update:model-value="load"
        />
        <AppSelect
          v-model="filters.status"
          density="compact"
          hide-details
          :items="statuses"
          label="Status"
          style="max-width: 200px"
          @update:model-value="load"
        />
        <v-switch
          v-model="filters.mine"
          color="primary"
          density="compact"
          hide-details
          label="Mine"
          @update:model-value="load"
        />
        <v-switch
          v-model="filters.overdue"
          color="error"
          density="compact"
          hide-details
          label="Overdue"
          @update:model-value="load"
        />
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-text class="d-flex ga-2 align-center">
        <AppTextField
          v-model="newTitle"
          density="compact"
          hide-details
          label="Add a task"
          name="newTitle"
          @keyup.enter="add"
        />
        <v-btn
          color="primary"
          :disabled="!newTitle.trim()"
          :loading="creating"
          variant="flat"
          @click="add"
        >
          Add
        </v-btn>
      </v-card-text>

      <v-divider />

      <v-progress-linear
        v-show="loading"
        indeterminate
      />

      <v-list
        v-if="tasks.length"
        lines="two"
      >
        <v-list-item
          v-for="task in tasks"
          :key="task.id"
          :class="{'text-medium-emphasis': task.isClosed}"
        >
          <template #title>
            <span :class="{'text-decoration-line-through': task.isClosed}">{{ task.title }}</span>
          </template>

          <template #subtitle>
            <div class="d-flex align-center ga-2 flex-wrap">
              <v-chip
                density="comfortable"
                size="x-small"
                variant="tonal"
              >
                {{ statusLabel(task.status) }}
              </v-chip>
              <v-chip
                v-if="task.priority !== 'normal'"
                :color="priorityColor(task.priority)"
                density="comfortable"
                size="x-small"
                variant="tonal"
              >
                {{ task.priority }}
              </v-chip>
              <span
                v-if="task.dueAt"
                :class="task.isOverdue ? 'text-error' : ''"
              >
                due <AppTimeAgo :value="task.dueAt" />
              </span>
              <span v-if="task.assignee">· {{ task.assignee.name }}</span>
            </div>
          </template>

          <template #append>
            <div class="d-flex align-center ga-1">
              <!-- Only the statuses the server says are legal, so a visible
                   button can never produce a 422. -->
              <v-btn
                v-for="next in task.nextStatuses"
                :key="next"
                size="x-small"
                variant="text"
                @click="advance(task, next)"
              >
                {{ statusLabel(next) }}
              </v-btn>
              <!-- Only rendered when the API would accept it. A visible button
                   that 403s is the failure this module's README rules out. -->
              <v-btn
                v-if="task.canDelete"
                color="error"
                icon="delete_outline"
                size="x-small"
                variant="text"
                @click="destroy(task)"
              />
            </div>
          </template>
        </v-list-item>
      </v-list>

      <AppEmptyState
        v-else-if="loaded"
        icon="check_circle_outline"
        description="Add one above, or attach tasks to a record with the HasTasks trait."
        title="No tasks"
      />
    </v-card>
  </v-container>
</template>
