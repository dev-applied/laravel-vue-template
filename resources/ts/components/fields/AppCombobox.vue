<template>
  <v-combobox
    ref="combobox"
    v-model="internalValue"
    v-model:search="search"
    :hide-no-data="!search"
    chips
    closable-chips
    :hide-details="'auto'"
    v-bind="comboboxProps"
  >
    <template #no-data>
      <v-list-item
        v-if="search"
        @click="addItem"
      >
        <span class="mr-3">{{ messageWhenNoData }}</span>
        <v-chip
          label
          size="small"
          variant="flat"
        >
          {{ search }}
        </v-chip>
      </v-list-item>
    </template>
    <template #message="message">
      <span v-html="message.message" />
    </template>
  </v-combobox>
</template>

<script lang="ts" setup>
import {computed, ref, useAttrs} from "vue"
import {VCombobox} from "vuetify/components"
import type {ComponentSlots} from 'vue-component-type-helpers'
import mapKeys from "lodash.mapkeys"


export interface AdditionalProps {
  messageWhenNoData?: string,
}

defineSlots<ComponentSlots<typeof VCombobox>>()

type Props = AdditionalProps & /* @vue-ignore */InstanceType<typeof VCombobox>["$props"]

withDefaults(defineProps<Props>(), {
  multiple: true,
  messageWhenNoData: 'Create'
})

// Make all useAttrs keys camelCase
const comboboxProps = computed(() => {
  const attrs = mapKeys(useAttrs(), (_value, key) => key.replace(/-(\w)/g, (_match, letter) => letter.toUpperCase()))
  // Typed at the source: v-bind of a loosely-typed object merges with the
  // literal attributes beside it, so an untyped spread widens `chips`,
  // `hide-details` and the rest to `unknown`/`string`.
  return VCombobox.filterProps(attrs) as Partial<InstanceType<typeof VCombobox>["$props"]>
})

const internalValue = defineModel<string[]>()

const search = ref('')
const combobox = ref<InstanceType<typeof VCombobox> | null>(null)

function addItem() {
  if (!combobox.value) return
  // Vuetify's ListItem also requires `children` and `type`; omitting them made
  // this a structurally different object that only happened to work because the
  // consumer reads `props` and `value`. Spelled out so a future Vuetify change
  // to the shape is a compile error rather than a silent behaviour change.
  combobox.value.select({
    props: {
      title: search.value,
      value: search.value,
    },
    raw: search.value,
    title: search.value,
    value: search.value,
    children: undefined,
    type: 'item',
  })
}
</script>

<style lang="scss" scoped>
:deep() {
  .v-combobox {
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
