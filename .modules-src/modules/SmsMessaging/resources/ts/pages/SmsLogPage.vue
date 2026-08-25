<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

interface SmsRow {
  id: number
  phone_number: string
  body: string
  status: string
  driver: string | null
  error: string | null
  created_at: string
}

export default defineComponent({
  name: "SmsLogPage",
  components: {AppEmptyState, AppTimeAgo},
  data() {
    return {
      messages: [] as SmsRow[],
      loading: false,
      forbidden: false,
      status: null as string | null,
      search: "",
      statuses: [
        {title: "Accepted by the carrier", value: "accepted"},
        {title: "Suppressed (opted out)", value: "suppressed"},
        {title: "Failed", value: "failed"},
      ],
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/sms/messages", {
        params: {status: this.status, phone_number: this.search || undefined},
      }).catch(e => e)
      this.loading = false

      // A 403 is handled here rather than falling through to the generic error
      // path, because the generic path leaves the page showing "Nothing sent
      // yet" — which tells the user there are no messages when the truth is
      // that they may not see them. Two very different statements.
      if (response.status === 403) {
        this.forbidden = true
        this.messages = []
        return
      }

      this.forbidden = false

      if (this.$error(response.status, response.data?.message)) return

      this.messages = response.data.data
    },
    colour(status: string): string {
      return {accepted: "success", suppressed: "warning", failed: "error"}[status] ?? "default"
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center flex-wrap ga-2 mb-4">
      <h1 class="text-headline-small text-md-headline-medium mb-0">
        SMS log
      </h1>
      <v-spacer />
      <v-text-field
        v-model="search"
        density="compact"
        hide-details
        placeholder="Phone number"
        prepend-inner-icon="search"
        style="max-width: 220px"
        aria-label="Filter by phone number"
        @keyup.enter="load"
      />
      <v-select
        v-model="status"
        :items="statuses"
        clearable
        density="compact"
        hide-details
        placeholder="Any status"
        style="max-width: 220px"
        aria-label="Filter by status"
        @update:model-value="load"
      />
      <v-btn
        variant="text"
        prepend-icon="refresh"
        @click="load"
      >
        Refresh
      </v-btn>
    </div>

    <v-card :loading="loading">
      <v-table v-if="messages.length">
        <thead>
          <tr>
            <th>To</th>
            <th>Message</th>
            <th>Status</th>
            <th>Driver</th>
            <th>When</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in messages"
            :key="row.id"
          >
            <td class="text-no-wrap">
              {{ row.phone_number }}
            </td>
            <td>
              {{ row.body }}
              <div
                v-if="row.error"
                class="text-body-small text-medium-emphasis"
              >
                {{ row.error }}
              </div>
            </td>
            <td>
              <v-chip
                :color="colour(row.status)"
                size="small"
                label
              >
                {{ row.status }}
              </v-chip>
            </td>
            <td>{{ row.driver }}</td>
            <td><AppTimeAgo :value="row.created_at" /></td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading && forbidden"
        icon="lock"
        title="You cannot read the SMS log"
        description="It holds phone numbers and message bodies, so access is off by default. Ask an administrator to grant the view-sms-log permission."
      />

      <AppEmptyState
        v-else-if="!loading"
        icon="sms"
        title="Nothing sent yet"
        description="Messages appear here as soon as the app texts somebody — including the ones it refused to send."
      />
    </v-card>
  </v-container>
</template>
