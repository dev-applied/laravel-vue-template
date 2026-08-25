<template>
  <v-container
    class="fill-height"
    max-width="480"
  >
    <v-card
      class="w-100 pa-6 text-center"
      variant="flat"
    >
      <template v-if="error">
        <v-icon
          class="mb-3"
          color="error"
          icon="error_outline"
          size="48"
        />

        <h1 class="text-title-large mb-2">
          Sign-in didn't complete
        </h1>

        <p class="text-body-medium text-medium-emphasis mb-6">
          {{ error }}
        </p>

        <v-btn
          color="primary"
          @click="backToLogin"
        >
          Back to sign in
        </v-btn>
      </template>

      <template v-else>
        <v-progress-circular
          class="mb-4"
          color="primary"
          indeterminate
          size="40"
        />

        <div class="text-body-large">
          Signing you in…
        </div>
      </template>
    </v-card>
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import useHttp from "@/composables/useHttp"
import {useUserStore} from "@/stores/user"
import {KERNEL_ROUTES} from "@/router/kernel-routes"

/**
 * Where the provider round trip lands.
 *
 * The API callback never hands the browser a token — it redirects here with a
 * single-use handoff code, and this page redeems it over a normal XHR the app
 * controls. Before this page existed the callback answered the top-level
 * navigation with `{"access_token": ...}`, so the browser simply displayed the
 * JSON and sign-in stopped there. In a Capacitor build it was worse: that JSON
 * rendered in a system browser the app cannot read, so the flow could not
 * complete at all.
 */
export default defineComponent({
  name: "SsoCompletePage",
  setup() {
    return useHttp()
  },
  data() {
    return {
      error: "" as string,
    }
  },
  async mounted() {
    // The API puts its own refusal here. It is deliberately generic and carries
    // a reference — the specific reason is in the server log, because three
    // distinguishable refusals let anyone sort an address into exists / does
    // not exist / deactivated.
    const failed = this.$route.query?.error

    if (failed) {
      this.error = String(failed)
      return
    }

    const code = this.$route.query?.code

    if (!code) {
      this.error = "This sign-in link is incomplete. Please start again."
      return
    }

    const {status, data} = await this.$http
      .post("auth/sso/exchange", {code: String(code)})
      .catch((e: any) => e)

    if (status > 204 || !data?.access_token) {
      this.error = data?.message || "This sign-in has expired. Please try again."
      return
    }

    // setToken persists the token and loads the user, so the router's
    // Authentication middleware sees a signed-in user on the next navigation.
    await useUserStore().setToken(data.access_token)

    await this.$router.push(this.$routeTo(KERNEL_ROUTES.DASHBOARD))
  },
  methods: {
    backToLogin() {
      this.$router.push(this.$routeTo(KERNEL_ROUTES.LOGIN))
    },
  },
})
</script>
