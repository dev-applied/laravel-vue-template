<script lang="ts">
import {defineComponent} from "vue"
import {useOnboarding, type OnboardingStep} from "@modules/Onboarding/resources/ts/composables/useOnboarding"

export default defineComponent({
  name: "OnboardingPage",
  setup() {
    return useOnboarding()
  },
  data() {
    return {busy: null as string | null}
  },
  computed: {
    percent(): number {
      return this.state.total === 0 ? 100 : Math.round((this.state.completedCount / this.state.total) * 100)
    },
    hasSkippable(): boolean {
      return this.state.steps.some((step: OnboardingStep) => !step.required && !step.completed && !step.skipped)
    },
  },
  async created() {
    await this.load(true)
  },
  methods: {
    async doComplete(step: OnboardingStep) {
      this.busy = step.key
      await this.complete(step.key)
      this.busy = null
    },
    async doSkip(step: OnboardingStep) {
      this.busy = step.key
      await this.skip(step.key)
      this.busy = null
    },
    go(step: OnboardingStep) {
      if (!step.route?.name) return
      void this.$router.push(this.$routeTo(step.route.name, step.route.params ?? {}))
    },
  },
})
</script>

<template>
  <v-container class="py-8">
    <div class="d-flex align-center flex-wrap ga-2 mb-2">
      <h1 class="text-headline-small text-md-headline-medium mb-0">
        Set up your account
      </h1>
      <v-spacer />
      <v-btn
        v-if="hasSkippable"
        variant="text"
        @click="skipAll"
      >
        Skip the optional steps
      </v-btn>
    </div>

    <p class="text-body-medium text-medium-emphasis">
      {{ state.completedCount }} of {{ state.total }} done.
      <template v-if="state.outstandingRequired > 0">
        {{ state.outstandingRequired }} still required.
      </template>
    </p>

    <v-progress-linear
      :model-value="percent"
      color="primary"
      height="8"
      rounded
      class="mb-6"
      :aria-label="`Setup ${percent}% complete`"
    />

    <v-list lines="two">
      <v-list-item
        v-for="step in state.steps"
        :key="step.key"
        :prepend-icon="step.completed ? 'check_circle' : (step.icon ?? 'radio_button_unchecked')"
        :class="step.completed ? 'text-medium-emphasis' : ''"
      >
        <v-list-item-title :class="step.completed ? 'text-decoration-line-through' : ''">
          {{ step.label }}
          <v-chip
            v-if="!step.required"
            size="x-small"
            label
            class="ms-2"
          >
            Optional
          </v-chip>
          <v-chip
            v-if="step.skipped"
            size="x-small"
            label
            color="warning"
            class="ms-2"
          >
            Skipped
          </v-chip>
        </v-list-item-title>
        <v-list-item-subtitle v-if="step.description">
          {{ step.description }}
        </v-list-item-subtitle>

        <template #append>
          <div class="d-flex align-center ga-2">
            <v-btn
              v-if="!step.completed && step.route"
              color="primary"
              variant="tonal"
              size="small"
              @click="go(step)"
            >
              Go
            </v-btn>
            <v-btn
              v-if="!step.completed && !step.route && !step.autoDetected"
              color="primary"
              variant="tonal"
              size="small"
              :loading="busy === step.key"
              @click="doComplete(step)"
            >
              Mark done
            </v-btn>
            <span
              v-if="!step.completed && !step.route && step.autoDetected"
              class="text-body-small text-medium-emphasis"
            >
              Ticks itself
            </span>
            <v-btn
              v-if="!step.completed && !step.skipped && !step.required"
              variant="text"
              size="small"
              :loading="busy === step.key"
              @click="doSkip(step)"
            >
              Skip
            </v-btn>
          </div>
        </template>
      </v-list-item>
    </v-list>

    <v-alert
      v-if="state.complete"
      type="success"
      variant="tonal"
      class="mt-6"
      role="status"
    >
      You are all set — everything required is done.
    </v-alert>
  </v-container>
</template>
