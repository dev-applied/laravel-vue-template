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

    <h3 class="mb-5">
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

export default defineComponent({
  components: {AppDialog}
})
</script>

<style lang="scss" scoped>
</style>
