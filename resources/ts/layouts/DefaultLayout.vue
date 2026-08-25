<template>
  <v-app>
    <app-impersonation-banner
      ref="impersonationBanner"
      :visible="$auth.impersonating"
      :impersonating-as="impersonatedName"
      :original-user="$auth.impersonator ?? ''"
      @stop="stopImpersonating"
    />
    <app-messages />
    <app-network-banner />
    <component
      :is="globalSearch"
      v-if="globalSearch"
    />
    <v-main>
      <v-slide-x-reverse-transition mode="out-in">
        <span class="transition-wrapper">
          <app-error-boundary name="DefaultLayout">
            <slot />
          </app-error-boundary>
        </span>
      </v-slide-x-reverse-transition>
    </v-main>
    <update-detector />
  </v-app>
</template>

<script lang="ts">
import {defineAsyncComponent, defineComponent, markRaw} from "vue"
import UpdateDetector from "@/components/UpdateDetector.vue"
import AppErrorBoundary from "@/components/AppErrorBoundary.vue"
import AppMessages from "@/components/AppMessages.vue"
import AppNetworkBanner from "@/components/AppNetworkBanner.vue"
import AppImpersonationBanner from "@/components/AppImpersonationBanner.vue"
import {displayName} from "@/stores/user"

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
  components: {AppErrorBoundary, 
    UpdateDetector,
    AppMessages,
    AppNetworkBanner,
    AppImpersonationBanner,
  },
  data() {
    return {
      // markRaw: a component object stored in data() gets made reactive, which
      // Vue warns about at runtime — it deep-proxies the whole component
      // definition for no benefit, since it never changes after load.
      globalSearch: searchGlob[searchPath] ? markRaw(defineAsyncComponent(searchGlob[searchPath] as never)) : null,
    }
  },
  computed: {
    impersonatedName(): string {
      return displayName(this.$auth.user) ?? ""
    },
  },
  methods: {
    async stopImpersonating() {
      const response = await this.$auth.stopImpersonating()

      if (this.$error(response.status, response.data?.message)) {
        // Stop the banner's spinner; the session is unchanged and they are
        // still impersonating.
        (this.$refs.impersonationBanner as InstanceType<typeof AppImpersonationBanner>)?.setLoading(false)

        return
      }

      // Full reload rather than a route change. Everything currently mounted
      // was fetched as the impersonated user, and the identity behind the token
      // has just changed underneath it.
      window.location.reload()
    },
  },
})
</script>

<style scoped></style>
