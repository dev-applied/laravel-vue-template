import type Vue from "vue"

declare module "vue/types/options" {
  interface ComponentOptions<V extends Vue = Vue> {
    backButton?: (() => BackButton.Item) | BackButton.Item
  }
}

declare module "vue/types/vue" {
  interface Vue {
    $breadCrumbs: BackButton.Plugin;
  }
}
