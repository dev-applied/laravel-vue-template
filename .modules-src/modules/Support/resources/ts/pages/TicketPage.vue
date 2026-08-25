<script lang="ts">
import {defineComponent} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"

interface Reply {id: number, body: string, isInternal: boolean, author: string | null, createdAt: string}
interface Ticket {
  id: number, reference: string, name: string, email: string, subject: string, body: string,
  status: string, priority: string, replies: Reply[], createdAt: string
}

export default defineComponent({
  name: "TicketPage",
  components: {AppTimeAgo, AppSelect, AppTextarea},
  props: {id: {type: [String, Number], required: true}},
  data() {
    return {
      ticket: null as Ticket | null,
      loading: false,
      reply: {body: "", is_internal: false},
      sending: false,
      statuses: ["open", "pending", "resolved", "closed"].map(s => ({title: s, value: s})),
      priorities: ["low", "normal", "high", "urgent"].map(p => ({title: p, value: p})),
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get(`/support/tickets/${this.id}`).catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.ticket = response.data.ticket
    },
    async patch(payload: Record<string, unknown>) {
      const response = await this.$http.put(`/support/tickets/${this.id}`, payload).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
    async send() {
      if (!this.reply.body) return

      this.sending = true
      const response = await this.$http
        .post(`/support/tickets/${this.id}/replies`, this.reply)
        .catch(e => e)
      this.sending = false
      if (this.$error(response.status, response.data?.message)) return

      this.reply = {body: "", is_internal: false}
      await this.load()
    },
  },
})
</script>

<template>
  <v-container v-if="ticket">
    <div class="d-flex align-center mb-4 ga-3 flex-wrap">
      <h1 class="text-headline-small">
        {{ ticket.subject }}
      </h1>
      <v-chip
        size="small"
        label
      >
        {{ ticket.reference }}
      </v-chip>
      <v-spacer />
      <AppSelect
        :model-value="ticket.status"
        :items="statuses"
        label="Status"
        density="compact"
        hide-details
        style="max-width: 170px"
        @update:model-value="v => patch({status: v})"
      />
      <AppSelect
        :model-value="ticket.priority"
        :items="priorities"
        label="Priority"
        density="compact"
        hide-details
        style="max-width: 170px"
        @update:model-value="v => patch({priority: v})"
      />
    </div>

    <v-card class="mb-4">
      <v-card-subtitle class="pt-3">
        {{ ticket.name }} &lt;{{ ticket.email }}&gt; · <AppTimeAgo :value="ticket.createdAt" />
      </v-card-subtitle>
      <v-card-text style="white-space: pre-wrap">
        {{ ticket.body }}
      </v-card-text>
    </v-card>

    <v-card
      v-for="entry in ticket.replies"
      :key="entry.id"
      class="mb-2"
      :color="entry.isInternal ? 'surface-variant' : undefined"
      variant="tonal"
    >
      <v-card-subtitle class="pt-3 d-flex align-center ga-2">
        <span>{{ entry.author ?? "system" }} · <AppTimeAgo :value="entry.createdAt" /></span>
        <v-chip
          v-if="entry.isInternal"
          size="x-small"
          label
        >
          internal note — not emailed
        </v-chip>
      </v-card-subtitle>
      <v-card-text style="white-space: pre-wrap">
        {{ entry.body }}
      </v-card-text>
    </v-card>

    <v-card class="mt-4">
      <v-card-text>
        <AppTextarea
          v-model="reply.body"
          label="Reply"
          rows="4"
        />
        <v-checkbox
          v-model="reply.is_internal"
          label="Internal note (not emailed to the requester)"
          density="compact"
          hide-details
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          :loading="sending"
          :disabled="!reply.body"
          @click="send"
        >
          {{ reply.is_internal ? "Add note" : "Send reply" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>

  <v-container v-else-if="loading">
    <v-progress-circular indeterminate />
  </v-container>
</template>
