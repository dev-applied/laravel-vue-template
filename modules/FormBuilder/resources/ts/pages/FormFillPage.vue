<script lang="ts">
import {defineComponent} from "vue"
import AppRenderedForm from "@modules/FormBuilder/resources/ts/components/AppRenderedForm.vue"

export default defineComponent({
  name: "FormFillPage",
  components: {AppRenderedForm},
  props: {
    slug: {type: String, required: true},
  },
})
</script>

<template>
  <v-container class="d-flex justify-center">
    <v-card
      class="pa-6"
      max-width="640"
      width="100%"
    >
      <AppRenderedForm :slug="slug" />
    </v-card>
  </v-container>
</template>
