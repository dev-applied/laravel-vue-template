<template>
  <div class="app-inline-edit">
    <v-text-field
      v-if="editing"
      ref="inputRef"
      v-model="draft"
      :type="type"
      :placeholder="placeholder"
      :rules="rules"
      :loading="saving"
      :error-messages="errorMessage ? [errorMessage] : []"
      density="compact"
      hide-details="auto"
      autofocus
      @keydown.enter.prevent="save"
      @keydown.escape.prevent="cancel"
      @blur="save"
    />
    <span
      v-else
      class="app-inline-edit__display"
      role="button"
      tabindex="0"
      :aria-label="`Edit ${label ?? 'value'}`"
      @click="startEdit"
      @keydown.enter.prevent="startEdit"
      @keydown.space.prevent="startEdit"
    >
      <slot :value="display">
        {{ display || placeholder || "—" }}
      </slot>
      <v-icon
        size="x-small"
        class="ms-1 app-inline-edit__icon"
      >
        edit
      </v-icon>
    </span>
  </div>
</template>

<script lang="ts" setup>
import { nextTick, ref } from "vue"

const props = withDefaults(defineProps<{
  modelValue:    string | number | null
  label?:        string
  placeholder?:  string
  type?:         "text" | "number" | "email" | "url"
  rules?:        ((v: unknown) => true | string)[]
  /** Called on save; should return a Promise. Errors flow into errorMessage. */
  onSave?:       (value: string | number | null) => Promise<unknown> | unknown
}>(), {
  label:       undefined,
  placeholder: undefined,
  type:        "text",
  rules:       () => [],
  onSave:      undefined,
})

const emit = defineEmits<{
  "update:modelValue": [value: string | number | null]
}>()

const editing      = ref(false)
const saving       = ref(false)
const draft        = ref<string | number | null>(props.modelValue)
const errorMessage = ref<string>("")
const inputRef     = ref<HTMLInputElement | null>(null)
const display      = ref(props.modelValue)

async function startEdit() {
  draft.value = props.modelValue
  errorMessage.value = ""
  editing.value = true
  await nextTick()
  ;(inputRef.value as unknown as { focus?: () => void })?.focus?.()
}

async function save() {
  if (!editing.value) return
  if (draft.value === props.modelValue) {
    editing.value = false
    return
  }

  saving.value = true
  errorMessage.value = ""
  try {
    if (props.onSave) await props.onSave(draft.value)
    emit("update:modelValue", draft.value)
    display.value = draft.value
    editing.value = false
  } catch (e) {
    errorMessage.value = e instanceof Error ? e.message : "Could not save"
    // stay in editing mode so the user can correct
  } finally {
    saving.value = false
  }
}

function cancel() {
  draft.value = props.modelValue
  errorMessage.value = ""
  editing.value = false
}
</script>

<style lang="scss" scoped>
.app-inline-edit {
  display: inline-block;
  min-width: 80px;

  &__display {
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    transition: background-color 0.15s;

    &:hover {
      background-color: rgba(var(--v-theme-on-surface), 0.06);

      .app-inline-edit__icon {
        opacity: 1;
      }
    }

    &:focus-visible {
      outline: 2px solid rgb(var(--v-theme-primary));
      outline-offset: 2px;
    }
  }

  &__icon {
    opacity: 0;
    transition: opacity 0.15s;
  }
}
</style>
