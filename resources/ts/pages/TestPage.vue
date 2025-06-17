<template>
  <app-dialog
    clo
    fullscreen
    model-value
  >
    <v-card>
      <v-card-title class="d-flex">
        <div class="text-wrap">
          Test wenflkwenflkewnflk nwelk fnlkewn flkwenflk wenlkf nlkwenf lkwenflk nwelkfn lkwenfl
          knewflk
        </div>
        <v-spacer />
        <v-icon>mdi-close</v-icon>
      </v-card-title>
    </v-card>
  </app-dialog>
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
