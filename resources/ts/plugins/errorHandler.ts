import {forEach} from "lodash"
import {useAppStore} from "@/stores/app"

export const $error = (
  status: number,
  message = "Unknown Error",
  errors: boolean | any = false,
  notify = true
) => {
  if (status > 204) {
    if (message === "canceled") return true
    if (notify) {
      if (errors) {
        loopErrors(errors)
      } else {
        useAppStore().addError(message)
      }
    }
    return true
  }
  return false
}

function loopErrors(errors: any, internal_key: string | null = null) {
  forEach(errors, (value: string | object, key: string) => {
    const field = internal_key ? `${internal_key}.${key}` : key
    if (typeof value === "object") {
      return loopErrors(value, field)
    }

    const field_name = field
      ? field
        .replace(/_/g, ' ')
        .replace(/\./g, ' ')
        .replace(/\w\S*/g, (txt: string) => {
          return txt.charAt(0).toUpperCase() + txt.slice(1).toLowerCase()
        })
      : 'Error'
    const showFieldName = key != '0'

    useAppStore().addError(`${showFieldName ? field_name + ' - ' : ''}${value}`)
  })
}
