declare namespace Breadcrumbs {
  // Inline import type: a top-level import would turn this .d.ts into a
  // module and stop `declare namespace` from being global. Vue Router 4
  // renamed RouteLocationRaw to RouteLocationRaw.
  type RouteLocationRaw = import("vue-router").RouteLocationRaw


  export interface Options {
    keyName: string
  }

  export interface Item {
    icon?: string,
    text?: string,
    disabled?: boolean,
    to?: RouteLocationRaw | string
  }

  export interface State {
    items: any[]; // Replace 'any' with the actual type of breadcrumb items if available
    vms: any[]; // Replace 'any' with the actual type of Vue instances if available
  }

  export interface Plugin {
    registerInstance(vm: any): void; // Replace 'any' with the actual type of Vue instance if available
    unregisterInstance(vm: any): void; // Replace 'any' with the actual type of Vue instance if available
    setItems(items: any[]): void; // Replace 'any' with the actual type of breadcrumb items if available
  }
}
