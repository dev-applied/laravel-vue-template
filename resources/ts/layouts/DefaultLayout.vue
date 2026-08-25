<template>
  <v-app>
    <app-messages />
    <app-network-banner />
    <component
      :is="globalSearch"
      v-if="globalSearch"
    />
    <v-main>
      <v-slide-x-reverse-transition mode="out-in">
        <span class="transition-wrapper">
          <slot />
        </span>
      </v-slide-x-reverse-transition>
    </v-main>
    <update-detector />
  </v-app>
</template>

<script lang="ts">
import {defineAsyncComponent, defineComponent, markRaw} from "vue"
import UpdateDetector from "@/components/UpdateDetector.vue"
import AppMessages from "@/components/AppMessages.vue"
import AppNetworkBanner from "@/components/AppNetworkBanner.vue"

// The palette is mounted HERE, once, rather than per page: its open state is
// module-scoped so every button and the Cmd/Ctrl+K binding reach one instance,
// and a per-page mount would give each route its own hidden dialog.
//
// import.meta.glob, not a static import — modules/GlobalSearch may not be
// installed, and a static path to a missing file fails the whole Vite build
// rather than just this layout. Same idiom ItemListPage uses for the export
// button and LoginPage for SsoButtons.
const searchGlob = import.meta.glob("/modules/GlobalSearch/resources/ts/components/AppGlobalSearch.vue")
const searchPath = "/modules/GlobalSearch/resources/ts/components/AppGlobalSearch.vue"


export default defineComponent({
  components: {
    UpdateDetector,
    AppMessages,
    AppNetworkBanner,
  },
  data() {
    return {
      // markRaw: a component object stored in data() gets made reactive, which
      // Vue warns about at runtime — it deep-proxies the whole component
      // definition for no benefit, since it never changes after load.
      globalSearch: searchGlob[searchPath] ? markRaw(defineAsyncComponent(searchGlob[searchPath] as never)) : null,
    }
  },
})
</script>

<style scoped></style>
