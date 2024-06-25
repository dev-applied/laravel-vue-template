<template>
  <v-menu
    v-model="menu"
    :close-on-content-click="false"
    eager
    min-width="auto"
    offset-y
    transition="scale-transition"
  >
    <template #activator="{ props }">
      <v-text-field
        :append-icon="noIcon ? undefined : 'mdi-calendar'"
        :label="label"
        :value="formatted"
        dense
        persistent-placeholder
        placeholder="Select Date"
        readonly
        v-bind="Object.assign({}, globalAttrs, props)"
      >
        <template
          v-if="!noIcon"
          #append
        >
          <v-btn
            height="28"
            icon
            width="28"
            @click="menu = !menu"
          >
            <v-icon size="20">
              mdi-calendar
            </v-icon>
          </v-btn>
        </template>
      </v-text-field>
    </template>
    <v-date-picker
      ref="datePicker"
      v-model="internalValue"
      :allowed-dates="allowedDates"
      :max="max"
      :min="min"
      @input="menu = false"
    />
  </v-menu>
</template>

<script lang="ts">
import moment from "moment"
import { defineComponent, type PropType } from "vue"

export default defineComponent({
  inheritAttrs: false,
  props: {
    modelValue: {
      type: String,
      default: undefined
    },
    label: {
      type: String,
      default: ""
    },
    noIcon: {
      type: Boolean,
      default: false
    },
    format: {
      type: String,
      default: undefined
    },
    allowedDates: {
      type: Function as PropType<(date: unknown) => boolean>,
      default: () => true
    },
    min: {
      type: String,
      default: null
    },
    max: {
      type: String,
      default: null
    }
  },
  data() {
    return {
      menu: false
    }
  },
  computed: {
    internalValue: {
      get() {
        return this.value ? moment.utc(this.value).format("YYYY-MM-DD") : this.value
      },
      set(val) {
        this.$emit("input", val)
      }
    },
    formatted() {
      if (this.format && this.internalValue) {
        return moment.utc(this.internalValue).format(this.format)
      }
      return this.internalValue
    },
    globalAttrs() {
      const attrs = { ...this.$attrs }

      // adding class and style for `v-bind`
      attrs.class = this.$vnode.data.staticClass
      attrs.style = this.$vnode.data.staticStyle

      return attrs
    }
  }
})
</script>
