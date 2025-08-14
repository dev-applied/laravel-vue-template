import type {RouteRecordRaw} from "vue-router"
import RouteDesigner from "@/router/RouteDesigner"
import {RouteBuilder} from "@/router/internal"

export class RouteGroup extends RouteBuilder {
    private readonly callback: () => void

    constructor(uri: string, callback: () => void) {
        super()
        this.attributes.prefix = uri
        this.callback = callback
    }

    // Internal Functions
    public _compile(): RouteRecordRaw[] {
        RouteDesigner._setActiveGroup(this)
        this.callback()
        const routes = this.routes.map((route) => {
            return route._compile()
        }).flat()

        RouteDesigner._setActiveGroup(undefined)

        return routes
    }
}
