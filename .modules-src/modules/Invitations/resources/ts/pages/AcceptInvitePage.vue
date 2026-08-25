<script lang="ts">
import {defineComponent} from "vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"

/**
 * Public account creation from an invite link. The token arrives as ?token=,
 * is previewed before the form renders (so a dead link says so immediately
 * rather than after the visitor types a password), and is submitted with the
 * new account details.
 */
export default defineComponent({
  name: "AcceptInvitePage",
  components: {AppTextField, AppEmptyState},
  data() {
    return {
      checking: true,
      valid: false,
      email: "",
      submitting: false,
      form: {first_name: "", last_name: "", password: "", password_confirmation: ""},
      errors: {} as Record<string, string[]>,
    }
  },
  computed: {
    token(): string {
      return String(this.$route.query.token ?? "")
    },
  },
  async created() {
    if (!this.token) {
      this.checking = false

      return
    }

    const response = await this.$http
      .get("/invitations/accept", {params: {token: this.token}})
      .catch(e => e)
    this.checking = false

    if (response.status === 200) {
      this.valid = true
      this.email = response.data.email
    }
  },
  methods: {
    async submit() {
      this.submitting = true
      this.errors = {}

      const response = await this.$http.post("/invitations/accept", {
        token: this.token,
        ...this.form,
      }).catch(e => e)
      this.submitting = false

      if (response.status === 422) {
        this.errors = response.data.errors ?? {}

        return
      }
      if (this.$error(response.status, response.data?.message)) return

      // The API returns a bearer token, so the new account is already signed in.
      await this.$auth.loadUser(true)
      await this.$router.push(this.$routeTo(this.ROUTES.DASHBOARD))
    },
  },
})
</script>

<template>
  <v-container
    class="d-flex justify-center align-center"
    style="min-height: 100vh"
  >
    <v-card
      width="440"
      max-width="95vw"
    >
      <v-card-text
        v-if="checking"
        class="text-center pa-8"
      >
        <v-progress-circular indeterminate />
      </v-card-text>

      <template v-else-if="valid">
        <v-card-title tag="h1">
          Create your account
        </v-card-title>
        <v-card-subtitle>{{ email }}</v-card-subtitle>
        <v-card-text>
          <AppTextField
            v-model="form.first_name"
            label="First name"
            :error-messages="errors.first_name"
          />
          <AppTextField
            v-model="form.last_name"
            label="Last name"
            :error-messages="errors.last_name"
          />
          <AppTextField
            v-model="form.password"
            label="Password"
            type="password"
            :error-messages="errors.password"
          />
          <AppTextField
            v-model="form.password_confirmation"
            label="Confirm password"
            type="password"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn
            block
            color="primary"
            :loading="submitting"
            @click="submit"
          >
            Create account
          </v-btn>
        </v-card-actions>
      </template>

      <AppEmptyState
        v-else
        icon="link_off"
        title="This invitation link is no longer valid"
        description="It may have expired, been revoked, or already been used. Ask whoever invited you to send a new one."
      />
    </v-card>
  </v-container>
</template>
