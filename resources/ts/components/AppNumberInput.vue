<template>
  <v-text-field
    ref="inputRef"
    :model-value="formattedValue"
    v-bind="textFieldProps"
    :class="$attrs.class"
    @click:clear="clear"
    validate-on="blur"
    :maxlength="$attrs.maxlength || 16"
    :suffix="$attrs.suffix"
    :prefix="$attrs.prefix"
    @input="handleInput"
  />
</template>


<script setup lang="ts">
import {CurrencyDisplay, useCurrencyInput} from 'vue-currency-input'
import {nextTick, type PropType, useAttrs, watch} from 'vue'
import {VTextField} from "vuetify/components"

const model = defineModel({
  default: null,
  type: [Number, String] as PropType<string | number | null>,
})

defineEmits([
  'change',
])


defineOptions({inheritAttrs: false})
const props = defineProps({
  decimals: {
    type: [Number, String],
    default: 2
  },
})

const attrs = useAttrs()

const textFieldProps = VTextField.filterProps(attrs)

const { inputRef, formattedValue, setValue } = useCurrencyInput({
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
  return value === null ? null : Number(value.toString().replace(/[^0-9.]/g, '')) || null
}

watch(model, (value: string | number | null) => {
  setValue(onlyNumbers(value))
})
</script>
