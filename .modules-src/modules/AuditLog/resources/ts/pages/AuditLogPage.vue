<script lang="ts">
import {defineComponent} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import type {AuditEntry} from "@modules/AuditLog/resources/ts/components/AuditTrail.vue"

/** Project-wide trail. Gated server-side by the project's viewAuditLog gate. */
export default defineComponent({
  name: "AuditLogPage",
  components: {AppTimeAgo, AppEmptyState, AppSelect},
  data() {
    return {
      entries: [] as AuditEntry[],
      loading: false,
      filters: {event: null as string | null},
      events: [
        {title: "All events", value: null},
        {title: "Created", value: "created"},
        {title: "Updated", value: "updated"},
        {title: "Deleted", value: "deleted"},
        {title: "Restored", value: "restored"},
      ],
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http
        .get("/audit-logs", {params: {event: this.filters.event ?? undefined}})
        .catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.entries = response.data.data
    },
    eventColor(event: string): string {
      return {created: "success", updated: "info", deleted: "error", restored: "warning"}[event] ?? "default"
    },
    summarise(entry: AuditEntry): string {
      if (!entry.changes.length) return "—"

      return entry.changes.map(c => c.field).join(", ")
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4 ga-4">
      <h1 class="text-headline-large">
        Audit log
      </h1>
      <v-spacer />
      <AppSelect
        v-model="filters.event"
        :items="events"
        label="Event"
        density="compact"
        hide-details
        style="max-width: 200px"
        @update:model-value="load"
      />
    </div>

    <v-card :loading="loading">
      <v-table v-if="entries.length">
        <thead>
          <tr>
            <th>Event</th>
            <th>Subject</th>
            <th>Fields</th>
            <th>By</th>
            <th>When</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in entries"
            :key="entry.id"
          >
            <td>
              <v-chip
                :color="eventColor(entry.event)"
                size="small"
                label
              >
                {{ entry.event }}
              </v-chip>
            </td>
            <td>{{ entry.subject.type }} #{{ entry.subject.id }}</td>
            <td class="text-body-small">
              {{ summarise(entry) }}
            </td>
            <td>{{ entry.user?.name ?? "system" }}</td>
            <td><AppTimeAgo :value="entry.createdAt" /></td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="history"
        title="Nothing recorded yet"
        description="Add the Auditable trait to a model to start recording changes."
      />
    </v-card>
  </v-container>
</template>
