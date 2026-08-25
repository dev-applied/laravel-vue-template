<script lang="ts">
import {defineComponent} from "vue"
import useEntitlement from "@modules/Billing/resources/ts/composables/useEntitlement"

/**
 * Current plan plus the correct management route.
 */
export default defineComponent({
  name: "AppSubscriptionStatus",
  setup() {
    return useEntitlement()
  },
  data() {
    return {stopReconciling: null as null | (() => void)}
  },
  computed: {
    /**
     * Routing follows the PROCESSOR, not the device: someone who bought on iOS
     * still manages through Apple even while sitting on the web.
     */
    managementUrl(): string | null {
      return this.entitlement.managementUrl ?? null
    },
    /**
     * Apple viewed anywhere but iOS gets COPY, not a link. Pointing an iOS
     * subscriber at web billing is external-purchase steering and gets the app
     * rejected.
     */
    managementNote(): string | null {
      if (this.managementUrl) return null
      if (this.entitlement.provider === 'apple') return 'Manage this subscription in your Apple account settings.'
      if (this.entitlement.provider === 'google') return 'Manage this subscription in the Google Play app.'
      if (this.entitlement.provider === 'manual') return 'This access was granted manually.'

      return null
    },
    statusLabel(): string {
      if (!this.entitlement.isActive) {
        return this.entitlement.isFirstTime ? 'No subscription' : 'Subscription ended'
      }
      if (this.entitlement.status === 'trial') return 'Free trial'
      if (this.entitlement.cancelAtPeriodEnd) return 'Cancels at period end'

      return 'Active'
    },
    statusColor(): string {
      if (!this.entitlement.isActive) return 'default'
      if (this.entitlement.status === 'trial') return 'info'
      if (this.entitlement.cancelAtPeriodEnd) return 'warning'

      return 'success'
    },
  },
  mounted() {
    this.refresh()
    this.stopReconciling = this.reconcileOnReturn()
  },
  beforeUnmount() {
    this.stopReconciling?.()
  },
  methods: {
    formatDate(iso?: string | null): string {
      if (!iso) return ''
      return new Intl.DateTimeFormat(undefined, {dateStyle: 'medium'}).format(new Date(iso))
    },
  },
})
</script>

<template>
  <v-card>
    <v-card-title class="d-flex align-center ga-2">
      <span>Subscription</span>
      <v-chip
        :color="statusColor"
        density="comfortable"
        size="small"
      >
        {{ statusLabel }}
      </v-chip>
      <v-spacer />
      <v-progress-circular
        v-show="loading"
        indeterminate
        size="18"
        width="2"
      />
    </v-card-title>

    <v-divider />

    <v-card-text>
      <div class="text-body-medium">
        <div><strong>Plan</strong> {{ entitlement.tier }}<span v-if="entitlement.plan !== 'none'"> · {{ entitlement.plan }}</span></div>
        <div v-if="entitlement.currentPeriodEnd">
          <strong>{{ entitlement.cancelAtPeriodEnd ? 'Access until' : 'Renews' }}</strong>
          {{ formatDate(entitlement.currentPeriodEnd) }}
        </div>
      </div>

      <v-alert
        v-if="managementNote"
        class="mt-3"
        density="compact"
        type="info"
        variant="tonal"
      >
        {{ managementNote }}
      </v-alert>
    </v-card-text>

    <v-card-actions v-if="managementUrl">
      <v-spacer />
      <v-btn
        :href="managementUrl"
        target="_blank"
        variant="text"
      >
        Manage subscription
      </v-btn>
    </v-card-actions>
  </v-card>
</template>
