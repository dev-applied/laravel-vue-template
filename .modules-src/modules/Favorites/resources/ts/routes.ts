import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  FAVORITES: "favorites.index",
}

// Signed-in only, and nothing more. A favourite is per-user and grants nothing,
// so there is no permission to gate on — the page shows you your own list.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/favorites",
    () => import("@modules/Favorites/resources/ts/pages/FavoritesPage.vue"),
    ROUTES.FAVORITES
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
