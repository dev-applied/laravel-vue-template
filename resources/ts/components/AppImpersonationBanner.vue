<template>
  <v-banner
    v-if="visible"
    color="warning"
    icon="visibility"
    sticky
    lines="one"
    density="compact"
    class="app-impersonation-banner"
  >
    <v-banner-text>
      <strong>Impersonating</strong>
      <template v-if="impersonatingAs">
        as <strong>{{ impersonatingAs }}</strong>
      </template>
      <template v-if="originalUser">
        (you are signed in as <strong>{{ originalUser }}</strong>)
      </template>
    </v-banner-text>
    <template #actions>
      <v-btn
        variant="tonal"
        :loading="loading"
        @click="onStop"
      >
        Stop impersonating
      </v-btn>
    </template>
  </v-banner>
</template>

<script lang="ts" setup>
import { ref } from "vue"

const props = defineProps<{
  visible:           boolean
  impersonatingAs?:  string
  originalUser?:     string
}>()

const emit = defineEmits<{
  /**
   * Fires when the user clicks "Stop impersonating". Parent should call
   * `$auth.stopImpersonating()` and then await its resolution; bind
   * :loading="loading" on this component (via template ref) or simply
   * v-if=false the banner once the API call resolves.
   */
  stop: []
}>()

void props // suppress unused
const loading = ref(false)

async function onStop() {
  loading.value = true
  emit("stop")
  // Parent owns the API call; reset loading after a beat so a re-render
  // from the banner-disappearing doesn't leave it spinning.
  setTimeout(() => { loading.value = false }, 3000)
}

defineExpose({ setLoading: (v: boolean) => { loading.value = v } })
</script>

<style lang="scss" scoped>
.app-impersonation-banner {
  // Pin to the very top of the viewport so it's always visible during
  // an impersonation session — defends against staff doing actions on
  // behalf of a user without noticing.
  z-index: 2000;
}
</style>
