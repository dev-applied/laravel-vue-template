<template>
  <v-app>
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
import {defineComponent} from "vue";

export default defineComponent({
  name: 'EmptyLayout'
})
</script>

<style scoped>

</style>
