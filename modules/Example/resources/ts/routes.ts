import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

// Module route-name constants. Exported so paths.ts merges them into the app
// ROUTES object (for `this.$routeTo(this.ROUTES.X)`), and importable directly
// for typed use inside the module's own pages. Prefix names with the module
// slug to avoid collisions.
export const ROUTES = {
  EXAMPLE_NOTES: "example.notes",
}

// A module declares its OWN layout and middleware stack — module routes are
// registered outside the core groups in paths.ts and inherit nothing.
// Pages are passed as lazy imports (never strings): the string resolver only
// sees resources/ts/pages/, and the lazy form code-splits per module.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/example-notes",
    () => import("@modules/Example/resources/ts/pages/ExampleNotesPage.vue"),
    ROUTES.EXAMPLE_NOTES
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
