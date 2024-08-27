<template>
  <div
    :class="$attrs.class"
  >
    <v-date-input
      v-bind="$attrs"
      v-model="internalValue"
      @click:clear="internalValue = null"
    />
  </div>
</template>

<script lang="ts">
import {defineComponent, type PropType} from 'vue'

export default defineComponent({
  props: {
    modelValue: {
      type: [String, Date, Number, null] as PropType<string | Date | undefined | number | null>,
      default: null
    }
  },
  emits: ['update:modelValue'],
  computed: {
    internalValue: {
      get() {
        if (!this.modelValue) return null
        if (this.modelValue instanceof Date) return this.modelValue
        if (typeof this.modelValue === 'number') return new Date(this.modelValue)
        return new Date(this.formatYyyyMmDd(this.modelValue.replace(/T.*/, '')))
      },
      set(value: Date) {
        this.$emit('update:modelValue', value ? value.toISOString().replace(/T.*/, '') : null)
      }
    }
  },
  methods: {
    formatYyyyMmDd(value: string): string {
      const formattedValue = value.split('-')
        .map(d => d.padStart(2, '0'))
        .join('-')

      const offsetMin = (new Date().getTimezoneOffset() / -60)
      const offsetSign = offsetMin < 0 ? '-' : '+'
      const offsetValue = Math.abs(offsetMin).toString().padStart(2, '0')

      return `${formattedValue}T00:00:00.000${offsetSign}${offsetValue}:00`
    }
  }
})
</script>

<style scoped lang="scss">

</style>
