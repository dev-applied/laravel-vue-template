import type {App} from "vue"
import AppSavedViews from "@modules/SavedViews/resources/ts/components/AppSavedViews.vue"

/**
 * Registered globally — the picker clips onto existing listing screens, so
 * adding it should not mean editing each page's imports.
 */
export default function (app: App): void {
  app.component('AppSavedViews', AppSavedViews)
}
