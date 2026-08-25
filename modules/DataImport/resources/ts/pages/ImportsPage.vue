<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

interface ImportRow {
  id: number, target: string, originalName: string, status: string,
  totalRows: number, importedRows: number, failedRows: number,
  errors: {line: number, errors: string[]}[] | null, errorsTrimmed: boolean,
  failureReason: string | null, createdAt: string
}

export default defineComponent({
  name: "ImportsPage",
  components: {AppEmptyState, AppTimeAgo},
  data() {
    return {
      imports: [] as ImportRow[],
      loading: false,
      expanded: null as number | null,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/imports").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.imports = response.data.data
    },
    statusColor(status: string): string {
      return {completed: "success", failed: "error", processing: "info", uploaded: "warning"}[status] ?? "default"
    },
    async remove(row: ImportRow) {
      if (!await this.$confirm("Delete this import and its uploaded file?", "Delete import")) return

      const response = await this.$http.delete(`/imports/${row.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      this.imports = this.imports.filter(i => i.id !== row.id)
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">
        Imports
      </h1>
      <v-spacer />
      <v-btn
        variant="text"
        prepend-icon="refresh"
        @click="load"
      >
        Refresh
      </v-btn>
      <v-btn
        color="primary"
        prepend-icon="add"
        @click="$router.push($routeTo(ROUTES.IMPORT_NEW))"
      >
        New import
      </v-btn>
    </div>

    <v-card :loading="loading">
      <v-table v-if="imports.length">
        <thead>
          <tr>
            <th>File</th>
            <th>Target</th>
            <th>Status</th>
            <th>Imported</th>
            <th>Failed</th>
            <th>When</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <template
            v-for="row in imports"
            :key="row.id"
          >
            <tr>
              <td>{{ row.originalName }}</td>
              <td>{{ row.target }}</td>
              <td>
                <v-chip
                  :color="statusColor(row.status)"
                  size="small"
                  label
                >
                  {{ row.status }}
                </v-chip>
              </td>
              <td>{{ row.importedRows }} / {{ row.totalRows }}</td>
              <td>
                <v-btn
                  v-if="row.failedRows"
                  size="small"
                  variant="text"
                  color="error"
                  @click="expanded = expanded === row.id ? null : row.id"
                >
                  {{ row.failedRows }}
                </v-btn>
                <span v-else>0</span>
              </td>
              <td><AppTimeAgo :value="row.createdAt" /></td>
              <td class="text-right">
                <v-btn
                  icon="delete"
                  size="small"
                  variant="text"
                  aria-label="Delete import"
                  @click="remove(row)"
                />
              </td>
            </tr>
            <tr v-if="expanded === row.id">
              <td
                colspan="7"
                class="pa-0"
              >
                <v-alert
                  v-if="row.failureReason"
                  type="error"
                  variant="tonal"
                  class="ma-3"
                >
                  {{ row.failureReason }}
                </v-alert>
                <v-table
                  density="compact"
                  class="text-caption"
                >
                  <thead>
                    <tr><th>Line</th><th>Problem</th></tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="error in row.errors ?? []"
                      :key="error.line"
                    >
                      <td>{{ error.line }}</td>
                      <td>{{ error.errors.join("; ") }}</td>
                    </tr>
                  </tbody>
                </v-table>
                <p
                  v-if="row.errorsTrimmed"
                  class="text-caption text-medium-emphasis pa-3"
                >
                  Only the first {{ (row.errors ?? []).length }} problems are kept.
                </p>
              </td>
            </tr>
          </template>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="upload_file"
        title="No imports yet"
        description="Upload a spreadsheet to get started."
      />
    </v-card>
  </v-container>
</template>
