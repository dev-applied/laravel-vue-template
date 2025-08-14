import {RouteBuilder} from "@/router/internal"
import type {RouteRecordRaw} from "vue-router"

export class Redirect extends RouteBuilder {
    private readonly from: string

    private readonly to: string

    constructor(from: string, to: string) {
        super()
        this.from = from
        this.to = to
    }

    // Internal Functions
    public _compile(): RouteRecordRaw[] {
        return [{
            path: this.compileUri(this.from),
            redirect: this.compileUri(this.to),
            meta: this._getMeta(),
        }]
    }
}
