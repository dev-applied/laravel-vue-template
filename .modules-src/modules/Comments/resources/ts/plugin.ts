import type {App} from "vue"
import AppComments from "@modules/Comments/resources/ts/components/AppComments.vue"

/**
 * Registered globally — comments bolt onto existing detail screens, so adding
 * them should not mean editing each page's imports.
 */
export default function (app: App): void {
  app.component('AppComments', AppComments)
}
