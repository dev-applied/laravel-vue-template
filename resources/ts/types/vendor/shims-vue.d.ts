import 'vue'
import type {ComputedRef} from "vue";
import { Capacitor } from '@capacitor/core'
import type {Auth} from "@/plugins/auth";
import type {StorePrototype} from "@/plugins/store";
import {Axios} from "axios";

declare module 'vue/types/vue' {
    interface Vue {
        $refs: {
            [key: string]: HTMLElement | any,
        }
        $auth: Auth
        $noty: {
            success: (message: string) => void
            error: (message: string) => void
            warning: (message: string) => void
            info: (message: string) => void
        },
        $error: (status: number, message = 'Unknown Error', errors: boolean | any = false, notify = true) => boolean
        $axios: Axios
    }
}
