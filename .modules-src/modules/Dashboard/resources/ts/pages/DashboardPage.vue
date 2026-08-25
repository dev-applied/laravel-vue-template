<template>
  <v-container fluid>
    <div class="d-flex align-center flex-wrap ga-2 mb-4">
      <h1 class="text-headline-small">
        {{ title }}
      </h1>
      <v-spacer />
      <v-btn
        :loading="loading"
        icon="refresh"
        aria-label="Refresh the dashboard"
        size="small"
        variant="text"
        @click="fetch()"
      />
    </div>

    <!-- Skeletons only on the very first load. A refresh keeps the numbers on
         screen instead of flashing the whole grid back to grey. -->
    <v-row v-if="!loaded">
      <v-col
        v-for="n in 4"
        :key="n"
        cols="12"
        md="3"
        sm="6"
      >
        <v-skeleton-loader type="article" />
      </v-col>
    </v-row>

    <template v-else>
      <v-row v-if="stats.length">
        <v-col
          v-for="stat in stats"
          :key="stat.key"
          cols="12"
          md="3"
          sm="6"
        >
          <AppStatTile :widget="stat" />
        </v-col>
      </v-row>

      <!--
        The chart slot. This module ships no charting library on purpose — a
        project picks its own and drops it in here, so the shell never forces a
        dependency or a second one alongside whatever is already installed.
      -->
      <v-row v-if="$slots.charts">
        <v-col cols="12">
          <slot
            name="charts"
            :widgets="widgets"
          />
        </v-col>
      </v-row>

      <v-row>
        <v-col
          v-for="queue in queues"
          :key="queue.key"
          cols="12"
          :md="queues.length > 1 ? 6 : 12"
        >
          <AppActionQueue :widget="queue" />
        </v-col>

        <v-col
          v-for="activity in activities"
          :key="activity.key"
          cols="12"
          :md="activities.length > 1 ? 6 : 12"
        >
          <AppActivityFeed :widget="activity" />
        </v-col>
      </v-row>

      <v-alert
        v-if="!widgets.length"
        class="mt-2"
        type="info"
        variant="tonal"
      >
        No dashboard widgets are registered yet. Register them from a service
        provider with <code>DashboardRegistry::stat()</code>,
        <code>::queue()</code> or <code>::activity()</code>.
      </v-alert>
    </template>
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import useDashboard from "@modules/Dashboard/resources/ts/composables/useDashboard"
import AppStatTile from "@modules/Dashboard/resources/ts/components/AppStatTile.vue"
import AppActionQueue from "@modules/Dashboard/resources/ts/components/AppActionQueue.vue"
import AppActivityFeed from "@modules/Dashboard/resources/ts/components/AppActivityFeed.vue"

export default defineComponent({
  name: "DashboardPage",
  components: {AppStatTile, AppActionQueue, AppActivityFeed},
  props: {
    title: {type: String, default: "Dashboard"},
    /** Seconds between silent refreshes. 0 disables polling. */
    pollSeconds: {type: Number, default: 0},
  },
  setup() {
    return useDashboard()
  },
  data() {
    return {
      timer: null as ReturnType<typeof setInterval> | null,
    }
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
