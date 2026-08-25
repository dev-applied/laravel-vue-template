<script lang="ts">
import {defineComponent} from "vue"
import {useOnboarding} from "@modules/Onboarding/resources/ts/composables/useOnboarding"
import {ROUTES} from "@modules/Onboarding/resources/ts/routes"

/**
 * A persistent nudge for any layout. Drop it above <v-main>.
 *
 * Dismissal is per session, not persisted: the point of the banner is that the
 * account is not finished, and a permanently dismissible reminder of unfinished
 * required setup is just a hidden one.
 */
export default defineComponent({
  name: "AppOnboardingBanner",
  setup() {
    return useOnboarding()
  },
  data() {
    return {dismissed: false}
  },
  computed: {
    visible(): boolean {
      return !this.dismissed && this.loaded && !this.state.complete
    },
    remaining(): number {
      return this.state.outstandingRequired
    },
  },
  async created() {
    await this.load()
  },
})
</script>

<template>
  <v-alert
    v-show="visible"
    type="info"
    variant="tonal"
    rounded="0"
    class="px-4"
    role="status"
  >
    <div class="d-flex align-center flex-wrap ga-2">
      <span>
        {{ remaining }} setup {{ remaining === 1 ? 'step is' : 'steps are' }} still required.
      </span>
      <v-spacer />
      <v-btn
        size="small"
        variant="tonal"
        @click="$router.push($routeTo(ROUTES.ONBOARDING))"
      >
        Finish setup
      </v-btn>
      <v-btn
        icon="close"
        size="small"
        variant="text"
        aria-label="Hide this reminder for now"
        @click="dismissed = true"
      />
    </div>
  </v-alert>
</template>
