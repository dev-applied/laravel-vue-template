// @ts-ignore
import VueNoty from 'vuejs-noty'
import 'vuejs-noty/dist/vuejs-noty.css'
import Vue from "vue";

Vue.use(VueNoty, {
  timeout: 4000,
  progressBar: true,
  layout: 'bottomLeft'
})
