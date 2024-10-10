import { createApp } from 'vue'
import { createPinia } from "pinia"
import vuetify from "@/plugins/vuetify"
import {loadLayouts} from "@/layouts"
import {usePlugins} from "@/plugins"
import App from "./App.vue"
import router from "@/router"
import * as Sentry from "@sentry/vue"

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
    Sentry.captureConsoleIntegration()
  ],
  // Session Replay
  replaysSessionSampleRate: 0,
  replaysOnErrorSampleRate: 1.0,
})
usePlugins(app)
loadLayouts(app)
app.use(createPinia())


router.isReady().then(() => {
  app.mount('#app')
})

export default app
