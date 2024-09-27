<template>
  <v-app>
    <v-main>
      <v-container :style="{'box-sizing': 'border-box'}">
        <slot name="story" />
      </v-container>
    </v-main>
  </v-app>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { useTheme } from "vuetify"

export default defineComponent({
  setup() {
    const theme = useTheme()
    return { theme }
  },
  props: {
    themeName: String,
  },
  watch: {
    themeName: {
      immediate: true,
      handler(themeName) {
        this.theme.global.name.value = themeName
      },
    },
  },
})
</script>

<style scoped lang="scss">
:deep(.v-application__wrap) {
    min-height: unset;
}
</style>
