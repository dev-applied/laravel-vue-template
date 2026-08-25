import type {App} from "vue"
import AppTagInput from "@modules/Tags/resources/ts/components/AppTagInput.vue"
import AppTagFilter from "@modules/Tags/resources/ts/components/AppTagFilter.vue"

/**
 * Registered globally — tags bolt onto existing detail and listing screens.
 */
export default function (app: App): void {
  app.component('AppTagInput', AppTagInput)
  app.component('AppTagFilter', AppTagFilter)
}
