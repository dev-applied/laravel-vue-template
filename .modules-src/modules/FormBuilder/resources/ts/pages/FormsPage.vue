<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"

interface FormRow {
  id: number, name: string, slug: string, is_published: boolean,
  is_public: boolean, closes_at: string | null, submissions_count: number,
  created_at: string
}

export default defineComponent({
  name: "FormsPage",
  components: {AppEmptyState, AppTimeAgo},
  data() {
    return {
      forms: [] as FormRow[],
      loading: false,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/forms").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.forms = response.data.data
    },
    async togglePublished(form: FormRow) {
      const response = await this.$http
        .put(`/forms/${form.id}`, {is_published: !form.is_published})
        .catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
    async destroy(form: FormRow) {
      const warning = form.submissions_count
        ? `This will also delete ${form.submissions_count} response(s).`
        : "This cannot be undone."

      if (!await this.$confirm("Delete form?", warning)) return

      const response = await this.$http.delete(`/forms/${form.id}`).catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
    state(form: FormRow): {text: string, color: string} {
      if (!form.is_published) return {text: "Draft", color: "default"}
      if (form.closes_at && new Date(form.closes_at) < new Date()) return {text: "Closed", color: "warning"}
      return {text: form.is_public ? "Live · public" : "Live", color: "success"}
    },
  },
})
</script>

<template>
  <v-container>
    <h1 class="text-headline-large mb-4">
      Forms
    </h1>

    <v-card>
      <v-progress-linear
        v-show="loading"
        indeterminate
      />

      <v-list
        v-if="forms.length"
        lines="two"
      >
        <v-list-item
          v-for="form in forms"
          :key="form.id"
          :title="form.name"
        >
          <template #subtitle>
            <span>/{{ form.slug }}</span>
            <span> · created <AppTimeAgo :value="form.created_at" /></span>
          </template>

          <template #append>
            <div class="d-flex align-center ga-2">
              <v-chip
                density="comfortable"
                prepend-icon="inbox"
                size="small"
                variant="tonal"
              >
                {{ form.submissions_count }}
              </v-chip>
              <v-chip
                :color="state(form).color"
                density="comfortable"
                size="small"
              >
                {{ state(form).text }}
              </v-chip>
              <v-btn
                :icon="form.is_published ? 'visibility_off' : 'send'"
                :aria-label="form.is_published ? 'Unpublish form' : 'Publish form'"
                size="small"
                variant="text"
                @click="togglePublished(form)"
              />
              <v-btn
                color="error"
                icon="delete_outline"
                aria-label="Delete form"
                size="small"
                variant="text"
                @click="destroy(form)"
              />
            </div>
          </template>
        </v-list-item>
      </v-list>

      <AppEmptyState
        v-else-if="!loading"
        icon="dynamic_form"
        description="Define fields once and the renderer builds the form from them."
        title="No forms yet"
      />
    </v-card>
  </v-container>
</template>
