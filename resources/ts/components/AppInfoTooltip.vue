<template>
  <v-tooltip
    :location="location"
    :max-width="maxWidth"
    open-on-focus
    open-on-click
  >
    <template #activator="{ props: activatorProps }">
      <v-icon
        v-bind="activatorProps"
        :icon="icon"
        :size="size"
        class="app-info-tooltip"
        :aria-label="ariaLabel"
        tabindex="0"
      />
    </template>
    <slot>{{ text }}</slot>
  </v-tooltip>
</template>

<script lang="ts" setup>
withDefaults(defineProps<{
  /** Tooltip body. Override via default slot for rich content. */
  text?:      string
  icon?:      string
  size?:      "x-small" | "small" | "default" | "large" | "x-large" | number
  location?:  "top" | "bottom" | "start" | "end"
  maxWidth?:  number | string
  ariaLabel?: string
}>(), {
  text:      undefined,
  icon:      "help_outline",
  size:      "small",
  location:  "top",
  maxWidth:  320,
  ariaLabel: "More information",
})
</script>

<style lang="scss" scoped>
.app-info-tooltip {
  cursor: help;
  opacity: 0.65;
  &:hover, &:focus {
    opacity: 1;
  }
  // Make the focus ring visible for keyboard users.
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
    border-radius: 50%;
  }
}
</style>
