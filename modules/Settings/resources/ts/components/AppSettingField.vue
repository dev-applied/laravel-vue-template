<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"
import AppNumberInput from "@/components/fields/AppNumberInput.vue"
import {SECRET_MASK, type SettingField} from "@modules/Settings/resources/ts/composables/useSettings"

/**
 * Renders one declared setting as the right control. The management screen is
 * generated from the declarations rather than hand-built, so adding a setting
 * server-side is the whole change.
 */
export default defineComponent({
  name: "AppSettingField",
  components: {AppSelect, AppTextField, AppTextarea, AppNumberInput},
  props: {
    field: {type: Object as PropType<SettingField>, required: true},
    // Genuinely any of the declared types — string, number, boolean, or an
    // object for a json setting — so the union is the honest annotation.
    modelValue: {
      type: [String, Number, Boolean, Object, Array] as PropType<string | number | boolean | object | null>,
      default: null,
    },
  },
  emits: ['update:modelValue'],
  computed: {
    choiceItems(): {title: string, value: string}[] {
      return Object.entries(this.field.choices ?? {}).map(([value, title]) => ({title, value}))
    },
    secretIsSet(): boolean {
      return this.field.isSecret && this.modelValue === SECRET_MASK
    },
    hint(): string {
      if (!this.field.isSecret) return this.field.help ?? ""

      return this.secretIsSet
        ? "Set. Leave as-is to keep it, or type a new value to replace it."
        : "Not configured."
    },
  },
})
</script>

<template>
  <div class="app-setting-field">
    <v-switch
      v-if="field.type === 'boolean'"
      color="primary"
      density="compact"
      hide-details="auto"
      :hint="hint"
      :label="field.label"
      :messages="hint || undefined"
      :model-value="!!modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppSelect
      v-else-if="field.type === 'select'"
      hide-details="auto"
      :hint="hint"
      :items="choiceItems"
      :label="field.label"
      :model-value="modelValue"
      persistent-hint
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- The cast carries what the v-else-if already guarantees: on an integer
         or float setting the value is numeric. TS cannot narrow a prop across a
         template branch, so without it the whole SettingValue union (which
         includes boolean and object) is offered to a numeric input. -->
    <AppNumberInput
      v-else-if="field.type === 'integer' || field.type === 'float'"
      hide-details="auto"
      :hint="hint"
      :label="field.label"
      :model-value="(modelValue as number | null)"
      persistent-hint
      :step="field.type === 'integer' ? 1 : 0.01"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppTextarea
      v-else-if="field.type === 'text' || field.type === 'json'"
      hide-details="auto"
      :hint="hint"
      :label="field.label"
      :model-value="modelValue"
      persistent-hint
      rows="3"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppTextField
      v-else
      :append-inner-icon="field.isSecret ? 'vpn_key' : undefined"
      hide-details="auto"
      :hint="hint"
      :label="field.label"
      :model-value="modelValue"
      persistent-hint
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <v-chip
      v-if="field.isPublic"
      class="mt-1"
      density="comfortable"
      prepend-icon="public"
      size="x-small"
      variant="tonal"
    >
      Public
    </v-chip>
  </div>
</template>
