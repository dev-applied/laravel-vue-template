// Route NAMES the kernel itself navigates to.
//
// They live in this import-free file so both paths.ts and every module's
// routes.ts can import them without an import cycle: paths.ts eager-globs the
// module routes files, so a module that imported paths.ts would read ROUTES
// before it is initialised (TDZ error at boot).
//
// Contract:
//   - DASHBOARD is registered by the kernel (paths.ts).
//   - LOGIN must be registered by SOME module — modules/Auth does. The kernel
//     depends on it existing: Authorization.ts bounces guests to it with a
//     deep link, axios.ts sends 401s there, Guest.ts sends logged-in users to
//     DASHBOARD. Remove modules/Auth and you must register a route named
//     KERNEL_ROUTES.LOGIN yourself.
export const KERNEL_ROUTES = {
  LOGIN: "login",
  DASHBOARD: "dashboard",
}
