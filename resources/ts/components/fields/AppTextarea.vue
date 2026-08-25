<template>
  <v-textarea
    v-model="internalValue"
    class="field"
    hide-details="auto"
    v-bind="textareaProps"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <!-- `slotData || {}` rather than `slotData`: a zero-argument slot
           (Vuetify's no-data, loading, top, bottom, ...) forwards `undefined`,
           and Vue's guardReactiveProps turns that into null. renderSlot only
           defaults props for `undefined`, so null reaches `props.key` and
           throws — blanking the entire page, not just the slot. -->
      <slot
        :name="name"
        v-bind="slotData || {}"
      />
    </template>
  </v-textarea>
</template>

<script lang="ts" setup>
import {computed, useAttrs} from "vue"
import {VTextarea} from "vuetify/components"
import type {ComponentSlots} from 'vue-component-type-helpers'


defineSlots<ComponentSlots<typeof VTextarea>>()

type Props = /* @vue-ignore */InstanceType<typeof VTextarea>["$props"]

defineProps<Props>()

const textareaProps = computed(() => {
  return useAttrs()
})

const internalValue = defineModel<string>()
</script>

<style lang="scss" scoped>
:deep() {
  .v-textarea {
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
