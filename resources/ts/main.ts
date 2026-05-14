import {createApp} from 'vue'
import {createPinia} from "pinia"
import vuetify from "@/plugins/vuetify"
import {loadLayouts} from "@/layouts"
import {usePlugins} from "@/plugins"
import {loadAuthToken} from "@/plugins/authToken"
import {initNative} from "@/plugins/nativeInit"
import App from "./App.vue"
import router from "@/router"
import * as Sentry from "@sentry/vue"
import {thirdPartyErrorFilterIntegration} from "@sentry/vue"

const app = createApp(App)

app.use(router)
app.use(vuetify)
Sentry.init({
  app,
  dsn: import.meta.env.VITE_SENTRY_DSN,
  environment: import.meta.env.VITE_APP_ENV,
  integrations: [
    Sentry.replayIntegration(),
    Sentry.browserTracingIntegration({ router }),
    Sentry.captureConsoleIntegration({
      levels: ['error'],
    }),
    thirdPartyErrorFilterIntegration({
      // MUST match the key you added in vite.config.ts
      filterKeys: [import.meta.env.VITE_APP_NAME || 'ai-frontend'],

      // Options:
      // 'drop-error-if-contains-third-party-frames' (Strict)
      // 'drop-error-if-exclusively-contains-third-party-frames' (Less strict, recommended start)
      behaviour: 'drop-error-if-contains-third-party-frames',
    }),
  ],
  // Session Replay
  replaysSessionSampleRate: 0,
  replaysOnErrorSampleRate: 1.0,
})
usePlugins(app)
loadLayouts(app)
app.use(createPinia())


// Load any persisted auth token into the sync in-memory cache before the first
// request fires. On native this reads @capacitor/preferences; on web localStorage.
loadAuthToken().finally(() => {
  router.isReady().then(() => {
    app.mount('#app')
    // Native init runs after mount so the splash hides once the app actually
    // shows pixels — avoids a flash of white between splash and first paint.
    void initNative()
  })
})

export default app
