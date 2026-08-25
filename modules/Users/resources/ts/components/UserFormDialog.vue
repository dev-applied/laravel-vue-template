<template>
  <app-dialog
    :model-value="modelValue"
    max-width="560"
    :title="user ? 'Edit user' : 'New user'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #body>
      <!-- validate-on="blur", not the kernel default of "eager": a dialog form
           would otherwise open with every field already painted red, and do it
           again on every reopen. -->
      <app-server-validation-form
        :data="form"
        :endpoint="endpoint"
        :method="user ? 'put' : 'post'"
        :success-message="user ? 'User updated' : 'User created'"
        validate-on="blur"
        @success="$emit('saved', $event)"
      >
        <template #default="{ submit, loading, getErrors }">
          <v-card-text>
            <v-row dense>
              <v-col
                cols="12"
                sm="6"
              >
                <v-text-field
                  v-model="form.first_name"
                  :error-messages="getErrors('first_name')"
                  label="First name"
                  :rules="[rules.required()]"
                />
              </v-col>
              <v-col
                cols="12"
                sm="6"
              >
                <v-text-field
                  v-model="form.last_name"
                  :error-messages="getErrors('last_name')"
                  label="Last name"
                  :rules="[rules.required()]"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.email"
                  :error-messages="getErrors('email')"
                  label="Email"
                  :rules="[rules.required(), rules.email()]"
                  type="email"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.password"
                  autocomplete="new-password"
                  :error-messages="getErrors('password')"
                  :hint="passwordHint"
                  label="Password"
                  persistent-hint
                  type="password"
                />
              </v-col>
              <!--
                v-show, not v-if: this field is inside a v-form, and tearing the
                input out of the DOM drops it from the form's validation registry
                mid-edit.
              -->
              <v-col
                v-show="form.password"
                cols="12"
              >
                <v-text-field
                  v-model="form.password_confirmation"
                  autocomplete="new-password"
                  :error-messages="getErrors('password_confirmation')"
                  label="Confirm password"
                  type="password"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn
              variant="text"
              @click="$emit('update:modelValue', false)"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              :loading="loading"
              variant="flat"
              @click="submit"
            >
              Save
            </v-btn>
          </v-card-actions>
        </template>
      </app-server-validation-form>
    </template>
  </app-dialog>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import validators from "@/mixins/validators"
import AppDialog from "@/components/AppDialog.vue"
import AppServerValidationForm from "@/components/AppServerValidationForm.vue"
import type {ManagedUser} from "@modules/Users/resources/ts/composables/useUsers"

export default defineComponent({
  name: "UserFormDialog",
  components: {AppDialog, AppServerValidationForm},
  mixins: [validators],
  props: {
    modelValue: {type: Boolean, default: false},
    user: {type: Object as PropType<ManagedUser | null>, default: null},
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      form: this.blankForm(),
    }
  },
  computed: {
    endpoint(): string {
      return this.user ? `manage/users/${this.user.id}` : 'manage/users'
    },
    passwordHint(): string {
      return this.user
        ? 'Leave empty to keep their current password.'
        : 'Leave empty to email them a link to set their own.'
    },
  },
  watch: {
    // Reset on open rather than on close: resetting on close visibly wipes the
    // fields while the dialog is still animating out.
    modelValue(open: boolean) {
      if (!open) return

      this.form = this.user
        ? {
          first_name: this.user.firstName,
          last_name: this.user.lastName,
          email: this.user.email,
          password: '',
          password_confirmation: '',
        }
        : this.blankForm()
    },
  },
  methods: {
    blankForm() {
      return {
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
      }
    },
  },
})
</script>
