import {computed} from "vue"

function isURL(str: any) {
  let url: any

  try {
    if (!str) return true
    if (!str.includes('https://') && !str.includes('http://')) {
      str = 'https://' + str
    }
    url = new URL(str)
  } catch (_) {
    return false
  }

  return url.protocol === "http:" || url.protocol === "https:"
}

function hasLowercase(val: string | null) {
  if (typeof val !== 'string') return false
  return val.split('').some(c => inCharacterRange(c, 'a', 'z'))
}

function hasUppercase(val: string | null) {
  if (typeof val !== 'string') return false
  return val.split('').some(c => inCharacterRange(c, 'A', 'Z'))
}

function hasNumber(val: string | null) {
  if (typeof val !== 'string') return false
  return val.split('').some(c => inCharacterRange(c, '0', '9'))
}

function hasSymbol(val: string | null) {
  if (typeof val !== 'string') return false
  return val.split('').some(c => [
    '~', '`', '!', '@', '#', '$',
    '%', '^', '&', '*', '(', ')',
    '_', '-', '+', '=', '{', '[',
    '}', ']', '|', '', ':', ';',
    '"', '\'', '<', ',', '>', '.',
    '?', '/'
  ].includes(c))
}

function hasNumberOfChars(val: string | null) {
  if (typeof val !== 'string') return false
  return val.length >= 8
}

function hasSpace(val: string | null) {
  if (typeof val !== 'string') return false
  return !!val.length && !val.includes(' ')
}

function inCharacterRange(subject: string, beginning: string, end: string) {
  const subjectCode = subject.charCodeAt(0)
  const beginningCode = beginning.charCodeAt(0)
  const endCode = end.charCodeAt(0)
  return subjectCode >= beginningCode && subjectCode <= endCode
}

export const rules = {
  required(message: string = 'Field is required') {
    return (v: any) => {
      if (v === null || v === undefined || v === '') {
        return message
      }
      if (typeof v === 'number' && v === 0) {
        return message
      }

      if (Array.isArray(v) && v.length === 0) {
        return message
      }
      return true
    }

  },
  email(message: string = "E-mail must be valid") {
    return (v: any) => !v || (new RegExp(/^[^\s@]+@[^\s@]+\.[^\s@]+$/).test(v) && (v && v.length <= 254)) || message
  },
  phone(message: string = "Invalid Phone Number") {
    return function (v: any): string | boolean {
      if (!v) return true
      if (v.replaceAll(" ", "").replaceAll("-", "").replaceAll("(", "").replaceAll(")", "").length !== 10) {
        return message
      }
      if (!new RegExp(/^[0-9-() ]*$/).test(v)) {
        return message
      }
      if (!new RegExp(/^\d{10}$/).test(v.replaceAll(" ", "").replaceAll("-", "").replaceAll("(", "").replaceAll(")", ""))) {
        return message
      }

      return true
    }
  },
  number(message: string = "Invalid Number") {
    return (v: any) => !v || !isNaN(parseFloat(v)) && isFinite(v) || message
  },
  url(message: string = "Invalid URL") {
    return (v: any) => !v || isURL(v) || message
  },
  percentage(message: string = "Percentage must be between 0 and 100") {
    return (v: any) => !v || (v <= 100 && v >= 0) || message
  },
  zip(message: string = "ZIP code must be valid") {
    return (v: any) => !v || new RegExp(/^[0-9]{5}(?:-[0-9]{4})?$/).test(v) || message
  },
  password() {
    return function (v: any): string | boolean {
      if (!v) return true

      if (!hasLowercase(v)) {
        return 'Password Must Contain a Lowercase Letter'
      }
      if (!hasUppercase(v)) {
        return 'Password Must Contain a Uppercase Letter'
      }
      if (!hasNumber(v)) {
        return 'Password Must Contain a Number'
      }
      if (!hasSymbol(v)) {
        return 'Password Must Contain a Symbol'
      }
      if (!hasNumberOfChars(v)) {
        return 'Password Must Contain at Least 8 Characters'
      }
      if (!hasSpace(v)) {
        return 'Password Must Not Contain Spaces'
      }
      return true
    }
  },
  confirmed(password: any, message: string = "Passwords must match") {
    return (v: any) => (v === password) || message
  },
  accepted(message: string = "This field must be accepted") {
    return (v: any) => v === true || v === 'on' || v === 'yes' || v === 1 || message
  },
  hasNumberOfChars,
  hasUppercase,
  hasLowercase,
  hasNumber,
  hasSymbol,
  hasSpace,
}

export default function useValidators() {
  const rules = computed(() => rules)

  return {
    rules
  }
}
