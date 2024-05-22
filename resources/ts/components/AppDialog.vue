<template>
  <component
    :is="($vuetify.breakpoint.smAndDown) ? 'v-bottomSheet' : 'v-dialog'"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <slot />
  </component>
</template>

<script lang="ts">
import { defineComponent } from "vue"

export default defineComponent({
  inheritAttrs: false
})
</script>

<style scoped>

</style>
