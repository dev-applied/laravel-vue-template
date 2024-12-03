import { forEach } from "lodash"
import { useAppStore } from "@/stores/app"

const errorHandler = (
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
    let field = internal_key ? `${internal_key}.${key}` : key
    if (typeof value === "object") {
      return loopErrors(value, field)
    }
    if (Array.isArray(value)) {
      value = value.join(", ")
    }
    field = field.split(".")[0]
    useAppStore().addError(`${field} - ${value}`)
  })
}

export default errorHandler
