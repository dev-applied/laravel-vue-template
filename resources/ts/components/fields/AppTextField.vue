<template>
  <v-text-field
    v-model="internalValue"
    class="field"
    hide-details="auto"
    v-bind="textFieldProps"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        :name="name"
        v-bind="slotData"
      />
    </template>
    <template #message="message">
      <span v-html="message.message" />
    </template>
  </v-text-field>
</template>

<script lang="ts" setup>
import {computed, useAttrs} from "vue"
import {VTextField} from "vuetify/components"
import type {ComponentSlots} from 'vue-component-type-helpers'


defineSlots<ComponentSlots<typeof VTextField>>()

type Props = /* @vue-ignore */InstanceType<typeof VTextField>["$props"]

defineProps<Props>()

// Make all useAttrs keys camelCase
const textFieldProps = computed(() => {
  return useAttrs()
})

const internalValue = defineModel<string | null | undefined>()
</script>

<style lang="scss" scoped>
:deep() {
  .v-text-field {
    .v-field__outline {
      &::after {
        display: none;
      }

      &::before {
        border-radius: 4px;
        border-width: 1px;
      }
    }

    &:not(.v-input--error) {
      .v-field__outline {
        &::before {
          border-width: 0;
        }
      }
    }
  }
}
</style>
