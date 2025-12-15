<template>
  <v-app>
    <app-messages />
    <v-main>
      <v-slide-x-reverse-transition mode="out-in">
        <span class="transition-wrapper">
          <slot />
        </span>
      </v-slide-x-reverse-transition>
    </v-main>
    <update-detector />
  </v-app>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import UpdateDetector from "@/components/UpdateDetector.vue"


export default defineComponent({
  components: {
    UpdateDetector
  }
})
</script>

<style scoped></style>
