<template>
  <v-mask-input
    ref="input"
    v-model="internalValue"
    class="field"
    v-bind="maskFieldProps"
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
  </v-mask-input>
</template>

<script lang="ts" setup>
import {computed, useAttrs} from "vue"
import type {ComponentSlots} from 'vue-component-type-helpers'
import {VMaskInput} from "vuetify/labs/components"


defineSlots<ComponentSlots<typeof VMaskInput>>()

type Props = /* @vue-ignore */InstanceType<typeof VMaskInput>["$props"]

defineProps<Props>()

// Make all useAttrs keys camelCase
const maskFieldProps = computed(() => {
  return useAttrs()
})

const internalValue = defineModel<any>()
</script>

<style lang="scss" scoped>
:deep() {
  .v-mask-input {
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
