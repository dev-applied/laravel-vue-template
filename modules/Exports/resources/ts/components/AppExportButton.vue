<script lang="ts" setup>
import {ref} from "vue"
import useExport from "@modules/Exports/resources/ts/composables/useExport"

/**
 * Drop into any listing toolbar:
 *
 *   <AppExportButton source="items" :filters="filters" />
 *
 * Pass the SAME filter object the listing is using, so the file matches what
 * the user is looking at rather than the whole table.
 */
const props = withDefaults(defineProps<{
  source:   string
  filters?: Record<string, any>
  formats?: string[]
  label?:   string
}>(), {
  filters: () => ({}),
  formats: () => ['csv'],
  label:   'Export',
})

const emit = defineEmits<{ done: [] }>()

const {running, current, start, download} = useExport()
const failed = ref<string | null>(null)

async function run(format: string) {
  failed.value = null

  const record = await start(props.source, {format, filters: props.filters})
  if (!record) return

  if (record.status === 'failed') {
    failed.value = record.error ?? 'The export failed.'

    return
  }

  download(record)
  emit('done')
}
</script>

<template>
  <div class="d-inline-flex align-center">
    <!-- One format: a plain button. Several: a menu. -->
    <v-btn
      v-if="formats.length === 1"
      :loading="running"
      variant="tonal"
      prepend-icon="download"
      @click="run(formats[0])"
    >
      {{ label }}
    </v-btn>

    <v-menu v-else>
      <template #activator="{ props: act }">
        <v-btn
          v-bind="act"
          :loading="running"
          variant="tonal"
          prepend-icon="download"
        >
          {{ label }}
        </v-btn>
      </template>
      <v-list density="compact">
        <v-list-item
          v-for="format in formats"
          :key="format"
          :title="format.toUpperCase()"
          @click="run(format)"
        />
      </v-list>
    </v-menu>

    <span
      v-if="running && current?.status === 'processing'"
      class="text-body-small text-medium-emphasis ml-2"
    >
      Preparing…
    </span>

    <v-tooltip
      v-if="failed"
      :text="failed"
    >
      <template #activator="{ props: tip }">
        <v-icon
          v-bind="tip"
          color="error"
          class="ml-2"
        >
          error
        </v-icon>
      </template>
    </v-tooltip>
  </div>
</template>
