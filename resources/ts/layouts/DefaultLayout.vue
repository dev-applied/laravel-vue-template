<template>
  <v-app>
    <app-messages />
    <app-network-banner />
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
import AppMessages from "@/components/AppMessages.vue"
import AppNetworkBanner from "@/components/AppNetworkBanner.vue"


export default defineComponent({
  components: {
    UpdateDetector,
    AppMessages,
    AppNetworkBanner,
  }
})
</script>

<style scoped></style>
