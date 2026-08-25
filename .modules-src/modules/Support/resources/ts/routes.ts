import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  CONTACT: "support.contact",
  TICKETS: "support.tickets",
  TICKET:  "support.ticket",
}

// The contact form is deliberately NOT behind Authentication — the person who
// cannot log in is exactly the person who needs to reach support.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/contact",
    () => import("@modules/Support/resources/ts/pages/ContactPage.vue"),
    ROUTES.CONTACT
  )
})
  .layout("Empty")
  .middleware([ForceTypes])
  .props()

// mode=contact DROPS the ticketing pages but keeps this file, so a static
// import of them would fail the vite build with an unresolved module. A glob
// only ever contains files that exist at build time, so the ticketing routes
// simply do not register in the contact variant.
const ticketingPages = import.meta.glob('./pages/Ticket*.vue')

if (ticketingPages['./pages/TicketsPage.vue'] && ticketingPages['./pages/TicketPage.vue']) {
  RouteDesigner.group('', function () {
    RouteDesigner.route(
      "/support/tickets",
      ticketingPages['./pages/TicketsPage.vue'] as never,
      ROUTES.TICKETS
    )
    RouteDesigner.route(
      "/support/tickets/:id",
      ticketingPages['./pages/TicketPage.vue'] as never,
      ROUTES.TICKET
    ).title("Support ticket")
  })
    .layout("Default")
    .middleware([ForceTypes, Authentication, Authorization])
    .props()
}
