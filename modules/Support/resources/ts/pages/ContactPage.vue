<script lang="ts">
import {defineComponent} from "vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"

/** Public contact form. Available signed in or not. */
export default defineComponent({
  name: "ContactPage",
  components: {AppTextField, AppTextarea},
  data() {
    return {
      form: {name: "", email: "", subject: "", body: "", website: ""},
      errors: {} as Record<string, string[]>,
      sending: false,
      reference: null as string | null,
    }
  },
  created() {
    // Pre-fill for a signed-in user; they should not retype what we know.
    const user = this.$auth.user as any
    if (user) {
      this.form.name  = [user.first_name, user.last_name].filter(Boolean).join(" ")
      this.form.email = user.email ?? ""
    }
  },
  methods: {
    async submit() {
      this.sending = true
      this.errors = {}

      const response = await this.$http.post("/support/tickets", this.form).catch(e => e)
      this.sending = false

      if (response.status === 422) {
        this.errors = response.data.errors ?? {}

        return
      }
      if (this.$error(response.status, response.data?.message)) return

      this.reference = response.data.reference
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
      width="560"
      max-width="95vw"
    >
      <template v-if="reference">
        <v-card-title tag="h1">
          Message received
        </v-card-title>
        <v-card-text>
          <p class="mb-2">
            Thanks — we will get back to you by email.
          </p>
          <p class="text-body-medium text-medium-emphasis">
            Your reference is <strong>{{ reference }}</strong>. Quote it if you
            need to follow up.
          </p>
        </v-card-text>
      </template>

      <template v-else>
        <v-card-title tag="h1">
          Contact support
        </v-card-title>
        <v-card-text>
          <AppTextField
            v-model="form.name"
            label="Your name"
            :error-messages="errors.name"
          />
          <AppTextField
            v-model="form.email"
            label="Email address"
            :error-messages="errors.email"
          />
          <AppTextField
            v-model="form.subject"
            label="Subject"
            :error-messages="errors.subject"
          />
          <AppTextarea
            v-model="form.body"
            label="How can we help?"
            :error-messages="errors.body"
            rows="5"
          />

          <!-- Honeypot: hidden from people, irresistible to form bots. Not
               type="hidden" — some bots skip those; off-screen is stickier. -->
          <input
            v-model="form.website"
            type="text"
            name="website"
            tabindex="-1"
            autocomplete="off"
            aria-hidden="true"
            style="position:absolute;left:-9999px;width:1px;height:1px"
          >
        </v-card-text>
        <v-card-actions>
          <v-btn
            block
            color="primary"
            :loading="sending"
            @click="submit"
          >
            Send
          </v-btn>
        </v-card-actions>
      </template>
    </v-card>
  </v-container>
</template>
