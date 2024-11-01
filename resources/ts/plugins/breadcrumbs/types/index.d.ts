declare namespace Breadcrumbs {
  import type { RawLocation } from "vue-router"

  export interface Options {
    keyName: string
  }

  export interface Item {
    icon?: string,
    text?: string,
    disabled?: boolean,
    to?: RawLocation | string
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
