<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextField from "@/components/fields/AppTextField.vue"

interface Ticket {
  id: number, reference: string, name: string, email: string, subject: string,
  status: string, priority: string, isSpam: boolean,
  assignee?: {id: number, name: string} | null, createdAt: string
}

export default defineComponent({
  name: "TicketsPage",
  components: {AppEmptyState, AppTimeAgo, AppSelect, AppTextField},
  data() {
    return {
      tickets: [] as Ticket[],
      loading: false,
      filters: {status: null as string | null, search: "", include_spam: false},
      statuses: [
        {title: "All statuses", value: null},
        {title: "Open", value: "open"},
        {title: "Pending", value: "pending"},
        {title: "Resolved", value: "resolved"},
        {title: "Closed", value: "closed"},
      ],
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/support/tickets", {
        params: {
          status:       this.filters.status ?? undefined,
          search:       this.filters.search || undefined,
          include_spam: this.filters.include_spam ? 1 : undefined,
        },
      }).catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.tickets = response.data.data
    },
    statusColor(status: string): string {
      return {open: "info", pending: "warning", resolved: "success", closed: "default"}[status] ?? "default"
    },
    priorityColor(priority: string): string {
      return {low: "default", normal: "info", high: "warning", urgent: "error"}[priority] ?? "default"
    },
  },
})
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">
      Support queue
    </h1>

    <v-card class="mb-4">
      <v-card-text class="d-flex ga-3 flex-wrap align-center">
        <AppSelect
          v-model="filters.status"
          :items="statuses"
          label="Status"
          density="compact"
          hide-details
          style="max-width: 200px"
          @update:model-value="load"
        />
        <AppTextField
          v-model="filters.search"
          label="Search subject, email or reference"
          density="compact"
          hide-details
          style="min-width: 260px"
          @keyup.enter="load"
        />
        <v-checkbox
          v-model="filters.include_spam"
          label="Include spam"
          density="compact"
          hide-details
          @update:model-value="load"
        />
      </v-card-text>
    </v-card>

    <v-card :loading="loading">
      <v-table v-if="tickets.length">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Subject</th>
            <th>From</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Assignee</th>
            <th>Received</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="ticket in tickets"
            :key="ticket.id"
            style="cursor: pointer"
            @click="$router.push($routeTo(ROUTES.TICKET, {id: ticket.id}))"
          >
            <td class="font-weight-medium">
              {{ ticket.reference }}
            </td>
            <td>
              {{ ticket.subject }}
              <v-chip
                v-if="ticket.isSpam"
                size="x-small"
                color="error"
                label
                class="ml-1"
              >
                spam
              </v-chip>
            </td>
            <td class="text-caption">
              {{ ticket.email }}
            </td>
            <td>
              <v-chip
                :color="statusColor(ticket.status)"
                size="small"
                label
              >
                {{ ticket.status }}
              </v-chip>
            </td>
            <td>
              <v-chip
                :color="priorityColor(ticket.priority)"
                size="small"
                label
              >
                {{ ticket.priority }}
              </v-chip>
            </td>
            <td>{{ ticket.assignee?.name ?? "—" }}</td>
            <td><AppTimeAgo :value="ticket.createdAt" /></td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="support_agent"
        title="Nothing in the queue"
        description="Submissions from the contact form land here."
      />
    </v-card>
  </v-container>
</template>
