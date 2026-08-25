import RouteDesigner from "@/router/RouteDesigner"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  BOOKING: "booking.show",
}

// Outside the auth pipeline: booking a slot usually happens before anyone has
// an account.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/book/:slug",
    () => import("@modules/Booking/resources/ts/pages/BookingPage.vue"),
    ROUTES.BOOKING
  )
})
  .layout("Empty")
  .middleware([ForceTypes])
  .props()
