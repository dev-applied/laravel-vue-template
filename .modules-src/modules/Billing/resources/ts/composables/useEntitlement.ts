import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export type Tier = 'free' | 'basic' | 'premium'
export type Status = 'none' | 'trial' | 'active' | 'cancelled' | 'lapsed'
export type Provider = 'none' | 'apple' | 'google' | 'web' | 'manual'

export interface Entitlement {
  tier:              Tier
  status:            Status
  plan:              'none' | 'monthly' | 'annual'
  provider:          Provider
  isActive:          boolean
  cancelAtPeriodEnd: boolean
  currentPeriodEnd?: string | null
  trialEndsAt?:      string | null
  trialUsed:         boolean
  /** Never subscribed — different copy from "trial expired". */
  isFirstTime:       boolean
  /** Null when no link may be shown; see the note in openManagement. */
  managementUrl?:    string | null
}

const RANK: Record<Tier, number> = {free: 0, basic: 1, premium: 2}

const FREE: Entitlement = {
  tier: 'free', status: 'none', plan: 'none', provider: 'none',
  isActive: false, cancelAtPeriodEnd: false, trialUsed: false, isFirstTime: true,
}

export default function useEntitlement() {
  const {$http, $error} = useHttp()

  const entitlement = shallowRef<Entitlement>(FREE)
  const loading     = ref(false)
  const loaded      = ref(false)

  const isActive = computed(() => entitlement.value.isActive)

  function hasTier(tier: Tier): boolean {
    return entitlement.value.isActive && RANK[entitlement.value.tier] >= RANK[tier]
  }

  async function refresh(): Promise<Entitlement> {
    loading.value = true

    const response = await $http.get('/billing/entitlement').catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors, false)) {
      // Fail CLOSED. A failed read must not look like a subscription.
      return entitlement.value
    }

    entitlement.value = response.data
    loaded.value = true

    return response.data
  }

  /**
   * The purchase-to-webhook race.
   *
   * The purchase call returns BEFORE the webhook has written to the database.
   * Refreshing once leaves a paying customer looking at the paywall they just
   * paid to dismiss — the highest-severity bug in this whole domain, because
   * it reads as "I paid and got nothing".
   */
  async function pollForUpgrade(attempts = 8, intervalMs = 1500): Promise<boolean> {
    const before = entitlement.value.tier

    for (let i = 0; i < attempts; i++) {
      const current = await refresh()

      if (current.isActive && RANK[current.tier] > RANK[before]) return true

      await new Promise((resolve) => setTimeout(resolve, intervalMs))
    }

    return false
  }

  /**
   * Re-check when the tab becomes visible again, so someone who backgrounded
   * the app mid-checkout is reconciled on return. Returns its own teardown.
   */
  function reconcileOnReturn(): () => void {
    const handler = () => {
      if (document.visibilityState === 'visible') refresh()
    }

    document.addEventListener('visibilitychange', handler)

    return () => document.removeEventListener('visibilitychange', handler)
  }

  return {entitlement, isActive, loading, loaded, hasTier, refresh, pollForUpgrade, reconcileOnReturn}
}
