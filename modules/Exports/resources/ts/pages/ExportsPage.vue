<script lang="ts">
import {defineComponent} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import type {ExportRecord} from "@modules/Exports/resources/ts/composables/useExport"

/** History of this user's exports, with re-download and cleanup. */
export default defineComponent({
  name: "ExportsPage",
  components: {AppTimeAgo, AppEmptyState},
  data() {
    return {
      exports: [] as ExportRecord[],
      loading: false,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/exports").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.exports = response.data.data
    },
    statusColor(status: string): string {
      return {
        completed:  "success",
        failed:     "error",
        processing: "info",
        pending:    "warning",
      }[status] ?? "default"
    },
    download(record: ExportRecord) {
      this.$http.download(`/exports/${record.id}/download`)
    },
    async remove(record: ExportRecord) {
      if (!await this.$confirm("Delete this export and its file?", "Delete export")) return

      const response = await this.$http.delete(`/exports/${record.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      this.exports = this.exports.filter(e => e.id !== record.id)
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center flex-wrap mb-4 ga-2">
      <h1 class="text-h4">
        Exports
      </h1>
      <v-spacer />
      <v-btn
        variant="text"
        prepend-icon="refresh"
        @click="load"
      >
        Refresh
      </v-btn>
    </div>

    <v-card :loading="loading">
      <v-table v-if="exports.length">
        <thead>
          <tr>
            <th>Source</th>
            <th>Format</th>
            <th>Status</th>
            <th>Rows</th>
            <th>Created</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="record in exports"
            :key="record.id"
          >
            <td>{{ record.source }}</td>
            <td class="text-uppercase">
              {{ record.format }}
            </td>
            <td>
              <v-chip
                :color="statusColor(record.status)"
                size="small"
                label
              >
                {{ record.status }}
              </v-chip>
              <v-tooltip
                v-if="record.error"
                :text="record.error"
              >
                <template #activator="{ props: tip }">
                  <v-icon
                    v-bind="tip"
                    size="small"
                    color="error"
                    class="ml-1"
                  >
                    info
                  </v-icon>
                </template>
              </v-tooltip>
            </td>
            <td>{{ record.rowCount ?? "—" }}</td>
            <td><AppTimeAgo :value="record.createdAt" /></td>
            <td class="text-right">
              <v-btn
                v-if="record.downloadable"
                icon="download"
                size="small"
                variant="text"
                aria-label="Download export"
                @click="download(record)"
              />
              <v-btn
                icon="delete"
                size="small"
                variant="text"
                aria-label="Delete export"
                @click="remove(record)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="download"
        title="No exports yet"
        description="Start one from any listing's Export button."
      />
    </v-card>
  </v-container>
</template>
