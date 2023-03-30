import type Router from 'vue-router'
import type { $auth } from '@/plugins/auth'
import type {Route, NavigationGuardNext, RawLocation} from "vue-router/types/router";

export declare interface middlewareOptions {
    $auth: typeof $auth
    router: Router
    to: Route
    from: Route
    next: (location?: RawLocation) => false
}

export default (data: { router: Router; $auth: typeof $auth}) =>
  async (to: Route, from: Route, next: NavigationGuardNext) => {
    function callNext(location?: RawLocation): false {
      if (location) {
          next(location)
      } else {
          next()
      }
      return false
    }

    if (to.meta?.middleware?.length) {
      const arr = to.meta.middleware
      for (let index = 0; index < arr.length; index++) {
        const method = arr[index]
        const result = await method({ ...data, to, from, next: callNext })
        if (result === false) {
          return
        }
      }
    }

    return next()
  }
