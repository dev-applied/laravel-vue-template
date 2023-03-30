import type {middlewareOptions} from "@/middleware/middleware";


export default async function authorization ({ $auth, to, next }: middlewareOptions) {
    if (to?.meta?.permissions_all || to?.meta?.permissions_any) {
        if (!$auth.user) {
            return next({name: 'Login', query: {to: to.fullPath}})
        }

        if (to.meta.permissions_all && !$auth.hasAllPermissions(to.meta.permissions_all)) {
          return next({name: 'Login', query: {to: to.fullPath}})
        }

        if (to.meta.permissions_any && !$auth.hasAnyPermissions(to.meta.permissions_any)) {
          return next({name: 'Login', query: {to: to.fullPath}})
        }
    }
}
