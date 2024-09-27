import { createApp } from 'vue'
import { createPinia } from "pinia"
import vuetify from "@/plugins/vuetify"
import {loadLayouts} from "@/layouts"
import {usePlugins} from "@/plugins"
import App from "./App.vue"
import router from "@/router"

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
usePlugins(app)
loadLayouts(app)


router.isReady().then(() => {
  app.mount('#app')
})
app.mount('#app')

export default app
