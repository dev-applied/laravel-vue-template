import 'vue-router'
import type {middlewareOptions} from "@/middleware/middleware";

declare module 'vue-router/types/router' {
    interface RouteMeta {
        layout?: string
        middleware?: ((data: middlewareOptions) => void | boolean)[]
        permissions_all?: string[]
        permissions_any?: string[]
        title?: string
    }
}
