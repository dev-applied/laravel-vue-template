<script lang="ts">
import {defineComponent} from "vue"
import AppFormField from "@modules/FormBuilder/resources/ts/components/AppFormField.vue"
import useForm from "@modules/FormBuilder/resources/ts/composables/useForm"

/**
 * A whole builder-defined form. Drop it anywhere:
 *
 *   <AppRenderedForm slug="volunteer-intake" />
 */
export default defineComponent({
  name: "AppRenderedForm",
  components: {AppFormField},
  props: {
    slug:      {type: String, required: true},
    showTitle: {type: Boolean, default: true},
  },
  emits: ['submitted'],
  setup(props) {
    return useForm(props.slug)
  },
  mounted() {
    this.load()
  },
  methods: {
    async send() {
      if (await this.submit()) this.$emit('submitted')
    },
  },
})
</script>

<template>
  <div class="app-rendered-form">
    <v-skeleton-loader
      v-if="loading"
      type="article, actions"
    />

    <v-alert
      v-else-if="submitted"
      type="success"
      variant="tonal"
    >
      {{ message }}
    </v-alert>

    <template v-else-if="form">
      <div
        v-if="showTitle"
        class="mb-4"
      >
        <h1 class="text-headline-small">
          {{ form.name }}
        </h1>
        <p
          v-if="form.description"
          class="text-body-medium text-medium-emphasis mt-1"
        >
          {{ form.description }}
        </p>
      </div>

      <!--
        A plain v-form. AppServerValidationForm OWNS the request — endpoint,
        method and data are required props — and useFormSubmission's submit()
        already posts the answers and surfaces the field errors. Passing it
        :loading/@submit (neither of which it declares) meant it made no request
        at all, and put this form's only Submit button in a #actions slot the
        component does not have, so nothing rendered it.
      -->
      <v-form @submit.prevent="send">
        <AppFormField
          v-for="field in fields"
          :key="field.key"
          v-model="answers[field.key]"
          class="mb-3"
          :field="field"
        />

        <div class="d-flex justify-end mt-4">
          <v-btn
            color="primary"
            :loading="submitting"
            type="submit"
            variant="flat"
          >
            Submit
          </v-btn>
        </div>
      </v-form>
    </template>
  </div>
</template>
