<template>
  <v-app>
    <app-error-messages />
    <v-main>
      <v-slide-x-reverse-transition mode="out-in">
        <span class="transition-wrapper">
          <slot />
        </span>
      </v-slide-x-reverse-transition>
    </v-main>
  </v-app>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import AppErrorMessages from "@/components/AppErrorMessages.vue"

export default defineComponent({
  components: {
    AppErrorMessages
  }
})
</script>

<style scoped></style>
