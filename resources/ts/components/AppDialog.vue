<template>
  <component
    :is="($vuetify.display.smAndDown) ? 'v-bottomSheet' : 'v-dialog'"
    class="app-dialog"
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

<style lang="scss">
.v-overlay-container .v-dialog.v-bottom-sheet.app-dialog > .v-overlay__content {
  max-width: 100%!important;
}
</style>
