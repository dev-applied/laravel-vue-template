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

defineProps<{
  visible:           boolean
  impersonatingAs?:  string
  originalUser?:     string
}>()

const emit = defineEmits<{
  /**
   * Fires when the user clicks "Stop impersonating". The banner sets its own
   * loading state to true; the parent owns the API call and is responsible
   * for either v-if=false-ing the banner on success or calling setLoading(false)
   * on failure so the button stops spinning.
   */
  stop: []
}>()

const loading = ref(false)

function onStop() {
  loading.value = true
  emit("stop")
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
