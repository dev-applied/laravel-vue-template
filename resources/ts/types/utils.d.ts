declare namespace App.Utils {
  export type NewModel<T> = {
    [P in keyof T]?: T[P] | null
  }
}

