import { Capacitor } from "@capacitor/core"

/**
 * One-shot native initialization. Called from main.ts after Vue mounts.
 *
 * - Hides the splash screen (we set launchAutoHide: false in capacitor.config.ts
 *   so the splash stays up until the app has had a chance to render).
 * - Sets the status bar style + background to match the app's theme.
 *
 * All work is gated behind Capacitor.isNativePlatform() so web bundles skip
 * the dynamic imports entirely.
 */
export async function initNative(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  // Dynamic imports keep the native-only plugins out of the web build.
  const [{ SplashScreen }, { StatusBar, Style }] = await Promise.all([
    import("@capacitor/splash-screen"),
    import("@capacitor/status-bar"),
  ])

  try {
    await StatusBar.setStyle({ style: Style.Default })
    if (Capacitor.getPlatform() === "android") {
      await StatusBar.setBackgroundColor({ color: "#FFFFFF" })
    }
  } catch {
    // Some Android skins reject setStyle on cold boot; ignore.
  }

  try {
    await SplashScreen.hide({ fadeOutDuration: 250 })
  } catch {
    // Splash may already be hidden; ignore.
  }
}
