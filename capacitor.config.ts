import type { CapacitorConfig } from "@capacitor/cli"

/**
 * Capacitor configuration for the Applied Imagination Laravel + Vue template.
 *
 * Per-project: change `appId` and `appName` after bootstrapping a new project.
 * App ID convention: `com.appliedimagination.<projectname>` (no dashes).
 *
 * Activation (per project that needs mobile):
 *   1. `npm install`                            # picks up @capacitor/* packages
 *   2. `npm run cap:add:ios`                    # generates ./ios/ (per-machine, gitignored)
 *   3. `npm run cap:add:android`                # generates ./android/ (per-machine, gitignored)
 *   4. `npm run build:capacitor && npm run cap:sync`
 *   5. `npm run cap:run:ios`  or  `npm run cap:run:android`
 *
 * Toolchain floor (Capacitor 8): Node 22+, Xcode 26.0+, Android Studio Otter
 * (2025.2.1) or newer, JDK 21. Xcode 26 is a hard requirement, not advice --
 * Capacitor 8's iOS binary is built with Swift 6.2 and part of its API is
 * gated behind a feature flag an older compiler cannot see, so Xcode 16 fails
 * with misleading "no member getString" / "incorrect argument label" errors.
 *
 * For hot-reload during native dev against a local Laravel server, uncomment
 * the `server.url` line and point it at the dev Vite URL the device can reach
 * (use your machine's LAN IP, not localhost).
 */
const config: CapacitorConfig = {
  appId:   "com.appliedimagination.template",
  appName: "Laravel Vue Template",
  webDir:  "dist",
  backgroundColor: "#FFFFFF",

  server: {
    androidScheme: "https",
    // url: "http://192.168.1.X:8080",   // uncomment for native hot-reload
    // cleartext: true,                   // required if `url` is http:// during dev
  },

  ios: {
    contentInset: "always",
    limitsNavigationsToAppBoundDomains: true,
  },

  android: {
    backgroundColor: "#FFFFFF",
  },

  plugins: {
    SplashScreen: {
      launchShowDuration:    1500,
      launchAutoHide:        false,  // manually hide from main.ts once Vue mounts
      backgroundColor:       "#FFFFFF",
      androidSplashResourceName: "splash",
      androidScaleType:      "CENTER_CROP",
      showSpinner:           false,
      splashFullScreen:      true,
      splashImmersive:       true,
    },

    // Capacitor 8 replaced the old non-edge-to-edge model with the SystemBars
    // core plugin (bundled in @capacitor/core -- there is nothing to install).
    //
    // Two Capacitor 7 levers are GONE and must not be reintroduced here:
    //   - `android.adjustMarginsForEdgeToEdge` (removed from the config schema)
    //   - `StatusBar.overlaysWebView: false`   (SystemBars does not implement
    //     setOverlaysWebView or setBackgroundColor; on Android 15+ the platform
    //     forces edge-to-edge regardless)
    //
    // `insetsHandling: "css"` is the default and is stated explicitly here so a
    // reader knows edge-to-edge is a deliberate choice. It makes Capacitor read
    // the real insets from WindowInsetsCompat and inject them as
    // --safe-area-inset-* CSS variables. resources/scss/settings.scss folds
    // those into --v-safe-area-*, which is what the app actually reads.
    //
    // Set `insetsHandling: "disable"` ONLY if a project takes over inset
    // handling with a third-party plugin. Disabling it without a replacement
    // puts content under the status and navigation bars on Android.
    SystemBars: {
      insetsHandling: "css",
      style:          "DEFAULT",
      hidden:         false,
    },

    Keyboard: {
      resize:     "body",
      style:      "DEFAULT",
      resizeOnFullScreen: true,
    },
  },
}

export default config
