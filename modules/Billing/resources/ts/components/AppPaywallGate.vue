<script lang="ts">
import {defineComponent, type PropType} from "vue"
import useEntitlement, {type Tier} from "@modules/Billing/resources/ts/composables/useEntitlement"

/**
 * Shows its default slot to subscribers and its `locked` slot to everyone
 * else.
 *
 * This decides what to SHOW. It is not the enforcement — that is the `tier`
 * middleware on the server. A gate that exists only in the client is a
 * suggestion.
 */
export default defineComponent({
  name: "AppPaywallGate",
  props: {
    tier: {type: String as PropType<Tier>, default: 'basic'},
  },
  setup() {
    return useEntitlement()
  },
  computed: {
    allowed(): boolean {
      return this.hasTier(this.tier)
    },
  },
  mounted() {
    this.refresh()
  },
})
</script>

<template>
  <div class="app-paywall-gate">
    <!-- Nothing is shown until the entitlement has actually loaded: rendering
         the locked state first makes a paying customer see the paywall flash
         on every page load. -->
    <v-skeleton-loader
      v-if="!loaded"
      type="paragraph"
    />

    <template v-else-if="allowed">
      <slot />
    </template>

    <template v-else>
      <slot
        name="locked"
        :entitlement="entitlement"
        :required="tier"
      >
        <v-alert
          type="info"
          variant="tonal"
        >
          {{ entitlement.isFirstTime
            ? 'This is part of a paid plan.'
            : 'Your subscription has ended — resubscribe to get this back.' }}
        </v-alert>
      </slot>
    </template>
  </div>
</template>
