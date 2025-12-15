<template>
  <v-text-field
    ref="inputRef"
    v-model="model"
    :class="$attrs.class"
    :maxlength="$attrs.maxlength || 16"
    v-bind="attrs"
    validate-on="blur"
    @click:clear="clear"
  >
    <template #message="message">
      <span v-html="message.message" />
    </template>
  </v-text-field>
</template>


<script lang="ts" setup>
import {CurrencyDisplay, useCurrencyInput} from 'vue-currency-input'
import {type PropType, useAttrs, watch} from 'vue'
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
  displayCurrencySymbol: {
    type: Boolean,
    default: false
  }
})

const attrs = useAttrs()

const {inputRef, setOptions} = useCurrencyInput({
  currency: 'USD',
  currencyDisplay: props.displayCurrencySymbol ? CurrencyDisplay.narrowSymbol : CurrencyDisplay.hidden,
  precision: Number(props.decimals),
})

const clear = () => {
  model.value = null
}

watch(() => props.decimals, (newDecimals) => {
  setOptions({
    precision: Number(newDecimals),
    currency: 'USD'
  })
})
</script>
