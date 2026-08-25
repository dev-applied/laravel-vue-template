import {reactive, readonly} from "vue"
import useHttp from "@/composables/useHttp"

export type ConnectionState = "idle" | "connecting" | "connected" | "unavailable" | "disabled"

interface RealtimeConfig {
  server: string
  key: string
  host: string
  port: number
  scheme: string
  cluster: string
  staleAfterSeconds: number
  enabled: boolean
}

/**
 * One shared socket for the whole app.
 *
 * Module scope, not per component: every listener has to share one connection.
 * A per-component client opens a socket per mounted component, and a server
 * that counts connections — Reverb does — starts refusing them on a page with
 * a handful of live widgets.
 *
 * Configuration is FETCHED rather than read from import.meta.env. A Capacitor
 * build is compiled once and pointed at an API afterwards, so a VITE_ variable
 * frozen at build time is the wrong host from then on.
 */
const state = reactive({
  connection: "idle" as ConnectionState,
  config: null as RealtimeConfig | null,
  lastError: null as string | null,
})

let echo: any = null
let loading: Promise<void> | null = null

const {$http} = useHttp()

async function ensureConfig(): Promise<RealtimeConfig | null> {
  if (state.config) return state.config

  const response = await $http.get("/realtime/config").catch(e => e)
  if (response.status !== 200) return null

  state.config = response.data.data
  return state.config
}

export function useRealtime() {
  return {
    state: readonly(state),

    get echo() {
      return echo
    },

    /**
     * Open the connection, once. Safe to call from every component that wants
     * to listen — the second and later calls await the first.
     */
    async connect(): Promise<void> {
      if (echo) return
      if (loading) return loading

      loading = (async () => {
        const config = await ensureConfig()

        if (!config || !config.enabled) {
          // Said out loud rather than retried. A socket to a server that was
          // never configured never opens, and a silent retry loop is
          // indistinguishable from a network problem.
          state.connection = "disabled"
          state.lastError = "Realtime is not configured on this environment."
          return
        }

        state.connection = "connecting"

        const [{default: Echo}, PusherModule] = await Promise.all([
          import("laravel-echo"),
          import("pusher-js"),
        ])

        ;(window as never as {Pusher: unknown}).Pusher = PusherModule.default

        echo = new Echo({
          broadcaster: "reverb",
          key: config.key,
          wsHost: config.host,
          wsPort: config.port,
          wssPort: config.port,
          forceTLS: config.scheme === "https",
          enabledTransports: ["ws", "wss"],
          cluster: config.cluster,
          // The auth endpoint is this module's, not Laravel's default: it is
          // behind auth:sanctum so a bearer-token client can authorise private
          // channels. axios has already attached the header.
          // pusher-js types the callback's first argument as `Error | null`,
          // not a boolean — the older boolean signature is the deprecated one,
          // and passing `true` where an Error is expected compiles under a bare
          // `npm run build` (esbuild strips types) and fails vue-tsc.
          authorizer: (channel: {name: string}) => ({
            authorize: (socketId: string, callback: (error: Error | null, data?: unknown) => void) => {
              $http.post("/broadcasting/auth", {socket_id: socketId, channel_name: channel.name})
                .then(response => callback(null, response.data))
                .catch(error => callback(error instanceof Error ? error : new Error(String(error))))
            },
          }) as never,
        })

        const connector = echo.connector?.pusher?.connection

        connector?.bind("connected", () => {
          state.connection = "connected"
          state.lastError = null
        })
        connector?.bind("unavailable", () => {
          state.connection = "unavailable"
        })
        connector?.bind("error", (error: {error?: {data?: {message?: string}}}) => {
          state.lastError = error?.error?.data?.message ?? "The realtime connection failed."
        })
        connector?.bind("disconnected", () => {
          state.connection = "unavailable"
        })
      })()

      await loading
      loading = null
    },

    /** Leave a channel without tearing down the shared connection. */
    leave(channel: string): void {
      echo?.leave(channel)
    },

    /** Close everything. For sign-out — a socket authorised as the previous user must not survive it. */
    disconnect(): void {
      echo?.disconnect()
      echo = null
      state.connection = "idle"
    },
  }
}
