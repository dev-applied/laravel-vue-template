import type {ComponentPublicInstance} from "vue"

export {}

declare module 'vue' {
  interface ComponentCustomOptions {
    breadCrumbs?: ((this: ComponentPublicInstance & {[keys: string]: any}) => Breadcrumbs.Item[] | void) | Breadcrumbs.Item[]
  }

  interface ComponentCustomProperties {
    $breadCrumbs: Breadcrumbs.Plugin;
    $breadCrumbsComputed: Breadcrumbs.Item[];
  }
}
