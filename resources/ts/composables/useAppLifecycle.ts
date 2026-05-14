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

  void App.addListener("backButton", async () => {
    const canGoBack = window.history.length > 1
    if (opts.onBackButton) {
      await opts.onBackButton(canGoBack)
      return
    }
    if (canGoBack) {
      router.back()
    } else {
      await App.exitApp()
    }
  }).then(h => handles.push(h))

  if (opts.onAppStateChange) {
    void App.addListener("appStateChange", async ({ isActive }) => {
      await opts.onAppStateChange!(isActive)
    }).then(h => handles.push(h))
  }

  if (opts.onUrlOpen) {
    void App.addListener("appUrlOpen", async (event) => {
      await opts.onUrlOpen!(event)
    }).then(h => handles.push(h))
  }

  onBeforeUnmount(() => {
    handles.forEach(h => { void h.remove() })
  })
}
