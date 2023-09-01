import Vue from "vue"
import pinia from "@/plugins/pinia"
import vuetify from "@/plugins/vuetify"
import "@/layouts/register"
import "@/plugins"
import App from "./App.vue"
import router from "@/router"

Vue.config.productionTip = false

new Vue({
  vuetify,
  router,
  pinia,
  render: h => h(App)
}).$mount("#app")
