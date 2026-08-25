<script lang="ts">
import {defineComponent} from "vue"
import {useMessageStore} from "@/stores/message"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

interface Invitation {
  id: number, email: string, role: string | null, status: string,
  invitedBy?: string | null, expiresAt: string, createdAt: string
}

export default defineComponent({
  name: "InvitationsPage",
  components: {AppEmptyState, AppTextField, AppTimeAgo},
  data() {
    return {
      invitations: [] as Invitation[],
      loading: false,
      sending: false,
      form: {email: "", role: ""},
      errors: {} as Record<string, string[]>,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/invitations").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.invitations = response.data.data
    },
    statusColor(status: string): string {
      return {pending: "info", accepted: "success", revoked: "default", expired: "warning"}[status] ?? "default"
    },
    async send() {
      this.sending = true
      this.errors = {}
      const response = await this.$http.post("/invitations", this.form).catch(e => e)
      this.sending = false

      if (response.status === 422) {
        this.errors = response.data.errors ?? {}

        return
      }
      if (this.$error(response.status, response.data?.message)) return

      // 202 means the address already has an account. The API deliberately does
      // not say so — repeating that here would rebuild the oracle it avoids.
      useMessageStore().addSuccess("Invitation sent.")
      this.form = {email: "", role: ""}
      await this.load()
    },
    async resend(invitation: Invitation) {
      const response = await this.$http.post(`/invitations/${invitation.id}/resend`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      useMessageStore().addSuccess("A new invitation link has been sent. The previous one no longer works.")
      await this.load()
    },
    async revoke(invitation: Invitation) {
      if (!await this.$confirm(`Revoke the invitation for ${invitation.email}?`, "Revoke invitation")) return

      const response = await this.$http.delete(`/invitations/${invitation.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
  },
})
</script>

<template>
  <v-container>
    <h1 class="text-headline-large mb-4">
      Invitations
    </h1>

    <v-card class="mb-4">
      <v-card-text>
        <div class="d-flex ga-2 align-start flex-wrap">
          <AppTextField
            v-model="form.email"
            label="Email address"
            :error-messages="errors.email"
            class="flex-grow-1"
            style="min-width: 240px"
          />
          <AppTextField
            v-model="form.role"
            label="Role (optional)"
            :error-messages="errors.role"
            style="max-width: 200px"
          />
          <v-btn
            color="primary"
            :loading="sending"
            @click="send"
          >
            Invite
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-card :loading="loading">
      <v-table v-if="invitations.length">
        <thead>
          <tr>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Invited by</th>
            <th>Expires</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="invitation in invitations"
            :key="invitation.id"
          >
            <td>{{ invitation.email }}</td>
            <td>{{ invitation.role ?? "—" }}</td>
            <td>
              <v-chip
                :color="statusColor(invitation.status)"
                size="small"
                label
              >
                {{ invitation.status }}
              </v-chip>
            </td>
            <td>{{ invitation.invitedBy ?? "—" }}</td>
            <td><AppTimeAgo :value="invitation.expiresAt" /></td>
            <td class="text-right">
              <v-btn
                v-if="invitation.status !== 'accepted'"
                size="small"
                variant="text"
                @click="resend(invitation)"
              >
                Resend
              </v-btn>
              <v-btn
                v-if="invitation.status === 'pending'"
                size="small"
                variant="text"
                color="error"
                @click="revoke(invitation)"
              >
                Revoke
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="mail"
        title="No invitations yet"
        description="Invite someone using the form above."
      />
    </v-card>
  </v-container>
</template>
