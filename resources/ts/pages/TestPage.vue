<template>
  <v-container>
    <h1>Style Guide</h1>
    <h3 class="mb-5">
      Buttons
    </h3>
    <div class="d-flex flex-column ga-4 align-start">
      <v-btn-primary text="VBtnPrimary" />
      <v-btn-secondary text="VBtnSecondary" />
      <v-btn-tertiary text="VBtnTertiary" />
    </div>
    <h3 class="mt-14 mb-5">
      Inputs
    </h3>

    <app-number-input
      v-model="numberModel1"
      label="App Number Input with currency symbol"
      :display-currency-symbol="true"
    />
    <app-number-input
      v-model="numberModel2"
      label="App Number Input without currency symbol"
    />

    <app-mask-field
      v-model="maskModel"
      mask="(###) ###-####"
      label="Phone Number"
      required
    />

    <app-select
      v-model="selectModel"
      label="Select"
      :items="[
        { title: 'Option 1', value: 'option1' },
        { title: 'Option 2', value: 'option2' },
        { title: 'Option 3', value: 'option3' },
      ]"
      :hide-details="false"
    />

    <app-auto-complete
      v-model="autoCompleteModel"
      endpoint="users"
      label="App Auto Complete"
      :item-title="(item: any) => item.full_name"
      :hide-details="false"
    />

    <app-combobox
      v-model="comboboxModel"
      :items="['Item 1', 'Item 2', 'Item 3']"
      label="App Combobox"
      placeholder="App Combobox"
      hide-selected
      :hide-details="false"
    />

    <app-date-input
      v-model="dateModel"
      label="App Date Input"
      persistent-placeholder
    />

    <app-textarea
      v-model="textareaModel"
      label="App Text Area"
      placeholder="App Text Area"
    />

    <h3 class="mt-14 mb-5">
      Components
    </h3>
    <div class="d-flex flex-column ga-4 align-start">
      <app-dialog title="App Dialog Example">
        <template #activator="{props}">
          <v-btn-primary
            text="App Dialog"
            v-bind="props"
          />
        </template>
        <template #body>
          <v-card-text>
            Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been
            the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and
            scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into
            electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release
            of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software
            like Aldus PageMaker including versions of Lorem Ipsum.
          </v-card-text>
        </template>
      </app-dialog>
    </div>
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import AppDialog from "@/components/AppDialog.vue"
import AppNumberInput from "@/components/fields/AppNumberInput.vue"
import AppMaskField from "@/components/fields/AppMaskField.vue"
import AppSelect from "@/components/fields/AppSelect.vue"
import AppAutoComplete from "@/components/fields/AppAutoComplete/AppAutoComplete.vue"
import AppCombobox from "@/components/fields/AppCombobox.vue"
import AppDateInput from "@/components/fields/AppDateInput.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"

export default defineComponent({
  components: {
    AppTextarea,
    AppDateInput,
    AppCombobox,
    AppAutoComplete,
    AppSelect,
    AppMaskField,
    AppNumberInput,
    AppDialog
  },
  data() {
    return {
      comboboxModel: [] as string[],
      numberModel1: null,
      numberModel2: null,
      maskModel: '',
      selectModel: null,
      autoCompleteModel: null,
      dateModel: null,
      textareaModel: ''
    }
  }
})
</script>

<style lang="scss" scoped>
</style>
