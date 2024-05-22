<template>
  <v-row
    v-if="messages && messages.length > 0"
    class="app-error-messages"
  >
    <v-col>
      <v-alert
        v-for="(message, index) in messages"
        :key="index + '-message'"
        :type="message.type.toLowerCase()"
        dismissible
      >
        {{ message.message }}
      </v-alert>
    </v-col>
  </v-row>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { mapState } from "pinia"
import { useAppStore } from "@/stores/app"

export default defineComponent({
  computed: {
    ...mapState(useAppStore, ["messages"])
  }
})
</script>

<style scoped lang="scss">

</style>
