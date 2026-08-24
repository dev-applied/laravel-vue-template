<script lang="ts">
import {defineComponent} from "vue"
import AppServerValidationForm from "@/components/AppServerValidationForm.vue"
import AppTextField from "@/components/fields/AppTextField.vue"

interface ExampleNote {
  id: number
  note: string
  created_at: string
}

/**
 * Reference module page — proves a module-local page renders through the
 * router glob, calls a module-registered API route, and uses the template's
 * kernel components (AppServerValidationForm, fields/) like any app page.
 */
export default defineComponent({
  name: "ExampleNotesPage",
  components: {AppServerValidationForm, AppTextField},
  data() {
    return {
      notes: [] as ExampleNote[],
      form: {
        note: "",
      },
      loading: false,
    }
  },
  async created() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/example-notes").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return
      this.notes = response.data.data
    },
    async onSaved() {
      this.form.note = ""
      await this.load()
    },
  },
})
</script>

<template>
  <v-container>
    <v-row>
      <v-col
        cols="12"
        md="8"
      >
        <h1 class="text-h4 mb-4">
          Example Notes
        </h1>

        <app-server-validation-form
          endpoint="/example-notes"
          :data="form"
          success-message="Note added"
          @success="onSaved"
        >
          <template #default="{ submit, loading: submitting, getErrors }">
            <div class="d-flex ga-2 align-start">
              <AppTextField
                v-model="form.note"
                label="Add a note"
                :error-messages="getErrors('note')"
                class="flex-grow-1"
              />
              <v-btn
                color="primary"
                :loading="submitting"
                @click="submit"
              >
                Add
              </v-btn>
            </div>
          </template>
        </app-server-validation-form>

        <v-card
          class="mt-4"
          :loading="loading"
        >
          <v-list v-if="notes.length">
            <v-list-item
              v-for="note in notes"
              :key="note.id"
              :title="note.note"
              :subtitle="note.created_at"
            />
          </v-list>
          <v-card-text v-else-if="!loading">
            No notes yet — add the first one above.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
