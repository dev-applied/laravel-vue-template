<template>
  <v-text-field
    v-model="maskedDate"
    label="Date"
    placeholder="mm/dd/yyyy"
    ref="dateInput"
    :focused="menu || isFocused"
    :error-messages="dateError"
    @focus="isFocused = true"
    @blur="validateDate"
    :prepend-inner-icon="prependInnerIcon"
    @click:prepend-inner="menu = !menu"
    :counter="false"
    @input="onAccept"
  >
    <v-menu
      v-model="menu"
      activator="parent"
      min-width="0"
      :close-on-content-click="false"
      :open-on-click="false"
    >
      <v-date-picker
        v-model="datePickerModel"
        hide-header
        @mousedown="e => e.preventDefault()"
      />
    </v-menu>
  </v-text-field>
</template>

<script lang="ts">
import { IMask } from 'vue-imask'

export default {
  props: {
    modelValue: {
      type: String,
      default: '',
    },
    prependInnerIcon: {
      type: String,
      default: '$calendar',
    }
  },
  emits: ['update:modelValue'],
  data() {
    return {
      maskedDate: '',
      dateError: '', // To store validation error messages
      mask: null,
      menu: false,
      isFocused: false,
    }
  },
  computed: {
    datePickerModel: {
      get() {
        return new Date(this.maskedDate)
      },
      set(value) {
        this.maskedDate = this.format(value)
        this.menu = false
        this.mask?.updateControl()
      }
    }
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(newVal) {
        this.maskedDate = this.format(newVal)
        this.mask?.updateControl()
      },
    }
  },
  mounted() {
    this.createMask()
  },
  methods: {
    format(date) {
      if (!date) return ''
      if (date.toString().includes('GMT')) {
        date = date.toISOString().substring(0, 10)
      }
      const [year, month, day] = date.split('T')[0].substring(0, 10).split('-')

      const result = `${month ? month.replaceAll('_', ''):month}/${day ? day.replaceAll('_', ''):day}/${year ? year.replaceAll('_', ''):year}`
      return result
    },
    parse(date) {
      if (!date) return ''
      const [month, day, year] = date.split('T')[0].split('/')

      const result = `${year ? year.replaceAll('_', ''):''}-${month ? month.replaceAll('_', ''):''}-${day ? day.replaceAll('_', ''):''}`
      return result
    },
    async validateDate() {
      this.isFocused = false
      await this.$nextTick()
      this.dateError = '' // Clear previous errors
      const [month, day, year] = this.maskedDate.split('/')

      // Check if the date parts are valid
      if (month && day && year) {
        const date = new Date(Number(year), Number(Number(month) - 1), Number(day))
        const isValidDate = date && date.getFullYear()==year && (date.getMonth() + 1)==month && date.getDate()==day

        if (!isValidDate) {
          this.dateError = 'Invalid date. Please enter a correct date (mm/dd/yyyy).'
        }
      }
    },
    onComplete() {
      this.$emit('update:modelValue', this.mask.typedValue)
    },
    onAccept() {
      this.maskedDate = this.mask.value
    },
    createMask() {
      this.mask = IMask(this.$refs.dateInput.$el.querySelector('input') as HTMLInputElement, {
        mask: 'm/`d/`Y',
        lazy: false, // ensures slashes appear while typing
        overwrite: true,
        blocks: {
          m: {
            mask: IMask.MaskedRange,
            from: 1,
            to: 12,
            autofix: 'pad'
          },
          d: {
            mask: IMask.MaskedRange,
            from: 1,
            to: 31,
            autofix: 'pad'
          },
          Y: {
            mask: IMask.MaskedRange,
            from: 1900,
            to: 2100,
          },
        },
        format: this.format.bind(this),
        parse: this.parse.bind(this),
      })
        .on('complete', this.onComplete.bind(this))
      this.mask.updateValue()
    },
  },
}
</script>
