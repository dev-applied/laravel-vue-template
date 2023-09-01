<template>
  <v-row
    v-if="text && link"
    class="admin-layout__container__back-container mt-2"
    no-gutters
  >
    <router-link
      :to="typeof link === 'string' ? { name: link } : link"
      class="admin-layout__container__back-container__back-link"
    >
      <v-icon
        class="mr-1 mb-1"
        color="secondary"
        size="24"
      >
        keyboard_backspace
      </v-icon>
      <span>Back to {{ text }}</span>
    </router-link>
  </v-row>
</template>

<script lang="ts">
import { backButton } from "@/plugins/backButton/index"
import { defineComponent } from "vue"
import type { RawLocation } from "vue-router"

export default defineComponent({
  data() {
    return {
      link: null as string | RawLocation | null,
      text: null
    }
  },
  created() {
    backButton.registerInstance(this)
  },
  beforeDestroy() {
    backButton.unregisterInstance(this)
  },
  methods: {
    navigate() {
      this.$router.push(this.link)
    }
  }
})
</script>

<style lang="scss" scoped>
.back-container {
  background: #f4f4f4;
  width: fit-content;
  cursor: pointer;
}
</style>
