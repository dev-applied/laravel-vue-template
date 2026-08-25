<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppDialog from "@/components/AppDialog.vue"
import AppServerValidationForm from "@/components/AppServerValidationForm.vue"
import AppDateInput from "@/components/fields/AppDateInput.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"
import type {Announcement} from "@modules/Announcements/resources/ts/composables/useAnnouncements"

export default defineComponent({
  name: "AnnouncementFormDialog",
  components: {AppDialog, AppServerValidationForm, AppDateInput, AppSelect, AppTextField, AppTextarea},
  props: {
    modelValue:   {type: Boolean, default: false},
    announcement: {type: Object as PropType<Announcement | null>, default: null},
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      form: this.blank(),
      levels: [
        {title: "Info", value: "info"},
        {title: "Success", value: "success"},
        {title: "Warning", value: "warning"},
        {title: "Error", value: "error"},
      ],
      placements: [
        {title: "Banner — sits above the page", value: "banner"},
        {title: "Modal — interrupts once", value: "modal"},
      ],
    }
  },
  computed: {
    // AppServerValidationForm makes the request itself, so it needs the target
    // and the body as reactive props rather than a handler of ours. It used to
    // be given :loading and @submit — neither of which it declares — so it made
    // no request, and the 422 field errors the comment here promised were never
    // wired up at all.
    endpoint(): string {
      return this.announcement ? `/announcements/${this.announcement.id}` : "/announcements"
    },
    method(): string {
      return this.announcement ? "put" : "post"
    },
    payload() {
      return {
        ...this.form,
        // Send null rather than "" — an empty string fails the url/label
        // required_with pair and reads as a validation bug to the author.
        action_label: this.form.action_label || null,
        action_url:   this.form.action_url || null,
      }
    },
  },
  watch: {
    modelValue(open: boolean) {
      // Reset on open, not on close — resetting on close visibly wipes the
      // form while the dialog is still animating out.
      if (open) this.form = this.announcement ? this.fromAnnouncement(this.announcement) : this.blank()
    },
  },
  methods: {
    blank() {
      return {
        title: "", body: "",
        level: "info", placement: "banner", audience: "everyone",
        dismissible: true, requires_acknowledgement: false,
        action_label: "", action_url: "",
        starts_at: null as string | null, ends_at: null as string | null,
      }
    },
    fromAnnouncement(a: Announcement) {
      return {
        title: a.title, body: a.body,
        level: a.level, placement: a.placement, audience: a.audience,
        dismissible: a.dismissible, requires_acknowledgement: a.requiresAcknowledgement,
        action_label: a.actionLabel ?? "", action_url: a.actionUrl ?? "",
        starts_at: a.startsAt ?? null, ends_at: a.endsAt ?? null,
      }
    },
  },
})
</script>

<template>
  <AppDialog
    max-width="640"
    :model-value="modelValue"
    :title="announcement ? 'Edit announcement' : 'New announcement'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <!--
      #body, not the default slot. AppDialog's default slot has the WHOLE
      v-card — title bar, close button and this #body — as its fallback, so
      passing default content replaces the card rather than filling it: the
      dialog rendered as a bare form with no chrome and no title.
    -->
    <template #body>
      <AppServerValidationForm
        v-slot="{submit, loading}"
        :data="payload"
        :endpoint="endpoint"
        :method="method"
        success-message="Announcement saved"
        validate-on="blur"
        @success="$emit('saved')"
      >
        <v-row dense>
          <v-col cols="12">
            <AppTextField
              v-model="form.title"
              label="Title"
              name="title"
              required
            />
          </v-col>

          <v-col cols="12">
            <AppTextarea
              v-model="form.body"
              label="Message"
              name="body"
              required
              rows="4"
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.level"
              :items="levels"
              label="Level"
              name="level"
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.placement"
              :items="placements"
              label="Placement"
              name="placement"
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppDateInput
              v-model="form.starts_at"
              hint="Leave empty to start as soon as it is published"
              label="Starts"
              name="starts_at"
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppDateInput
              v-model="form.ends_at"
              hint="Leave empty to run until unpublished"
              label="Ends"
              name="ends_at"
            />
          </v-col>

          <v-col cols="12">
            <AppTextField
              v-model="form.audience"
              hint="Resolved by the project's AudienceResolver. `everyone` by default."
              label="Audience"
              name="audience"
              persistent-hint
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.action_label"
              label="Button label"
              name="action_label"
            />
          </v-col>

          <v-col
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.action_url"
              label="Button link"
              name="action_url"
            />
          </v-col>

          <v-col cols="12">
            <v-switch
              v-model="form.dismissible"
              color="primary"
              density="compact"
              hide-details
              label="People can dismiss it"
            />
            <v-switch
              v-model="form.requires_acknowledgement"
              color="primary"
              density="compact"
              hide-details
              label="Require an explicit acknowledgement"
              :messages="form.requires_acknowledgement ? 'Records who acknowledged and when.' : ''"
            />
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-4">
          <v-btn
            variant="text"
            @click="$emit('update:modelValue', false)"
          >
            Cancel
          </v-btn>
          <!-- @click, not type="submit": the wrapper's v-form is @submit.prevent,
             so the native submit does nothing. `submit` comes from the slot. -->
          <v-btn
            color="primary"
            :loading="loading"
            variant="flat"
            @click="submit"
          >
            Save
          </v-btn>
        </div>
      </AppServerValidationForm>
    </template>
  </AppDialog>
</template>
