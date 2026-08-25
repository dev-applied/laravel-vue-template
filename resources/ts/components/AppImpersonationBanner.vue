<script lang="ts">
import {defineComponent} from "vue"

/**
 * Sticky warning shown for the whole of an impersonation session.
 *
 * The hazard it exists for: an impersonated session looks exactly like a real
 * one. Somebody forgets they are acting as another user, and then acts as that
 * user believing they are themselves. So this stays on screen the entire time
 * rather than being a toast that can be missed or dismissed.
 *
 * Owns its own loading state; the parent owns the API call.
 */
export default defineComponent({
  name: "AppImpersonationBanner",
  props: {
    visible: {type: Boolean, default: false},
    impersonatingAs: {type: String, default: ""},
    originalUser: {type: String, default: ""},
  },
  emits: ["stop"],
  data() {
    return {
      loading: false,
    }
  },
  computed: {
    /**
     * Two lines on phones, one everywhere else.
     *
     * `lines` sets a -webkit-line-clamp. At 390px the text needs two lines, so
     * clamping to one renders "Impersonating as…" — the name of the person you
     * are acting as, the single thing this banner exists to tell you, is the
     * part that gets cut.
     */
    lines(): "one" | "two" {
      return this.$vuetify.display.xs ? "two" : "one"
    },
  },
  methods: {
    onStop() {
      this.loading = true
      this.$emit("stop")
    },
    /** Let the parent stop the spinner if the call failed. */
    setLoading(value: boolean) {
      this.loading = value
    },
  },
})
</script>

<template>
  <v-banner
    v-show="visible"
    color="warning"
    icon="visibility"
    sticky
    :lines="lines"
    density="compact"
    class="app-impersonation-banner"
  >
    <v-banner-text>
      <strong>Impersonating</strong>
      <template v-if="impersonatingAs">
        as <strong>{{ impersonatingAs }}</strong>
      </template>
      <!--
        Hidden below sm. The banner is one line and the phone width cannot hold
        both names — left in, it eats the space and truncates to
        "Impersonating as…", losing the one fact this banner exists to show.
        Who you are ACTING AS is what matters; who you signed in as is
        recoverable from the account menu.
      -->
      <span
        v-if="originalUser"
        class="d-none d-sm-inline"
      >
        (you are signed in as <strong>{{ originalUser }}</strong>)
      </span>
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

<style lang="scss" scoped>
.app-impersonation-banner {
  // Pin above the app bar so it is always visible during an impersonation
  // session — this is the only thing distinguishing it from a real one.
  z-index: 2000;
}
</style>
