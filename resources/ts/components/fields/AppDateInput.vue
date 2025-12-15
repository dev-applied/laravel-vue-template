<template>
  <v-text-field
    ref="dateInput"
    :counter="false"
    :error-messages="dateError"
    :focused="menu || isFocused"
    :model-value="maskedDate"
    :prepend-inner-icon="prependInnerIcon"
    label="Date"
    placeholder="mm/dd/yyyy"
    @blur="handleBlur"
    @focus="isFocused = true"
    @click:clear="clearDate"
    @click:prepend-inner="menu = !menu"
  >
    <v-menu
      v-model="menu"
      :close-on-content-click="false"
      :open-on-click="false"
      activator="parent"
      min-width="0"
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
import {IMask} from 'vue-imask'

export default {
  props: {
    modelValue: {
      type: String,
      default: '',
    },
    prependInnerIcon: {
      type: String,
      default: '$calendar',
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      maskedDate: '',
      dateError: '',
      mask: null as any,
      menu: false,
      isFocused: false,
      isUpdatingFromMask: false,
    }
  },
  computed: {
    datePickerModel: {
      get() {
        const iso = this.toIsoString(this.maskedDate)

        return iso ? new Date(iso) : null
      },
      set(value: Date | null) {
        if (!value) {
          this.maskedDate = ''
          if (this.mask) {
            this.mask.value = ''
          }
          this.menu = false
          this.$emit('update:modelValue', '')

          return
        }

        const iso = this.dateToIso(value)
        const formatted = this.isoToMasked(iso)
        this.maskedDate = formatted
        if (this.mask) {
          this.isUpdatingFromMask = true
          this.mask.value = formatted
          this.isUpdatingFromMask = false
        }
        this.menu = false
        this.dateError = ''
        this.$emit('update:modelValue', iso)
      },
    },
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(newVal: string) {
        if (this.isUpdatingFromMask) {
          return
        }

        const formatted = this.isoToMasked(newVal)
        this.maskedDate = formatted

        if (this.mask) {
          this.isUpdatingFromMask = true
          this.mask.value = formatted
          this.isUpdatingFromMask = false
        }
      },
    },
  },
  mounted() {
    this.createMask()
  },
  methods: {
    isoToMasked(date: string | null | undefined): string {
      if (!date) {
        return ''
      }

      const cleaned = date.toString().split('T')[0]
      const [year, month, day] = cleaned.split('-')

      if (!year || !month || !day) {
        return ''
      }

      return `${month.padStart(2, '0')}/${day.padStart(2, '0')}/${year}`
    },
    maskedToParts(date: string | null | undefined): { month: number; day: number; year: number } | null {
      if (!date) {
        return null
      }

      const raw = date.replace(/_/g, '')
      const [monthStr, dayStr, yearStr] = raw.split('/')

      if (!monthStr || !dayStr || !yearStr) {
        return null
      }

      if (monthStr.length !== 2 || dayStr.length !== 2 || yearStr.length !== 4) {
        return null
      }

      const month = Number(monthStr)
      const day = Number(dayStr)
      const year = Number(yearStr)

      if (!month || !day || !year) {
        return null
      }

      return {month, day, year}
    },
    toIsoString(masked: string | null | undefined): string {
      const parts = this.maskedToParts(masked)

      if (!parts) {
        return ''
      }

      const {month, day, year} = parts
      const date = new Date(year, month - 1, day)

      if (Number.isNaN(date.getTime())) {
        return ''
      }

      if (
        date.getFullYear() !== year ||
        date.getMonth() + 1 !== month ||
        date.getDate() !== day
      ) {
        return ''
      }

      const isoMonth = `${month}`.padStart(2, '0')
      const isoDay = `${day}`.padStart(2, '0')

      return `${year}-${isoMonth}-${isoDay}`
    },
    dateToIso(date: Date): string {
      const year = date.getFullYear()
      const month = `${date.getMonth() + 1}`.padStart(2, '0')
      const day = `${date.getDate()}`.padStart(2, '0')

      return `${year}-${month}-${day}`
    },
    async handleBlur() {
      this.isFocused = false
      await this.$nextTick()
      this.validateDate()
    },
    validateDate() {
      this.dateError = ''

      if (!this.maskedDate) {
        this.$emit('update:modelValue', '')

        return
      }

      const iso = this.toIsoString(this.maskedDate)

      if (!iso) {
        this.dateError = 'Invalid date. Please enter a correct date (mm/dd/yyyy).'
        this.$emit('update:modelValue', '')

        return
      }

      this.dateError = ''
      this.$emit('update:modelValue', iso)
    },
    clearDate() {
      this.maskedDate = ''
      if (this.mask) {
        this.isUpdatingFromMask = true
        this.mask.value = ''
        this.isUpdatingFromMask = false
      }
      this.dateError = ''
      this.$emit('update:modelValue', '')
    },
    createMask() {
      const input = (this.$refs.dateInput as any)?.$el?.querySelector('input') as HTMLInputElement | null

      if (!input) {
        return
      }

      this.mask = IMask(input, {
        mask: 'MM/DD/YYYY',
        lazy: false,
        overwrite: false,
        blocks: {
          MM: {
            mask: IMask.MaskedRange,
            from: 1,
            to: 12,
            autofix: 'pad',
          },
          DD: {
            mask: IMask.MaskedRange,
            from: 1,
            to: 31,
            autofix: 'pad',
          },
          YYYY: {
            mask: IMask.MaskedRange,
            from: 1900,
            to: 2100,
            autofix: false,
          },
        },
        placeholderChar: '_',
      })

      this.mask.on('accept', () => {
        this.isUpdatingFromMask = true
        this.maskedDate = this.mask.value
        this.isUpdatingFromMask = false
      })

      this.mask.on('complete', () => {
        const iso = this.toIsoString(this.mask.value)

        if (iso) {
          this.dateError = ''
          this.$emit('update:modelValue', iso)
        }
      })

      if (this.modelValue) {
        const formatted = this.isoToMasked(this.modelValue)
        this.isUpdatingFromMask = true
        this.mask.value = formatted
        this.maskedDate = this.mask.value
        this.isUpdatingFromMask = false
      }
    },
  },
}
</script>
