import {reactive, readonly} from "vue"
import useHttp from "@/composables/useHttp"

export interface OnboardingStep {
  key: string
  label: string
  description: string | null
  icon: string | null
  route: {name: string, params?: Record<string, string | number>} | null
  required: boolean
  autoDetected: boolean
  completed: boolean
  skipped: boolean
  completedAt: string | null
}

export interface OnboardingState {
  steps: OnboardingStep[]
  nextStep: string | null
  outstandingRequired: number
  complete: boolean
  total: number
  completedCount: number
}

/**
 * One shared onboarding state.
 *
 * Module scope, not per component: the banner, the checklist page and any
 * "you still owe us X" prompt all read the same numbers, and a per-component
 * copy means completing a step on the page leaves the banner stale until a
 * reload. Every mutating call returns the recomputed state from the server, so
 * there is no local recalculation to drift.
 */
const state = reactive<OnboardingState>({
  steps: [],
  nextStep: null,
  outstandingRequired: 0,
  complete: true,
  total: 0,
  completedCount: 0,
})

let loaded = false

const {$http, $error} = useHttp()

function apply(next: OnboardingState) {
  Object.assign(state, next)
  loaded = true
}

export function useOnboarding() {
  return {
    state: readonly(state),

    get loaded() {
      return loaded
    },

    async load(force = false): Promise<void> {
      if (loaded && !force) return

      const response = await $http.get("/onboarding").catch(e => e)
      if ($error(response.status, response.data?.message)) return

      apply(response.data.data)
    },

    async complete(key: string): Promise<void> {
      const response = await $http.post(`/onboarding/${key}/complete`).catch(e => e)
      if ($error(response.status, response.data?.message)) return

      apply(response.data.data)
    },

    async skip(key: string): Promise<void> {
      const response = await $http.post(`/onboarding/${key}/skip`).catch(e => e)
      if ($error(response.status, response.data?.message)) return

      apply(response.data.data)
    },

    async skipAll(): Promise<void> {
      const response = await $http.post("/onboarding/skip").catch(e => e)
      if ($error(response.status, response.data?.message)) return

      apply(response.data.data)
    },
  }
}
