declare namespace Vuetify {

  export type VForm = Vue & {
    reset: () => void
    validate: () => boolean
    resetValidation: () => void
  }

  import PaginationTableComponent from "@/components/AppPaginationTable.vue"
  export type PaginationTable = InstanceType<typeof PaginationTableComponent>

  import AppAutocompleteComponent from "@/components/AppAutoComplete.vue"
  export type AppAutoComplete = InstanceType<typeof AppAutocompleteComponent>

  export type SelectItem = { text: string, value: any } | any

  export type TableItem = { [key: string]: any }
}
