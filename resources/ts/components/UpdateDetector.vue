<template>
  <v-snackbar
    v-model="showUpdateSnackbar"
    color="warning"
    timeout="-1"
  >
    A new version of the app is available.
    <template #actions>
      <v-btn
        variant="text"
        @click="reloadApp"
      >
        Reload
      </v-btn>
    </template>
  </v-snackbar>
</template>

<script setup lang="ts">
import {onBeforeUnmount, onMounted, ref} from "vue"
import {startVersionPolling} from "@/plugins/versioning/versionWatcher"

const showUpdateSnackbar = ref(false)
const latestVersion = ref<string | null>(null)

let stopPolling: (() => void) | null = null

onMounted(() => {
  const env = import.meta.env.VITE_APP_ENV
  if (env === 'local') return
  
  stopPolling = startVersionPolling({
    onVersionChange: ({ latestVersion: lv }) => {
      latestVersion.value = lv
      showUpdateSnackbar.value = true
    },
  })
})

onBeforeUnmount(() => {
  stopPolling?.()
})

function reloadApp() {
  window.location.reload()
}
</script>

<style scoped lang="scss">

</style>
