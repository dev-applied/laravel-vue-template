<script lang="ts">
import {defineComponent, type PropType} from "vue"
import useTags, {useRecordTags, type Tag} from "@modules/Tags/resources/ts/composables/useTags"

/**
 * Tag chips on a record, with autocomplete over the existing pool.
 *
 * Free text is allowed on purpose: forcing people to pick from a list means
 * the list never grows, and the slug normalisation is what keeps it tidy
 * instead.
 */
export default defineComponent({
  name: "AppTagInput",
  props: {
    /** The alias the project registered in TaggableRegistry. */
    type:     {type: String, required: true},
    recordId: {type: [Number, String], required: true},
    /** Scopes the suggestion pool. Match the model's tagType(). */
    tagType:  {type: String as PropType<string | null>, default: null},
    label:    {type: String, default: "Tags"},
    readonly: {type: Boolean, default: false},
  },
  emits: ['change'],
  setup(props) {
    const pool   = useTags(props.tagType)
    const record = useRecordTags(props.type, props.recordId)

    // Flattened, not `return {pool, record}`. Options-API setup() unwraps refs
    // only at the TOP level of what it returns, so `this.pool.tags` stayed a
    // Ref: `.map` was undefined and `record.saving` was an always-truthy Ref
    // object rather than a boolean. Aliased because both composables export
    // `tags` and `fetch`.
    return {
      poolTags:    pool.tags,
      fetchPool:   pool.fetch,
      recordTags:  record.tags,
      saving:      record.saving,
      fetchRecord: record.fetch,
      syncRecord:  record.sync,
    }
  },
  data() {
    return {
      model: [] as string[],
      search: "",
    }
  },
  computed: {
    suggestions(): string[] {
      return this.poolTags.map((t: Tag) => t.name)
    },
  },
  watch: {
    recordTags: {
      handler(tags: Tag[]) {
        this.model = tags.map((t) => t.name)
      },
      immediate: true,
    },
  },
  async mounted() {
    await Promise.all([this.fetchRecord(), this.fetchPool()])
  },
  methods: {
    async commit(names: string[]) {
      if (this.readonly) return

      const ok = await this.syncRecord(names)
      if (!ok) return

      // Refresh the pool so a brand-new tag becomes suggestable immediately.
      await this.fetchPool()
      this.$emit('change', this.recordTags)
    },
  },
})
</script>

<template>
  <v-combobox
    v-model="model"
    chips
    closable-chips
    :disabled="readonly"
    hide-details="auto"
    :items="suggestions"
    :label="label"
    :loading="saving"
    multiple
    :search="search"
    variant="outlined"
    @update:model-value="commit"
    @update:search="search = $event"
  >
    <!-- `item` is the raw value in Vuetify v4 (the wrapper is `internalItem`),
         and these items are plain strings, so it is the tag name directly. -->
    <template #chip="{props: chipProps, item}">
      <v-chip
        v-bind="chipProps"
        density="comfortable"
        size="small"
        :text="item"
      />
    </template>

    <template #no-data>
      <v-list-item
        v-if="search"
        :subtitle="`Press enter to create “${search}”`"
        title="New tag"
      />
    </template>
  </v-combobox>
</template>
