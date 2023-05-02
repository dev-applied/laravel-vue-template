import Vue from "vue"
import { forEach } from 'lodash'

const errorHandler = (
  status: number,
  message = 'Unknown Error',
  errors: boolean | any = false,
  notify = true
) => {
  if (status > 204) {
    if (message === 'canceled') return true
    if (notify) {
      if (errors) {
        loopErrors(errors)
      } else {
        Vue.prototype.$noty.error(message)
      }
    }
    return true
  }
  return false
}

function loopErrors(errors: any, internal_key: string | null = null) {
  forEach(errors, (value: string | object, key: string) => {
    const field = internal_key ? `${internal_key}.${key}` : key
    // eslint-disable-next-line
    console.error(`value: `, value, `key: ${key}`, `Field: ${field}`)
    if (typeof value === 'object') {
      return loopErrors(value, field)
    }
    Vue.prototype.$noty.error(`${field} - ${value}`)
  })
}

Vue.prototype.$error = errorHandler

export default errorHandler
