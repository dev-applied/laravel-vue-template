import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  FORMS: "forms.index",
  FORM_FILL: "forms.fill",
}

// Filling a form in is deliberately outside the auth pipeline: a public form
// has to work for someone with no account. The endpoint still refuses a
// non-public form to a signed-out visitor.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/forms/:slug",
    () => import("@modules/FormBuilder/resources/ts/pages/FormFillPage.vue"),
    ROUTES.FORM_FILL
  )
})
  .layout("Empty")
  .middleware([ForceTypes])
  .props()

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/admin/forms",
    () => import("@modules/FormBuilder/resources/ts/pages/FormsPage.vue"),
    ROUTES.FORMS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
