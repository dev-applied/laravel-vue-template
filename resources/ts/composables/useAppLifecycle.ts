import { onBeforeUnmount } from "vue"
import { useRouter } from "vue-router"
import { Capacitor } from "@capacitor/core"
import { App, type URLOpenListenerEvent } from "@capacitor/app"

export interface UseAppLifecycleOptions {
  /**
   * Called when the OS asks the app to back-navigate (Android hardware back
   * button, or iOS edge-swipe in some cases). Defaults to router.back() when
   * there's history, otherwise App.exitApp() on Android.
   */
  onBackButton?: (canGoBack: boolean) => void | Promise<void>

  /** Called when the app moves between foreground/background. */
  onAppStateChange?: (isActive: boolean) => void | Promise<void>

  /**
   * Called when the OS opens the app via a registered URL scheme / universal
   * link. Use to deep-link into a specific route.
   */
  onUrlOpen?: (event: URLOpenListenerEvent) => void | Promise<void>
}

/**
 * Wire Capacitor App-plugin events into a Vue component's lifecycle.
 * No-op on web (the App plugin's web shim is silent for backButton/state/URL).
 *
 *   useAppLifecycle({
 *     onUrlOpen: (event) => {
 *       const url = new URL(event.url)
 *       router.push(url.pathname)
 *     },
 *   })
 *
 * Call from a layout or root component — registering from every page would
 * stack listeners.
 */
export function useAppLifecycle(opts: UseAppLifecycleOptions = {}): void {
  if (!Capacitor.isNativePlatform()) return

  const router = useRouter()
  const handles: Array<{ remove: () => Promise<void> }> = []
  let unmounted = false

  // Track a handle returned by an async addListener(). If the component already
  // unmounted before the promise resolved, remove the handle immediately so we
  // don't leak a native listener past the component's lifetime.
  const track = (h: { remove: () => Promise<void> }): void => {
    if (unmounted) void h.remove()
    else handles.push(h)
  }

  void App.addListener("backButton", async ({ canGoBack }) => {
    if (opts.onBackButton) {
      await opts.onBackButton(canGoBack)
      return
    }
    if (canGoBack) {
      router.back()
    } else {
      await App.exitApp()
    }
  }).then(track)

  if (opts.onAppStateChange) {
    void App.addListener("appStateChange", async ({ isActive }) => {
      await opts.onAppStateChange!(isActive)
    }).then(track)
  }

  if (opts.onUrlOpen) {
    void App.addListener("appUrlOpen", async (event) => {
      await opts.onUrlOpen!(event)
    }).then(track)
  }

  onBeforeUnmount(() => {
    unmounted = true
    handles.forEach(h => { void h.remove() })
  })
}
