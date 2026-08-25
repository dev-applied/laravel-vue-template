import {describe, expect, it} from "vitest"
import App from "@/App.vue"

/**
 * App.vue's title fallback.
 *
 * Route names in this project are dotted identifiers, and the old fallback
 * printed them verbatim — so browser tabs, bookmarks and history entries read
 * "booking.show", "tasks.index", "support.tickets". The booking page is public,
 * so that string was what a customer saw and saved.
 *
 * This is the floor, not the goal: a route that matters calls .title() and
 * never reaches here.
 */
const humanise = (App as unknown as {
  methods: {humanise(name: unknown): string | undefined}
}).methods.humanise

describe("App.vue title fallback", () => {
  it("drops a trailing index/show/list and names the subject", () => {
    // "Booking" is what belongs in the tab, not "Show".
    expect(humanise("booking.show")).toBe("Booking")
    expect(humanise("tasks.index")).toBe("Tasks")
    expect(humanise("users.list")).toBe("Users")
  })

  it("keeps a trailing segment that is a real page rather than a verb", () => {
    expect(humanise("support.contact")).toBe("Contact")
    expect(humanise("support.tickets")).toBe("Tickets")
  })

  it("humanises separators and camelCase", () => {
    expect(humanise("audit-log.index")).toBe("Audit log")
    expect(humanise("auth.set-password")).toBe("Set password")
    expect(humanise("billing.invoiceHistory")).toBe("Invoice history")
  })

  it("returns undefined for anything that is not a usable name", () => {
    // undefined lets App.vue fall through to `|| undefined`, so the head
    // plugin leaves the title alone rather than rendering "undefined".
    expect(humanise(undefined)).toBeUndefined()
    expect(humanise("")).toBeUndefined()
    expect(humanise(42)).toBeUndefined()
  })
})
