<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppChecklistEvidence from "@modules/Checklists/resources/ts/components/AppChecklistEvidence.vue"

interface Response {
  id: number
  label: string
  help: string | null
  status: string
  note: string | null
  file_id: number | null
  requires_evidence: boolean
  is_required: boolean
}

interface Checklist {
  id: number
  name: string
  status: string
  signed_by: string | null
  responses: Response[]
}

/**
 * One checklist, fillable. Drop it wherever the subject lives:
 *
 *   <AppChecklistPanel subject-type="vehicle" :subject-id="vehicle.id" />
 *
 * The module ships this rather than a page, because a checklist is always about
 * something — a vehicle, a site visit, a shipment — and the screen it belongs on
 * is the one already showing that thing.
 */
export default defineComponent({
  name: "AppChecklistPanel",
  components: {AppChecklistEvidence},
  props: {
    subjectType: {type: String, required: true},
    subjectId: {type: [Number, String] as PropType<number | string>, required: true},
  },
  data() {
    return {
      checklists: [] as Checklist[],
      templates: [] as {id: number, name: string}[],
      outstanding: [] as string[],
      selectedTemplate: null as number | null,
      loading: false,
      busy: null as number | null,
      answers: [
        {title: "Pass", value: "pass"},
        {title: "Fail", value: "fail"},
        {title: "N/A", value: "na"},
      ],
    }
  },
  async created() {
    await Promise.all([this.load(), this.loadTemplates()])
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/checklists", {
        params: {subject_type: this.subjectType, subject_id: this.subjectId},
      }).catch(e => e)
      this.loading = false

      if (this.$error(response.status, response.data?.message)) return

      this.checklists = response.data.data
    },
    async loadTemplates() {
      const response = await this.$http.get("/checklist-templates").catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      this.templates = response.data.data
    },
    async start() {
      if (!this.selectedTemplate) return

      const response = await this.$http.post("/checklists", {
        template_id: this.selectedTemplate,
        subject_type: this.subjectType,
        subject_id: this.subjectId,
      }).catch(e => e)

      if (this.$error(response.status, response.data?.message)) return

      this.selectedTemplate = null
      await this.load()
    },
    async answer(checklist: Checklist, item: Response, status: string) {
      this.busy = item.id
      const response = await this.$http
        .patch(`/checklists/${checklist.id}/responses/${item.id}`, {
          status, note: item.note, file_id: item.file_id,
        }).catch(e => e)
      this.busy = null

      if (this.$error(response.status, response.data?.message)) return

      Object.assign(checklist, response.data.data)
      this.outstanding = response.data.outstanding ?? []
    },
    async complete(checklist: Checklist) {
      const response = await this.$http.post(`/checklists/${checklist.id}/complete`).catch(e => e)

      // 422 here is not an error to shout about — it is the checklist telling
      // the user what is left, which is the whole point of listing every reason
      // rather than the first.
      if (response.status === 422) {
        this.outstanding = response.data.outstanding ?? []
        return
      }

      if (this.$error(response.status, response.data?.message)) return

      this.outstanding = []
      await this.load()
    },
  },
})
</script>

<template>
  <v-card :loading="loading">
    <v-card-title class="d-flex align-center flex-wrap ga-2">
      <span class="text-title-large">Checklists</span>
      <v-spacer />
      <v-select
        v-model="selectedTemplate"
        :items="templates"
        item-title="name"
        item-value="id"
        density="compact"
        hide-details
        placeholder="Start a checklist…"
        style="max-width: 260px"
        aria-label="Choose a checklist template"
      />
      <v-btn
        color="primary"
        :disabled="!selectedTemplate"
        @click="start"
      >
        Start
      </v-btn>
    </v-card-title>

    <v-divider />

    <v-card-text>
      <p
        v-if="!checklists.length && !loading"
        class="text-body-medium text-medium-emphasis mb-0"
      >
        No checklists have been started against this record yet.
      </p>

      <div
        v-for="checklist in checklists"
        :key="checklist.id"
        class="mb-6"
      >
        <div class="d-flex align-center flex-wrap ga-2 mb-2">
          <span class="text-title-large">{{ checklist.name }}</span>
          <v-chip
            :color="checklist.status === 'complete' ? 'success' : 'warning'"
            size="small"
            label
          >
            {{ checklist.status }}
          </v-chip>
          <v-spacer />
          <v-btn
            v-if="checklist.status !== 'complete'"
            color="success"
            variant="tonal"
            size="small"
            @click="complete(checklist)"
          >
            Complete
          </v-btn>
        </div>

        <v-alert
          v-if="outstanding.length"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-3"
          role="status"
          aria-live="polite"
        >
          <ul class="ps-4 mb-0">
            <li
              v-for="reason in outstanding"
              :key="reason"
            >
              {{ reason }}
            </li>
          </ul>
        </v-alert>

        <v-list density="comfortable">
          <v-list-item
            v-for="item in checklist.responses"
            :key="item.id"
          >
            <v-list-item-title>
              {{ item.label }}
              <v-chip
                v-if="!item.is_required"
                size="x-small"
                label
                class="ms-2"
              >
                Optional
              </v-chip>
            </v-list-item-title>
            <v-list-item-subtitle v-if="item.help">
              {{ item.help }}
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <AppChecklistEvidence
                  v-if="item.requires_evidence && checklist.status !== 'complete'"
                  v-model="item.file_id"
                  label="Photo"
                />
                <v-btn-toggle
                  :model-value="item.status"
                  density="compact"
                  variant="outlined"
                  divided
                  mandatory
                >
                  <v-btn
                    v-for="option in answers"
                    :key="option.value"
                    :value="option.value"
                    :disabled="checklist.status === 'complete' || busy === item.id"
                    size="small"
                    @click="answer(checklist, item, option.value)"
                  >
                    {{ option.title }}
                  </v-btn>
                </v-btn-toggle>
              </div>
            </template>
          </v-list-item>
        </v-list>
      </div>
    </v-card-text>
  </v-card>
</template>
