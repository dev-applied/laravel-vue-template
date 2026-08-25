<template>
  <v-container fluid>
    <div class="d-flex align-center mb-4">
      <v-btn
        icon="arrow_back"
        variant="text"
        @click="goBack"
      />
      <h1 class="text-h4 ml-2">
        {{ isEdit ? 'Edit Item' : 'New Item' }}
      </h1>
    </div>

    <v-card
      v-if="!loading"
      max-width="720"
      class="mx-auto"
    >
      <app-server-validation-form
        :endpoint="endpoint"
        :method="method"
        :data="form"
        :success-message="isEdit ? 'Item updated' : 'Item created'"
        @success="onSuccess"
      >
        <template #default="{ submit, loading: submitting, getErrors }">
          <v-card-text>
            <v-text-field
              v-model="form.name"
              label="Name"
              :error-messages="getErrors('name')"
              :rules="[rules.required()]"
            />

            <v-textarea
              v-model="form.description"
              label="Description"
              rows="3"
              auto-grow
              :error-messages="getErrors('description')"
            />

            <v-row>
              <v-col
                cols="12"
                md="6"
              >
                <v-select
                  v-model="form.status"
                  label="Status"
                  :items="statusOptions"
                  :error-messages="getErrors('status')"
                  :rules="[rules.required()]"
                />
              </v-col>
              <v-col
                cols="12"
                md="6"
              >
                <v-text-field
                  v-model.number="form.priority"
                  label="Priority (1-5)"
                  type="number"
                  min="1"
                  max="5"
                  :error-messages="getErrors('priority')"
                  :rules="[rules.required()]"
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col
                cols="12"
                md="6"
              >
                <app-date-input
                  v-model="form.due_date"
                  label="Due date"
                  :error-messages="getErrors('due_date')"
                  clearable
                />
              </v-col>
              <v-col
                cols="12"
                md="6"
              >
                <app-auto-complete
                  v-model="form.owner_id"
                  endpoint="users"
                  label="Owner"
                  :item-title="(u: any) => u.full_name"
                  :item-value="(u: any) => u.id"
                  :error-messages="getErrors('owner_id')"
                  clearable
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn
              :disabled="submitting"
              @click="goBack"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              :loading="submitting"
              @click="trySubmit(submit)"
            >
              {{ isEdit ? 'Save' : 'Create' }}
            </v-btn>
          </v-card-actions>
        </template>
      </app-server-validation-form>
    </v-card>

    <v-progress-circular
      v-else
      indeterminate
      class="d-block mx-auto mt-12"
    />
  </v-container>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import validators from "@/mixins/validators"
import AppServerValidationForm from "@/components/AppServerValidationForm.vue"
import AppDateInput from "@/components/fields/AppDateInput.vue"
import AppAutoComplete from "@/components/fields/AppAutoComplete/AppAutoComplete.vue"

interface ItemForm {
  name:        string
  description: string
  status:      string
  priority:    number
  due_date:    string | null
  owner_id:    number | null
}

const blankForm = (): ItemForm => ({
  name:        "",
  description: "",
  status:      "active",
  priority:    3,
  due_date:    null,
  owner_id:    null,
})

export default defineComponent({
  components: { AppServerValidationForm, AppDateInput, AppAutoComplete },
  mixins: [validators],
  data() {
    return {
      form:    blankForm(),
      loading: false,
      statusOptions: [
        { title: "Draft",    value: "draft" },
        { title: "Active",   value: "active" },
        { title: "Archived", value: "archived" },
      ],
    }
  },
  computed: {
    isEdit(): boolean {
      return !!this.$route.params.id
    },
    endpoint(): string {
      return this.isEdit ? `/items/${this.$route.params.id}` : "/items"
    },
    method(): string {
      return this.isEdit ? "patch" : "post"
    },
  },
  async mounted() {
    if (this.isEdit) {
      await this.loadItem()
    }
  },
  methods: {
    async loadItem() {
      this.loading = true
      const { data } = await this.$http.get(`/items/${this.$route.params.id}`).catch(e => e)
      this.loading = false

      // `show` returns an ItemResource, and Laravel wraps a returned resource in
      // a `data` envelope — so the record is at `data.data`, not `data`. Reading
      // the envelope's own properties gave undefined for every field, and the
      // `??` fallbacks then filled the form with defaults, so opening an item
      // for editing silently showed a blank form and saving it wiped the record.
      const item = data?.data ?? data
      if (!item) return

      this.form = {
        name:        item.name        ?? "",
        description: item.description ?? "",
        status:      item.status      ?? "active",
        priority:    item.priority    ?? 3,
        due_date:    item.due_date    ?? null,
        owner_id:    item.owner_id    ?? null,
      }
    },
    // AppServerValidationForm#submit throws on client-side validation failure
    // (intentional, so the form can report invalid state). We don't want that
    // to surface as an unhandled rejection from a button click — the form
    // itself already shows the per-field errors.
    async trySubmit(submit: () => Promise<void>) {
      try { await submit() } catch { /* validation surfaced in form */ }
    },
    onSuccess() {
      this.$router.push(this.$routeTo(this.ROUTES.ITEMS_LIST))
    },
    goBack() {
      this.$router.push(this.$routeTo(this.ROUTES.ITEMS_LIST))
    },
  },
})
</script>

<style lang="scss" scoped></style>
