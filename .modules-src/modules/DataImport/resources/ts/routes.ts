import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  IMPORTS:     "imports.index",
  IMPORT_NEW:  "imports.create",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/imports",
    () => import("@modules/DataImport/resources/ts/pages/ImportsPage.vue"),
    ROUTES.IMPORTS
  )
  RouteDesigner.route(
    "/imports/new",
    () => import("@modules/DataImport/resources/ts/pages/ImportWizardPage.vue"),
    ROUTES.IMPORT_NEW
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
