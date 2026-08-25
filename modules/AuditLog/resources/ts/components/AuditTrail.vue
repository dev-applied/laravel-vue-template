<script lang="ts" setup>
import {onMounted, ref, watch} from "vue"
import useHttp from "@/composables/useHttp"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

export interface AuditChange { field: string, from: unknown, to: unknown }
export interface AuditEntry {
  id:        number
  event:     string
  subject:   {type: string, id: number | string}
  user?:     {id: number, name: string} | null
  changes:   AuditChange[]
  ipAddress: string | null
  createdAt: string
}

/**
 * Drop onto any record page to show that record's history:
 *
 *   <AuditTrail subject-type="App\Models\Item" :subject-id="item.id" />
 *
 * subjectType is the FQCN as stored (or the model's morph alias if the project
 * maps one) — it is matched server-side against auditable_type.
 */
const props = withDefaults(defineProps<{
  subjectType: string
  subjectId:   number | string
  limit?:      number
}>(), {
  limit: 25,
})

const {$http, $error} = useHttp()
const entries = ref<AuditEntry[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  const response = await $http.get('/audit-logs', {
    params: {
      auditable_type: props.subjectType,
      auditable_id:   props.subjectId,
      itemsPerPage:   props.limit,
    },
  }).catch((e: any) => e)
  loading.value = false

  if ($error(response.status, response.data?.message, response.data?.errors, false)) return

  entries.value = response.data.data
}

function eventColor(event: string): string {
  return {created: 'success', updated: 'info', deleted: 'error', restored: 'warning'}[event] ?? 'default'
}

/** null / objects render badly raw; make them readable in one line. */
function display(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'object') return JSON.stringify(value)

  return String(value)
}

onMounted(load)
watch(() => [props.subjectType, props.subjectId], load)
</script>

<template>
  <v-card :loading="loading">
    <v-card-title class="text-subtitle-1">
      History
    </v-card-title>
    <v-divider />

    <v-timeline
      v-if="entries.length"
      side="end"
      density="compact"
      class="pa-4"
    >
      <v-timeline-item
        v-for="entry in entries"
        :key="entry.id"
        :dot-color="eventColor(entry.event)"
        size="x-small"
      >
        <div class="d-flex align-center ga-2">
          <strong class="text-body-2 text-capitalize">{{ entry.event }}</strong>
          <span class="text-caption text-medium-emphasis">
            by {{ entry.user?.name ?? "system" }} · <AppTimeAgo :value="entry.createdAt" />
          </span>
        </div>

        <v-table
          v-if="entry.changes.length"
          density="compact"
          class="mt-1 text-caption"
        >
          <tbody>
            <tr
              v-for="change in entry.changes"
              :key="change.field"
            >
              <td class="font-weight-medium">
                {{ change.field }}
              </td>
              <td class="text-medium-emphasis">
                {{ display(change.from) }}
              </td>
              <td>→ {{ display(change.to) }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-timeline-item>
    </v-timeline>

    <v-card-text
      v-else-if="!loading"
      class="text-medium-emphasis"
    >
      No recorded changes.
    </v-card-text>
  </v-card>
</template>
