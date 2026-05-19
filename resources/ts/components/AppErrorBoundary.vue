<template>
  <slot
    v-if="!error"
  />
  <slot
    v-else
    name="fallback"
    :error="error"
    :reset="reset"
  >
    <v-alert
      type="error"
      variant="tonal"
      class="my-4"
    >
      <template #title>
        Something went wrong
      </template>
      <p class="text-body-2 mb-3">
        The page hit an unexpected error. The team has been notified.
      </p>
      <v-btn
        size="small"
        variant="text"
        @click="reset"
      >
        Try again
      </v-btn>
    </v-alert>
  </slot>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import * as Sentry from "@sentry/vue"

export default defineComponent({
  name: "AppErrorBoundary",
  props: {
    /** Tag captured Sentry events with this string so dashboards can group by boundary. */
    name: {
      type: String,
      default: "AppErrorBoundary",
    },
  },
  data() {
    return {
      error: null as Error | null,
    }
  },
  errorCaptured(err: unknown, _instance, info) {
    const e = err instanceof Error ? err : new Error(String(err))
    this.error = e
    Sentry.captureException(e, {
      tags: { boundary: this.name },
      extra: { vueInfo: info },
    })
    return false
  },
  methods: {
    reset() {
      this.error = null
    },
  },
})
</script>

<style lang="scss" scoped></style>
