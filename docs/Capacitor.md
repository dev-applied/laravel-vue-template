# Capacitor (iOS / Android) Integration

This template ships Capacitor 7 as an **opt-in** mobile target. Projects that don't need native apps install nothing extra; projects that do get a full scaffolded path in minutes.

## What's installed

**Runtime packages** (`package.json` dependencies):

- `@capacitor/core`, `@capacitor/cli`, `@capacitor/ios`, `@capacitor/android`
- `@capacitor/app` — back button, app state, deep links
- `@capacitor/preferences` — persistent key/value (used by token storage)
- `@capacitor/keyboard` — keyboard show/hide events
- `@capacitor/status-bar` — status bar styling
- `@capacitor/splash-screen` — manual splash hide after mount
- `@capacitor/network` — connectivity detection (web shim works in browsers too)

**Not installed by default** (per-project decisions):
- `@capacitor/camera`, `@capacitor/geolocation`, `@capacitor/push-notifications`, `@capacitor/biometric`, etc. — add with `npm i @capacitor/<plugin>` when a project needs them.

## Activation per project

```sh
npm install
npm run cap:add:ios            # generates ./ios/  (per-machine, gitignored)
npm run cap:add:android        # generates ./android/  (per-machine, gitignored)
npm run build:capacitor        # vite build --mode capacitor → ./dist/
npm run cap:sync               # copies ./dist/ into the native projects
npm run cap:open:ios           # opens Xcode
npm run cap:open:android       # opens Android Studio
```

The `ios/` and `android/` platform folders are **per-developer-machine** and **per-project** — they're in `.gitignore`. Each project decides whether to commit them (most don't; CI builds them fresh from `cap add`).

## Configuration

Edit `capacitor.config.ts`:

- `appId` — change to `com.appliedimagination.<projectname>` (no dashes)
- `appName` — display name
- `server.url` (commented) — uncomment for native hot-reload against the dev Vite server (use your machine's LAN IP, not `localhost`)

## Dev loop

Two flavors:

1. **Iterate native code**: rebuild Vite, re-sync, run.
   ```sh
   npm run cap:run:ios       # build:capacitor + cap sync + cap run ios
   ```

2. **Hot reload from Vite into the native app**: uncomment `server.url` in `capacitor.config.ts`, point at `http://<LAN-IP>:8080`, then:
   ```sh
   npm run dev               # one terminal
   npm run cap:sync          # one-time
   npm run cap:open:ios      # build & launch from Xcode
   ```
   Edits in Vite hot-reload in the running native app. Faster than the build/sync cycle.

## Code patterns

**Platform detection** — always use the composable, never raw `Capacitor.getPlatform()` in feature code:

```ts
import { useCapacitor } from "@/composables/useCapacitor"

const { isNative, platform, online } = useCapacitor()
if (isNative.value) { /* … */ }
```

**Persistent storage** — use `useStorage` for anything that needs to survive an app launch on native (auth tokens, user preferences, cached identifiers):

```ts
import { useStorage } from "@/composables/useStorage"

const storage = useStorage()
await storage.set("settings.theme", "dark")
const theme = await storage.get("settings.theme")
```

iOS WebView can clear `localStorage` at any time; `useStorage` is backed by Capacitor Preferences on native, which persists across launches.

Plain `localStorage` is fine for purely-cosmetic browser-only state that you can afford to lose (e.g. `LoginPage.vue`'s "remember email" checkbox). Anything the app depends on at boot should go through `useStorage`.

**Auth tokens** — the existing flow handles native correctly out of the box. `plugins/authToken.ts` caches the token in-memory for sync axios reads and persists via `useStorage` for cross-launch survival.

**Back button + deep links** — register from a root layout (NOT each page):

```ts
import { useAppLifecycle } from "@/composables/useAppLifecycle"

useAppLifecycle({
  onUrlOpen: (event) => {
    const url = new URL(event.url)
    router.push(url.pathname)
  },
})
```

The default back-button behavior is `router.back()` when history exists, `App.exitApp()` otherwise.

**Keyboard awareness** — keep critical controls above the keyboard:

```ts
const { isOpen, height } = useKeyboard()

// in template:
// :style="{ marginBottom: isOpen ? `${height}px` : 0 }"
```

**Safe-area insets** — globally handled via `resources/scss/safe-area.scss` (pads `v-application__wrap`). For components anchored to edges, use the utility classes:

- `pt-safe` / `pb-safe` / `pl-safe` / `pr-safe` / `pa-safe`
- `fab-safe-bottom` / `fab-safe-top` (additive — preserves baseline 16px margin)

## API base URL

Web composes an absolute base URL as `VITE_APP_URL + VITE_API_BASE_URL`. `VITE_APP_URL` points at the same origin the SPA is served from, so Sanctum SPA cookie auth still works — the absolute URL just makes it explicit.

Native (Capacitor) uses `VITE_API_BASE_URL_NATIVE` — absolute URL of the backend. The bundle is loaded from `capacitor://` or `http://localhost`, so a relative URL would 404. `axios.ts` picks the right base automatically via `Capacitor.isNativePlatform()`.

Set both in `.env`:

```env
VITE_API_BASE_URL=/api/v1
VITE_API_BASE_URL_NATIVE="https://staging.example.com/api/v1"
```

## Build mode

`vite.config.ts` has a `capacitor` mode that:

- Sets `base: '/'` (no Laravel asset URLs)
- Disables `publicDir` (Capacitor copies its own assets)
- Outputs to `./dist/` (matches `capacitor.config.ts` `webDir`)
- Strips `laravel-vite-plugin` and `vite-plugin-eslint` from the build (dev-server concerns)
- Preserves Sentry sourcemap upload and `build.sourcemap: true`

`npm run build:capacitor` triggers it.

## Gotchas

- **Cookies don't work cross-origin**. `axios` flips `withCredentials: false` on native automatically; the auth flow is bearer-token-only (which the backend already returns).
- **App Store / Play Store signing** is per-project — handled outside this template. Configure once in the generated `ios/` and `android/` projects.
- **iOS App Transport Security** rejects `http://` URLs by default. Production targets must use HTTPS for the backend. For dev hot-reload, you'll need to whitelist your dev URL in `ios/App/App/Info.plist` (`NSAppTransportSecurity → NSAllowsArbitraryLoads → true`) — and remove that before shipping.
- **Android cleartext traffic** — same story; `android:usesCleartextTraffic="true"` in dev only.
- **Cold-boot flash** between splash and first paint is avoided by `launchAutoHide: false` in `capacitor.config.ts` + manual `SplashScreen.hide()` in `plugins/nativeInit.ts` after mount.

## Testing

Vitest specs for `useCapacitor` and `useStorage` mock `Capacitor.isNativePlatform()` and `Preferences` to exercise both code paths. Add specs alongside any new platform-branching composable.

## CI

`build:capacitor` is smoke-tested in CI on every workflow trigger (no iOS/Android tooling required — just produces `./dist/`). This catches Capacitor-mode build regressions before they reach a developer trying to do `cap sync`.

## Layout integration example

The Capacitor primitives are designed to plug into the existing `DefaultLayout`. Recommended structure:

```vue
<!-- resources/ts/layouts/DefaultLayout.vue -->
<template>
  <v-app id="applied">
    <v-app-bar class="pt-safe">
      <v-app-bar-title>{{ appTitle }}</v-app-bar-title>
    </v-app-bar>

    <AppNetworkBanner />

    <v-main>
      <router-view />
    </v-main>

    <v-bottom-navigation v-if="isNative" class="pb-safe">
      <!-- bottom nav items -->
    </v-bottom-navigation>
  </v-app>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { useRouter } from "vue-router"
import AppNetworkBanner from "@/components/AppNetworkBanner.vue"
import { useCapacitor } from "@/composables/useCapacitor"
import { useAppLifecycle } from "@/composables/useAppLifecycle"
import { useKeyboard } from "@/composables/useKeyboard"

export default defineComponent({
  components: { AppNetworkBanner },
  setup() {
    const router = useRouter()
    const { isNative } = useCapacitor()
    const { isOpen: keyboardOpen, height: keyboardHeight } = useKeyboard()

    // Register Capacitor App-plugin events at the layout level (NOT each page —
    // listeners would stack). The default back-button behavior is router.back()
    // when history exists, App.exitApp() otherwise.
    useAppLifecycle({
      onUrlOpen: (event) => {
        const url = new URL(event.url)
        // Map your custom URL scheme paths to vue-router routes here.
        router.push(url.pathname).catch(() => {})
      },
    })

    return { isNative, keyboardOpen, keyboardHeight }
  },
  computed: {
    appTitle() {
      return import.meta.env.VITE_APP_NAME
    },
  },
})
</script>
```

Patterns demonstrated:

- `pt-safe` on the app bar reaches into the iOS notch.
- `pb-safe` on the bottom navigation lifts above the iOS home indicator.
- `<AppNetworkBanner />` placed once at the layout level — surfaces offline state across all pages without per-page wiring.
- `useAppLifecycle` registered at the layout level so back-button / deep-link handlers don't stack as the user navigates.
- `useKeyboard` exposes `keyboardOpen` and `keyboardHeight` for downstream pages that need to position a submit button above the keyboard.
- `isNative` lets the bottom nav appear only on native (web users get a sidebar via a different layout, etc.).

## Reference

- Capacitor docs: https://capacitorjs.com/docs
- Plugin APIs: https://capacitorjs.com/docs/plugins
