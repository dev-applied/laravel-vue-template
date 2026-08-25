<script lang="ts">
import {defineComponent} from "vue"
import {useMessageStore} from "@/stores/message"
import AppWizardSteps from "@/components/AppWizardSteps.vue"
import AppSelect from "@/components/fields/AppSelect.vue"

interface Target {key: string, label: string, fields: Record<string, string>, required: string[]}
interface DryRun {total: number, imported: number, failed: number, errors: {line: number, errors: string[]}[]}

/**
 * Upload → map → dry-run → commit.
 *
 * The dry run is not optional politeness: an import writes to the database, and
 * showing exactly which rows would fail BEFORE anything is written is the
 * difference between a usable importer and one people work around.
 */
export default defineComponent({
  name: "ImportWizardPage",
  components: {AppWizardSteps, AppSelect},
  data() {
    return {
      step: 1,
      targets: [] as Target[],
      // Distinguishes "still asking" from "asked, and there are none", so the
      // empty-state notice does not flash on every load.
      loadingTargets: true,
      targetKey: null as string | null,
      file: null as File | null,
      uploading: false,
      importId: null as number | null,
      headers: [] as string[],
      sample: [] as string[][],
      mapping: {} as Record<string, string>,
      dryRun: null as DryRun | null,
      running: false,
    }
  },
  computed: {
    target(): Target | null {
      return this.targets.find(t => t.key === this.targetKey) ?? null
    },
    steps() {
      return [
        {title: "File", valid: !!this.importId},
        {title: "Map columns", valid: this.requiredMapped},
        {title: "Preview", valid: !!this.dryRun},
        {title: "Import"},
      ]
    },
    requiredMapped(): boolean {
      return (this.target?.required ?? []).every(field => !!this.mapping[field])
    },
    headerOptions() {
      return [{title: "— not mapped —", value: ""}, ...this.headers.map(h => ({title: h, value: h}))]
    },
  },
  async created() {
    const response = await this.$http.get("/imports/targets").catch(e => e)
    this.loadingTargets = false
    if (this.$error(response.status, response.data?.message)) return

    this.targets = response.data.targets
  },
  methods: {
    async upload() {
      if (!this.file || !this.targetKey) return

      this.uploading = true
      const body = new FormData()
      body.append("target", this.targetKey)
      body.append("file", this.file)

      const response = await this.$http.post("/imports", body).catch(e => e)
      this.uploading = false
      if (this.$error(response.status, response.data?.message)) return

      this.importId = response.data.import.id
      this.headers  = response.data.headers
      this.sample   = response.data.sample
      this.mapping  = {...response.data.suggested}
      this.step     = 2
    },
    async preview() {
      const response = await this.$http
        .post(`/imports/${this.importId}/dry-run`, {mapping: this.cleanMapping()})
        .catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      this.dryRun = response.data.result
      this.step   = 3
    },
    async commit() {
      this.running = true
      const response = await this.$http
        .post(`/imports/${this.importId}/run`, {mapping: this.cleanMapping()})
        .catch(e => e)
      this.running = false
      if (this.$error(response.status, response.data?.message)) return

      useMessageStore().addSuccess("Import started.")
      await this.$router.push(this.$routeTo(this.ROUTES.IMPORTS))
    },
    cleanMapping(): Record<string, string> {
      // Drop "— not mapped —" entries; the API validates mapping.* as required.
      return Object.fromEntries(Object.entries(this.mapping).filter(([, v]) => !!v))
    },
    async onFinish() {
      // steps.length, not a literal. AppWizardSteps only emits `finish` on the
      // LAST step, and there are four — so `=== 3` could never be true and
      // "Start import" did nothing at all: no request, no error, no spinner.
      if (this.step === this.steps.length) await this.commit()
    },
  },
})
</script>

<template>
  <v-container>
    <h1 class="text-headline-large mb-4">
      Import data
    </h1>

    <AppWizardSteps
      v-model="step"
      :steps="steps"
      finish-text="Start import"
      :finishing="running"
      @finish="onFinish"
    >
      <!-- AppWizardSteps exposes one named slot per step (step-1, step-2, …),
           and owns the Previous/Next buttons. The explicit buttons below do the
           async work for a transition; `steps[].valid` keeps the wizard's own
           Next disabled until that work has happened, so both paths agree. -->
      <template #step-1>
        <div>
          <!-- The registry is an allow-list, so a fresh install has nothing in
               it and this screen is the first place anyone finds that out. An
               empty selector reads as a broken page; say what is missing. -->
          <v-alert
            v-if="!loadingTargets && !targets.length"
            type="info"
            variant="tonal"
            class="mb-4"
          >
            <div class="font-weight-medium">
              No import targets are registered
            </div>
            <div class="text-body-medium">
              A project decides what may be imported, because an import writes to the
              database. Register one with <code>ImportRegistry::register()</code> from
              <code>AppServiceProvider::boot()</code> — see the DataImport README.
            </div>
          </v-alert>
          <AppSelect
            v-show="targets.length"
            v-model="targetKey"
            :items="targets.map(t => ({title: t.label, value: t.key}))"
            label="What are you importing?"
          />
          <v-file-input
            v-model="file"
            label="CSV file"
            accept=".csv,text/csv"
            prepend-icon="upload_file"
          />
          <v-btn
            color="primary"
            :disabled="!file || !targetKey"
            :loading="uploading"
            @click="upload"
          >
            Upload and continue
          </v-btn>
        </div>
      </template>

      <template #step-2>
        <div>
          <p class="text-body-medium text-medium-emphasis mb-3">
            Matching columns are pre-selected. Fields marked required must be mapped.
          </p>
          <v-row>
            <v-col
              v-for="(label, field) in target?.fields ?? {}"
              :key="field"
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="mapping[field]"
                :items="headerOptions"
                :label="target?.required.includes(String(field)) ? `${label} *` : label"
              />
            </v-col>
          </v-row>

          <v-table
            v-if="sample.length"
            density="compact"
            class="mt-3 text-body-small"
          >
            <thead>
              <tr>
                <th
                  v-for="header in headers"
                  :key="header"
                >
                  {{ header }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in sample"
                :key="i"
              >
                <td
                  v-for="(cell, j) in row"
                  :key="j"
                >
                  {{ cell }}
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-btn
            color="primary"
            class="mt-3"
            :disabled="!requiredMapped"
            @click="preview"
          >
            Preview
          </v-btn>
        </div>
      </template>

      <template #step-3>
        <div v-if="dryRun">
          <div class="d-flex ga-4 mb-3">
            <v-chip
              color="info"
              label
            >
              {{ dryRun.total }} rows
            </v-chip>
            <v-chip
              color="success"
              label
            >
              {{ dryRun.imported }} will import
            </v-chip>
            <v-chip
              :color="dryRun.failed ? 'error' : 'default'"
              label
            >
              {{ dryRun.failed }} will fail
            </v-chip>
          </div>

          <v-alert
            v-if="dryRun.failed"
            type="warning"
            variant="tonal"
            class="mb-3"
          >
            Failing rows are skipped — the rest still import.
          </v-alert>

          <v-table
            v-if="dryRun.errors.length"
            density="compact"
            class="text-body-small"
          >
            <thead>
              <tr><th>Line</th><th>Problem</th></tr>
            </thead>
            <tbody>
              <tr
                v-for="error in dryRun.errors"
                :key="error.line"
              >
                <td>{{ error.line }}</td>
                <td>{{ error.errors.join("; ") }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </template>

      <template #step-4>
        <p class="text-body-medium">
          Ready to import {{ dryRun?.imported ?? 0 }} rows. Press
          <strong>Start import</strong> to begin — it runs in the background and
          appears in your import history.
        </p>
      </template>
    </AppWizardSteps>
  </v-container>
</template>
