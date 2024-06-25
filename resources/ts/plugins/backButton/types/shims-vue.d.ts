declare module '@vue/runtime-core' {
  interface ComponentCustomOptions {
    backButton?: (() => BackButton.Item) | BackButton.Item
  }

  interface ComponentCustomProperties {
    $breadCrumbs: BackButton.Plugin;
  }
}

export {}
