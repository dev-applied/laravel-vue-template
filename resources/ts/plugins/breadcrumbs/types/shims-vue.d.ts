declare module '@vue/runtime-core' {
  interface ComponentCustomOptions {
    breadCrumbs?: (() => Breadcrumbs.Item[] | void) | Breadcrumbs.Item[]
  }

  interface ComponentCustomProperties {
    $breadCrumbs: Breadcrumbs.Plugin;
  }
}

export {}
