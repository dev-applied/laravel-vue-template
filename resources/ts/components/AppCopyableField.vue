<template>
  <span class="app-copyable d-inline-flex align-center ga-1">
    <span
      v-if="!hideValue"
      class="app-copyable__value"
      :class="{ 'app-copyable__value--mono': monospace }"
    >
      <slot>{{ display }}</slot>
    </span>
    <v-tooltip
      location="top"
      :text="tooltipText"
    >
      <template #activator="{ props: activatorProps }">
        <v-btn
          v-bind="activatorProps"
          :icon="copied ? 'check' : 'content_copy'"
          :color="copied ? 'success' : undefined"
          variant="text"
          size="x-small"
          density="comfortable"
          :aria-label="ariaLabel"
          @click="onCopy"
        />
      </template>
    </v-tooltip>
  </span>
</template>

<script lang="ts" setup>
import { computed, ref } from "vue"

const props = withDefaults(defineProps<{
  /** The value that gets copied. Falls back to slot text content if omitted. */
  value?:     string | number | null
  /** When true, only the copy button renders. Useful next to existing labels. */
  hideValue?: boolean
  /** Tooltip on the button before clicking. */
  tooltip?:   string
  /** Tooltip after successful copy. Auto-reverts after 1.5s. */
  copiedTooltip?: string
  /** Render the value in a monospace font (useful for IDs, tokens, hashes). */
  monospace?: boolean
  /** Hidden a11y label for the button. */
  ariaLabel?: string
}>(), {
  value:         undefined,
  hideValue:     false,
  tooltip:       "Copy",
  copiedTooltip: "Copied!",
  monospace:     false,
  ariaLabel:     "Copy to clipboard",
})

const copied = ref(false)
const display     = computed(() => props.value ?? "")
const tooltipText = computed(() => copied.value ? props.copiedTooltip : props.tooltip)

async function onCopy() {
  const text = String(props.value ?? "")
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
    } else {
      // Fallback for non-https/old browsers
      const ta = document.createElement("textarea")
      ta.value = text
      ta.style.position = "fixed"
      ta.style.opacity = "0"
      document.body.appendChild(ta)
      ta.select()
      document.execCommand("copy")
      document.body.removeChild(ta)
    }
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  } catch {
    // Silently fail — caller can listen on @copy:error if needed.
  }
}
</script>

<style lang="scss" scoped>
.app-copyable {
  &__value--mono {
    font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
  }
}
</style>
