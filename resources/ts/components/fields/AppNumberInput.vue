<template>
  <div class="text-left">
    <div class="mb-2 d-flex ga-4 align-center justify-space-between">
      <div class="label">
        {{ label }}<span
          v-if="required"
          class="text-error"
        >*</span>
        <slot name="label-append" />
      </div>
    </div>
    <v-text-field
      ref="inputRef"
      :class="$attrs.class"
      :maxlength="$attrs.maxlength || 16"
      :model-value="formattedValue"
      v-bind="attrs"
      validate-on="blur"
      @click:clear="clear"
      @update:model-value="handleInput"
    >
      <template #message="message">
        <span v-html="message.message" />
      </template>
    </v-text-field>
  </div>
</template>


<script lang="ts" setup>
import {CurrencyDisplay, useCurrencyInput} from 'vue-currency-input'
import {nextTick, type PropType, useAttrs, watch} from 'vue'
import {VTextField} from "vuetify/components"

const model = defineModel({
  default: null,
  type: [Number, String] as PropType<string | number | null>,
})

defineOptions({inheritAttrs: false})
const props = defineProps({
  decimals: {
    type: [Number, String],
    default: 2
  },
  label: {
    type: String,
    default: ''
  },
  required: {
    type: Boolean,
    default: false
  },
})

const attrs = useAttrs()

const {inputRef, formattedValue, setValue, setOptions} = useCurrencyInput({
  currency: 'USD',
  currencyDisplay: CurrencyDisplay.hidden,
  precision: Number(props.decimals),
})

const clear = () => {
  setValue(null)
  model.value = null
}


const handleInput = (e) => {
  nextTick(() => {
    e.target.value = formattedValue.value
  })
}

function onlyNumbers(value: string | number | null): number | null {
  if (value === null) return null
  const numericValue = Number(value.toString().replace(/[^0-9.]/g, ''))
  return isNaN(numericValue) ? null : numericValue
}

watch(model, (value: string | number | null) => {
  setValue(onlyNumbers(value))
})
watch(() => props.decimals, (newDecimals) => {
  setOptions({
    precision: Number(newDecimals),
    currency: 'USD'
  })
})
</script>
