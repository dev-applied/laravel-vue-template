<template>
  <component
    :is="($vuetify.display.smAndDown) ? 'v-bottomSheet' : 'v-dialog'"
    v-bind="$attrs"
  >
    <slot />
  </component>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import {VBottomSheet} from "vuetify/components/VBottomSheet"
import {VDialog} from "vuetify/components/VDialog"

export default defineComponent({
  components: {VDialog, VBottomSheet},
  inheritAttrs: false
})
</script>

<style scoped>

</style>
