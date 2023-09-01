declare namespace BackButton {
  import type { RawLocation } from "vue-router"

  export interface Options {
    rootKey: string,
    keyName: string
  }

  export interface Item {
    text?: string,
    link?: RawLocation
  }

  export interface State {
    link: RawLocation | null,
    text: string | null,
    vms: typeof BackButton[]
  }

  export interface Plugin {
    registerInstance(vm: any): void;

    unregisterInstance(vm: any): void;

    setLink(link: RawLocation | null): void;

    setText(text: string | null): void;
  }
}
