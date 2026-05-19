import { computed, onBeforeUnmount, ref, type ComputedRef, type Ref } from "vue"
import { Capacitor } from "@capacitor/core"
import { App, type AppInfo } from "@capacitor/app"
import { Network, type ConnectionStatus } from "@capacitor/network"

export type Platform = "ios" | "android" | "web"

export interface UseCapacitorReturn {
  /** True when running in the native shell (iOS / Android), false in any browser. */
  isNative: ComputedRef<boolean>
  /** "ios", "android", or "web". */
  platform: ComputedRef<Platform>
  /** Reactive ref — true when the device has any connectivity. Defaults to true on first call. */
  online: Ref<boolean>
  /** Capacitor App plugin's getInfo(), null on web. Resolves once and caches. */
  appInfo: () => Promise<AppInfo | null>
}

let cachedAppInfo: AppInfo | null | undefined = undefined

/**
 * Single entry point for "what environment am I running in" questions.
 *
 *   const { isNative, platform, online } = useCapacitor()
 *   if (isNative.value) { ... }
 *
 * For richer lifecycle hooks (back button, app state, deep links) see
 * useAppLifecycle. For keyboard show/hide events see useKeyboard.
 */
export function useCapacitor(): UseCapacitorReturn {
  const isNative = computed(() => Capacitor.isNativePlatform())
  const platform = computed<Platform>(() => Capacitor.getPlatform() as Platform)

  const online = ref(true)
  let unlisten: (() => void) | null = null
  let unmounted = false

  // Initialize online state and subscribe to changes. Both work on web (Network
  // plugin has a browser implementation that wraps navigator.onLine).
  void Network.getStatus().then((s: ConnectionStatus) => { online.value = s.connected })
  void Network.addListener("networkStatusChange", (s) => { online.value = s.connected })
    .then(handle => {
      // If we already unmounted before this promise resolved, drop the handle
      // immediately so the native listener doesn't outlive the component.
      if (unmounted) { void handle.remove(); return }
      unlisten = () => { void handle.remove() }
    })

  onBeforeUnmount(() => {
    unmounted = true
    unlisten?.()
  })

  async function appInfo(): Promise<AppInfo | null> {
    if (cachedAppInfo !== undefined) return cachedAppInfo
    if (!isNative.value) { cachedAppInfo = null; return null }
    try {
      cachedAppInfo = await App.getInfo()
    } catch {
      cachedAppInfo = null
    }
    return cachedAppInfo
  }

  return { isNative, platform, online, appInfo }
}
