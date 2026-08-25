import type {App} from "vue"
import AppRenderedForm from "@modules/FormBuilder/resources/ts/components/AppRenderedForm.vue"

/**
 * Registered globally — a builder-defined form is usually dropped into an
 * existing page rather than given one of its own.
 */
export default function (app: App): void {
  app.component('AppRenderedForm', AppRenderedForm)
}
