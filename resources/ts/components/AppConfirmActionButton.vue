<template>
  <v-btn
    :color="color"
    :variant="variant"
    :size="size"
    :prepend-icon="prependIcon"
    :append-icon="appendIcon"
    :loading="loading"
    :disabled="disabled"
    @click="onClick"
  >
    <slot>{{ text }}</slot>
  </v-btn>
</template>

<script lang="ts" setup>
import { ref } from "vue"
import { $confirm } from "@/plugins/confirm"

const props = withDefaults(defineProps<{
  /** Button label — overridable via default slot. */
  text?:           string
  /** Confirm dialog title. */
  confirmTitle?:   string
  /** Confirm dialog body. */
  confirmMessage?: string
  /** Confirm primary button label, color, and icon. */
  confirmText?:    string
  confirmColor?:   string
  /** Set to false to bypass the dialog (useful when caller toggles it conditionally). */
  requireConfirm?: boolean
  /** Vuetify v-btn passthroughs. */
  color?:          string
  variant?:        "elevated" | "flat" | "tonal" | "outlined" | "text" | "plain"
  size?:           "x-small" | "small" | "default" | "large" | "x-large"
  prependIcon?:    string
  appendIcon?:     string
  disabled?:       boolean
}>(), {
  text:           "Delete",
  confirmTitle:   "Are you sure?",
  confirmMessage: "This action cannot be undone.",
  confirmText:    "Confirm",
  confirmColor:   "error",
  requireConfirm: true,
  color:          "error",
  variant:        "tonal",
  size:           "default",
  prependIcon:    undefined,
  appendIcon:     undefined,
  disabled:       false,
})

const emit = defineEmits<{
  /** Fires after the user confirms (or immediately if requireConfirm=false). */
  confirmed:      []
  /** Fires when the user cancels the confirm dialog. */
  cancelled:      []
}>()

const loading = ref(false)

/**
 * Use this from the parent: bind @confirmed to your action; while the action
 * runs, set `:loading="true"` via a child ref, OR don't pass loading at all
 * and rely on the awaited handler via `runAsync` exposed below.
 */
async function onClick() {
  if (props.requireConfirm) {
    const ok = await $confirm(
      props.confirmTitle,
      props.confirmMessage,
      "warning",
      {
        buttonTrueText:  props.confirmText,
        buttonTrueColor: props.confirmColor,
        buttonFalseText: "Cancel",
      },
    )
    if (!ok) {
      emit("cancelled")
      return
    }
  }
  emit("confirmed")
}

/** Parent can call this to wrap an async action with the loading state. */
async function runAsync<T>(fn: () => Promise<T>): Promise<T | undefined> {
  loading.value = true
  try {
    return await fn()
  } finally {
    loading.value = false
  }
}

defineExpose({ runAsync, setLoading: (v: boolean) => { loading.value = v } })
</script>

<style lang="scss" scoped></style>
