<script lang="ts">
import {defineComponent} from "vue"

/**
 * A code field, not a password field.
 *
 * inputmode="numeric" so phones open the number pad, autocomplete="one-time-code"
 * so iOS and Android offer the code straight from the SMS or mail — the single
 * biggest usability win in this whole flow, and the one every hand-rolled
 * version misses.
 */
export default defineComponent({
  name: "AppOtpInput",
  props: {
    modelValue: {type: String, default: ""},
    length:     {type: Number, default: 6},
    label:      {type: String, default: "Verification code"},
    loading:    {type: Boolean, default: false},
  },
  emits: ['update:modelValue', 'complete'],
  methods: {
    onInput(value: string) {
      const digits = (value ?? "").replace(/\D/g, "").slice(0, this.length)

      this.$emit('update:modelValue', digits)

      // Submitting on the last digit removes a tap that nobody wants to make.
      if (digits.length === this.length) this.$emit('complete', digits)
    },
  },
})
</script>

<template>
  <v-text-field
    autocomplete="one-time-code"
    autofocus
    class="app-otp-input"
    :disabled="loading"
    inputmode="numeric"
    :label="label"
    :maxlength="length"
    :model-value="modelValue"
    variant="outlined"
    @update:model-value="onInput"
  />
</template>

<style scoped lang="scss">
.app-otp-input :deep(input) {
  font-size: 1.5rem;
  letter-spacing: 0.4em;
  text-align: center;
}
</style>
