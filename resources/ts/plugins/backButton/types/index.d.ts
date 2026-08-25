declare namespace BackButton {
  // Inline import type: a top-level import would turn this .d.ts into a
  // module and stop `declare namespace` from being global. Vue Router 4
  // renamed RouteLocationRaw to RouteLocationRaw.
  type RouteLocationRaw = import("vue-router").RouteLocationRaw


  export interface Options {
    rootKey: string,
    keyName: string
  }

  export interface Item {
    text?: string,
    link?: RouteLocationRaw
  }

  export interface State {
    link: RouteLocationRaw | null,
    text: string | null,
    vms: typeof BackButton[]
  }

  export interface Plugin {
    registerInstance(vm: any): void;

    unregisterInstance(vm: any): void;

    setLink(link: RouteLocationRaw | null): void;

    setText(text: string | null): void;
  }
}
