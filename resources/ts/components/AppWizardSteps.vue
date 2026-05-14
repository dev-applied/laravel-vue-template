<template>
  <div class="app-wizard">
    <v-stepper
      v-model="current"
      :items="stepTitles"
      :alt-labels="altLabels"
      flat
      class="bg-transparent"
    />

    <v-card
      variant="outlined"
      class="mt-4"
    >
      <v-card-text class="pa-4">
        <slot :name="`step-${current}`" />
      </v-card-text>

      <v-divider />

      <v-card-actions>
        <v-btn
          variant="text"
          :disabled="current === 1"
          @click="previous"
        >
          Previous
        </v-btn>
        <v-spacer />
        <span class="text-caption text-medium-emphasis me-2">
          Step {{ current }} of {{ steps.length }}
        </span>
        <v-btn
          v-if="!isLast"
          color="primary"
          :disabled="!canAdvance"
          @click="next"
        >
          Next
        </v-btn>
        <v-btn
          v-else
          color="success"
          :disabled="!canFinish"
          :loading="finishing"
          @click="onFinish"
        >
          {{ finishText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
import { computed } from "vue"

export interface WizardStep {
  title:  string
  /** Set false to gate "Next" / "Finish" on this step. Undefined = always allowed. */
  valid?: boolean
}

const props = withDefaults(defineProps<{
  steps:        WizardStep[]
  /** 1-based step index. v-model. */
  modelValue?:  number
  finishText?:  string
  finishing?:   boolean
  altLabels?:   boolean
}>(), {
  modelValue: 1,
  finishText: "Finish",
  finishing:  false,
  altLabels:  true,
})

const emit = defineEmits<{
  "update:modelValue": [step: number]
  finish:              []
}>()

const current = computed({
  get: () => props.modelValue,
  set: (v) => emit("update:modelValue", v),
})

// Prefix invalid steps with a warning glyph — without per-step v-slot
// gymnastics, this stays both terse and Vuetify-version-agnostic.
const stepTitles = computed(() =>
  props.steps.map(s => s.valid === false ? `⚠ ${s.title}` : s.title),
)

const isLast     = computed(() => current.value === props.steps.length)
const canAdvance = computed(() => props.steps[current.value - 1]?.valid !== false)
const canFinish  = computed(() => props.steps.every(s => s.valid !== false))

function previous() { if (current.value > 1) current.value-- }
function next()     { if (current.value < props.steps.length && canAdvance.value) current.value++ }

function onFinish() {
  if (canFinish.value) emit("finish")
}
</script>

<style lang="scss" scoped></style>
