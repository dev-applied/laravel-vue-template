<template>
  <!--
    Renders nothing at all when no provider is configured, so a project with the
    layer installed but unconfigured shows a plain login form rather than an
    empty divider and a gap.
  -->
  <div v-if="providers.length">
    <div class="d-flex align-center ga-3 my-4">
      <v-divider />
      <span class="text-body-small text-medium-emphasis">or</span>
      <v-divider />
    </div>

    <v-btn
      v-for="provider in providers"
      :key="provider.provider"
      block
      class="mb-2"
      :loading="pending === provider.provider"
      variant="outlined"
      @click="start(provider)"
    >
      {{ provider.kind === 'saml' ? 'Sign in with' : 'Continue with' }} {{ provider.label }}
    </v-btn>
  </div>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import useHttp from "@/composables/useHttp"

interface SsoProvider {
  provider: string
  label: string
  // Which protocol starts this one. Both finish at the same handoff code, but
  // OIDC begins at /auth/sso/{provider}/start and SAML at /auth/saml/start.
  kind?: "oidc" | "saml"
}

export default defineComponent({
  name: "SsoButtons",
  setup() {
    return useHttp()
  },
  data() {
    return {
      providers: [] as SsoProvider[],
      pending: null as string | null,
    }
  },
  async mounted() {
    // Silent on failure: this endpoint is decoration. If it 404s because the
    // layer is not installed, the login form must still work.
    const {status, data} = await this.$http.get('auth/sso/providers').catch((e: any) => e)

    // `!status` as well as the range: the axios plugin rejects with a
    // {status, data} shape, but a non-axios throw arrives with neither, and
    // `undefined > 204` is false — which would fall straight through to
    // data.data on an undefined data.
    if (!status || status > 204) return

    this.providers = data.data ?? []
  },
  methods: {
    async start(provider: SsoProvider) {
      this.pending = provider.provider

      const endpoint = provider.kind === "saml"
        ? "auth/saml/start"
        : `auth/sso/${provider.provider}/start`

      const {status, data} = await this.$http.get(endpoint).catch((e: any) => e)

      this.pending = null

      if (this.$error(status, data?.message, data?.errors)) return

      // Assert the scheme before handing a string to a navigation sink. The URL
      // is built server-side from config, so this is defence in depth rather
      // than a known hole — but `window.location.href` will happily execute a
      // `javascript:` URL, and this is the one line where that would matter.
      if (!/^https:\/\//i.test(String(data.url ?? ''))) {
        this.$error(422, 'That sign-in provider returned an address we will not open.')
        return
      }

      // A full navigation, not an XHR: the provider has to render its own
      // consent and login UI, and many refuse to be framed. The browser comes
      // back to the API callback, which redirects it to SsoCompletePage with a
      // single-use handoff code — it never lands on a token.
      window.location.href = data.url
    },
  },
})
</script>
