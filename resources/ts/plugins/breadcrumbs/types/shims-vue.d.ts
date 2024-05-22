import type Vue from "vue"

declare module "vue/types/options" {
  interface ComponentOptions<V extends Vue = Vue> {
    breadCrumbs?: (() => Breadcrumbs.Item[] | void) | Breadcrumbs.Item[]
  }
}

declare module "vue/types/vue" {
  interface Vue {
    $breadCrumbs: Breadcrumbs.Plugin;
  }
}
