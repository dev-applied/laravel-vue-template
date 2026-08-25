<script lang="ts">
import {defineComponent, type PropType} from "vue"
import useTags, {type Tag} from "@modules/Tags/resources/ts/composables/useTags"

/**
 * A tag filter for a listing screen. Emits slugs, which is what the backend
 * scopes match on — names would break the moment a tag is renamed.
 */
export default defineComponent({
  name: "AppTagFilter",
  props: {
    modelValue: {type: Array as PropType<string[]>, default: () => []},
    tagType:    {type: String as PropType<string | null>, default: null},
    label:      {type: String, default: "Tags"},
  },
  emits: ['update:modelValue'],
  setup(props) {
    return useTags(props.tagType)
  },
  mounted() {
    this.fetch()
  },
  methods: {
    itemsFor(): {title: string, value: string, count: number}[] {
      return this.tags.map((t: Tag) => ({
        title: t.name,
        value: t.slug,
        count: t.usage_count ?? t.usageCount ?? 0,
      }))
    },
  },
})
</script>

<template>
  <v-select
    chips
    clearable
    hide-details="auto"
    :items="itemsFor()"
    :label="label"
    :loading="loading"
    :model-value="modelValue"
    multiple
    variant="outlined"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #item="{props: itemProps, item}">
      <v-list-item v-bind="itemProps">
        <template #append>
          <!-- item.count, not item.raw.count. Vuetify v4 changed this slot:
               `item` IS the raw item now and the wrapper moved to
               `internalItem`, so `.raw` is undefined and reading through it
               throws. The kernel's AppAutoComplete took the other half of the
               same change in 8ae8c70e. -->
          <span class="text-body-small text-medium-emphasis">{{ item.count }}</span>
        </template>
      </v-list-item>
    </template>
  </v-select>
</template>
