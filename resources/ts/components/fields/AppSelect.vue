<template>
  <v-select
    v-model="internalValue"
    class="field"
    hide-details="auto"
    v-bind="selectProps"
  />
</template>

<script lang="ts" setup>
import {computed, ref, useAttrs, watch} from "vue"
import {VSelect} from "vuetify/components"
import type {ComponentSlots} from 'vue-component-type-helpers'
import mapKeys from "lodash.mapkeys"


export interface AdditionalProps {
  modelValue?: any,
}

defineSlots<ComponentSlots<typeof VSelect>>()

type Props = AdditionalProps & /* @vue-ignore */InstanceType<typeof VSelect>["$props"]

const props = defineProps<Props>()

// Make all useAttrs keys camelCase
const selectProps = computed(() => {
  const attrs = mapKeys(useAttrs(), (_value, key) => key.replace(/-(\w)/g, (_match, letter) => letter.toUpperCase()))
  return VSelect.filterProps(attrs)
})

const emits = defineEmits({
  'update:modelValue': (_value: any) => true,
})

const internalValue = ref(props.modelValue)

watch(internalValue, (value) => {
  emits('update:modelValue', value)
})

watch(() => props.modelValue, (value) => {
  internalValue.value = value
})
</script>

<style lang="scss" scoped>
:deep() {
  .v-select {
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
