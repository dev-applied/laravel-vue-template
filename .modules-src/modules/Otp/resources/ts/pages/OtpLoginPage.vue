<script lang="ts">
import {defineComponent} from "vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppOtpInput from "@modules/Otp/resources/ts/components/AppOtpInput.vue"
import useOtp from "@modules/Otp/resources/ts/composables/useOtp"
import {useUserStore} from "@/stores/user"
import {KERNEL_ROUTES} from "@/router/kernel-routes"

export default defineComponent({
  name: "OtpLoginPage",
  components: {AppTextField, AppOtpInput},
  setup() {
    return useOtp()
  },
  beforeUnmount() {
    this.stop()
  },
  methods: {
    async submitIdentifier() {
      await this.request()
    },
    async submitCode() {
      const result = await this.verify()
      if (!result) return

      // Three separate bugs lived in the two lines this replaces, and none of
      // them raised anything:
      //   1. $auth has no setToken — `?.()` turned a missing method into a
      //      silent no-op, so the token was never stored and the user was
      //      never actually signed in.
      //   2. ROUTES.HOME does not exist, so the `?? "/"` fallback asked
      //      $routeTo for a route NAMED "/", which throws.
      //   3. $routeTo only BUILDS a location; without $router.push nothing
      //      navigates even when it resolves.
      await useUserStore().setToken(result.token)

      await this.$router.push(this.$routeTo(KERNEL_ROUTES.DASHBOARD))
    },
    countdown(): string {
      const m = Math.floor(this.secondsLeft / 60)
      const s = this.secondsLeft % 60
      return `${m}:${String(s).padStart(2, "0")}`
    },
  },
})
</script>

<template>
  <v-container
    class="d-flex align-center justify-center"
    style="min-height: 80vh"
  >
    <v-card
      max-width="420"
      width="100%"
    >
      <v-card-title>Sign in</v-card-title>

      <!-- v-show, not v-if: swapping these would drop focus and lose whatever
           has been typed if the request is slow. -->
      <v-card-text v-show="!sent">
        <p class="text-body-2 text-medium-emphasis mb-4">
          We will email you a code. No password needed.
        </p>
        <AppTextField
          v-model="identifier"
          autocomplete="email"
          label="Email address"
          name="identifier"
          type="email"
          @keyup.enter="submitIdentifier"
        />
      </v-card-text>

      <v-card-text v-show="sent">
        <p class="text-body-2 text-medium-emphasis mb-4">
          We sent a code to <strong>{{ masked }}</strong>.
        </p>
        <AppOtpInput
          v-model="code"
          :loading="verifying"
          @complete="submitCode"
        />
        <div class="text-caption text-medium-emphasis mt-2">
          <span v-if="secondsLeft > 0">Expires in {{ countdown() }}</span>
          <v-btn
            v-else
            :loading="sending"
            size="small"
            variant="text"
            @click="submitIdentifier"
          >
            Send a new code
          </v-btn>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-btn
          v-show="sent"
          variant="text"
          @click="reset"
        >
          Use a different address
        </v-btn>
        <v-spacer />
        <v-btn
          v-show="!sent"
          color="primary"
          :disabled="!identifier"
          :loading="sending"
          variant="flat"
          @click="submitIdentifier"
        >
          Send code
        </v-btn>
        <v-btn
          v-show="sent"
          color="primary"
          :disabled="!code"
          :loading="verifying"
          variant="flat"
          @click="submitCode"
        >
          Sign in
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
