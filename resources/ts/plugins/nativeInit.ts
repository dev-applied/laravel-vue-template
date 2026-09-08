import { Capacitor, SystemBars, SystemBarsStyle } from "@capacitor/core"

/**
 * One-shot native initialization. Called from main.ts after Vue mounts.
 *
 * - Sets the system bar (status + navigation) icon style.
 * - Hides the splash screen (we set launchAutoHide: false in capacitor.config.ts
 *   so the splash stays up until the app has had a chance to render).
 *
 * All work is gated behind Capacitor.isNativePlatform() so web bundles skip
 * the dynamic import entirely.
 *
 * Capacitor 8 note: this used @capacitor/status-bar. SystemBars ships inside
 * @capacitor/core and is the supported path for edge-to-edge, which Capacitor 8
 * makes the default. Two behaviours deliberately did NOT carry over:
 *
 *   - `StatusBar.setBackgroundColor()` is gone. Under edge-to-edge the webview
 *     paints behind the bars, so the bar background IS the app background --
 *     set it with `backgroundColor` in capacitor.config.ts and with the app
 *     theme, not with an imperative call. Android 15+ ignores the old call.
 *   - Styling is no longer status-bar-only. SystemBars.setStyle() with no
 *     `bar` option styles the status bar AND the navigation bar together.
 *     Pass `{ bar: SystemBarType.StatusBar }` to target just one.
 *
 * @capacitor/status-bar is still a dependency and still works for a project
 * that genuinely needs the legacy overlay/background APIs on older Android.
 * Prefer SystemBars for anything new.
 */
export async function initNative(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  try {
    await SystemBars.setStyle({ style: SystemBarsStyle.Default })
  } catch {
    // Some Android skins reject setStyle on cold boot; ignore.
  }

  try {
    // Dynamic import keeps the native-only splash plugin out of the web build.
    const { SplashScreen } = await import("@capacitor/splash-screen")
    await SplashScreen.hide({ fadeOutDuration: 250 })
  } catch {
    // Splash may already be hidden; ignore.
  }
}
