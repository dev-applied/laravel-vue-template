<template>
  <div class="app-page-header mb-4">
    <v-breadcrumbs
      v-if="breadcrumbs?.length || $slots.breadcrumbs"
      :items="breadcrumbs ?? []"
      density="compact"
      class="pa-0 mb-1"
    >
      <template
        v-if="$slots.breadcrumbs"
        #default
      >
        <slot name="breadcrumbs" />
      </template>
    </v-breadcrumbs>

    <div class="d-flex flex-wrap align-center ga-3">
      <v-btn
        v-if="backTo"
        :to="backTo"
        icon="arrow_back"
        variant="text"
        size="small"
        aria-label="Back"
      />

      <div class="flex-grow-1 min-w-0">
        <h1
          class="text-headline-small text-md-h4 mb-0"
          :class="titleClass"
        >
          {{ title }}
        </h1>
        <p
          v-if="subtitle"
          class="text-body-medium text-medium-emphasis mt-1 mb-0"
        >
          {{ subtitle }}
        </p>
      </div>

      <div
        v-if="$slots.actions"
        class="d-flex flex-wrap ga-2 ms-auto"
      >
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { RouteLocationRaw } from "vue-router"

export interface Breadcrumb {
  title:    string
  to?:      RouteLocationRaw
  href?:    string
  disabled?: boolean
}

withDefaults(defineProps<{
  title:        string
  subtitle?:    string
  breadcrumbs?: Breadcrumb[]
  /** When set, renders a back-arrow button that navigates to the given route. */
  backTo?:      RouteLocationRaw
  /** Extra classes on the h1 — useful for color tweaks per page. */
  titleClass?:  string
}>(), {
  subtitle:    undefined,
  breadcrumbs: () => [],
  backTo:      undefined,
  titleClass:  "",
})
</script>

<style lang="scss" scoped>
.app-page-header :deep(.v-breadcrumbs-item) {
  padding: 0;
}
</style>
