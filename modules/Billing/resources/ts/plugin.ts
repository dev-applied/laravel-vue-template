import type {App} from "vue"
import AppPaywallGate from "@modules/Billing/resources/ts/components/AppPaywallGate.vue"
import AppSubscriptionStatus from "@modules/Billing/resources/ts/components/AppSubscriptionStatus.vue"

export default function (app: App): void {
  app.component('AppPaywallGate', AppPaywallGate)
  app.component('AppSubscriptionStatus', AppSubscriptionStatus)
}
