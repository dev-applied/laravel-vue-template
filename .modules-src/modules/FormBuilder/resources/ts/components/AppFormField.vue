<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {FormAnswer} from "@modules/FormBuilder/resources/ts/composables/useForm"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"
import AppNumberInput from "@/components/fields/AppNumberInput.vue"
import AppDateInput from "@/components/fields/AppDateInput.vue"
import type {FormField} from "@modules/FormBuilder/resources/ts/composables/useForm"

/**
 * One declared field, rendered with the kernel's own field components.
 *
 * That is the point of the module: a builder-made form looks and behaves like
 * every hand-built form in the app, including validation display, rather than
 * being a second, worse set of inputs.
 */
export default defineComponent({
  name: "AppFormField",
  components: {AppSelect, AppTextField, AppTextarea, AppNumberInput, AppDateInput},
  props: {
    field:      {type: Object as PropType<FormField>, required: true},
    modelValue: {
      // FormAnswer, not `unknown`. `unknown` reads as "anything" but is
      // assignable to nothing, so binding a real answer into this field was an
      // error at every call site.
      type: [String, Number, Boolean, Array] as PropType<FormAnswer>,
      default: null,
    },
  },
  emits: ['update:modelValue'],
  computed: {
    items(): {title: string, value: string}[] {
      return (this.field.options ?? []).map((o) => ({title: o.label, value: o.value}))
    },
    // The server names errors `answers.<key>`, which is what
    // AppServerValidationForm matches on.
    fieldName(): string {
      return `answers.${this.field.key}`
    },
  },
})
</script>

<template>
  <div class="app-form-field">
    <AppTextarea
      v-if="field.type === 'textarea'"
      :hint="field.help || undefined"
      :label="field.label"
      :model-value="modelValue"
      :name="fieldName"
      persistent-hint
      :placeholder="field.placeholder || undefined"
      :required="field.required"
      rows="4"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- The casts on these two carry what the v-else-if already guarantees.
         TypeScript cannot narrow a prop across a template branch, so the whole
         FormAnswer union is otherwise offered to a numeric / date input. -->
    <AppNumberInput
      v-else-if="field.type === 'number'"
      :hint="field.help || undefined"
      :label="field.label"
      :max="field.max ?? undefined"
      :min="field.min ?? undefined"
      :model-value="(modelValue as number | null)"
      :name="fieldName"
      persistent-hint
      :required="field.required"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppDateInput
      v-else-if="field.type === 'date'"
      :hint="field.help || undefined"
      :label="field.label"
      :model-value="(modelValue as string | null)"
      :name="fieldName"
      persistent-hint
      :required="field.required"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppSelect
      v-else-if="field.type === 'select' || field.type === 'multiselect'"
      :chips="field.type === 'multiselect'"
      :hint="field.help || undefined"
      :items="items"
      :label="field.label"
      :model-value="modelValue"
      :multiple="field.type === 'multiselect'"
      :name="fieldName"
      persistent-hint
      :required="field.required"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <div v-else-if="field.type === 'radio'">
      <v-radio-group
        :hint="field.help || undefined"
        :label="field.label"
        :model-value="modelValue"
        :name="fieldName"
        persistent-hint
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <v-radio
          v-for="option in field.options ?? []"
          :key="option.value"
          :label="option.label"
          :value="option.value"
        />
      </v-radio-group>
    </div>

    <v-checkbox
      v-else-if="field.type === 'checkbox'"
      hide-details="auto"
      :label="field.label"
      :messages="field.help || undefined"
      :model-value="!!modelValue"
      :name="fieldName"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <AppTextField
      v-else
      :hint="field.help || undefined"
      :label="field.label"
      :model-value="modelValue"
      :name="fieldName"
      persistent-hint
      :placeholder="field.placeholder || undefined"
      :required="field.required"
      :type="field.type === 'email' ? 'email' : 'text'"
      @update:model-value="$emit('update:modelValue', $event)"
    />
  </div>
</template>
