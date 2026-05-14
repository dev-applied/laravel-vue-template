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
    StatusBar: {
      style:           "DEFAULT",
      backgroundColor: "#FFFFFF",
      overlaysWebView: false,
    },
    Keyboard: {
      resize:     "body",
      style:      "DEFAULT",
      resizeOnFullScreen: true,
    },
  },
}

export default config
