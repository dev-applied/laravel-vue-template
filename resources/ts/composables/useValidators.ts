import { computed } from "vue"

export default function useValidators() {
  function isURL(str: any) {
    let url

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
  function confirmPasswordRules(val1: any, val2: any) {
    return [
      () => (val1 === val2) || "Passwords must match",
      (v: any) => !!v || "Confirmation Password is required"
    ]
  }
  function confirmPasswordRulesOptional(val1: any, val2: any) {
    return [
      () => !val1 || !val2 || (val1 === val2) || 'Passwords must match'
    ]
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
  const rules = computed(() => {
    return {
      required: [
        (v: any) => (v !== undefined && v !== null && v !== "" && !Array.isArray(v)) || v === 0 || (Array.isArray(v) && v.length > 0) || 'Field is required'
      ],
      email: [
        (v: any) => !!v || "E-mail is required",
        (v: any) => new RegExp(/^[^\s@]+@[^\s@]+\.[^\s@]+$/).test(v) || "E-mail must be valid",
        (v: any) => (v && v.length <= 254) || "Email must be less than 254 characters"
      ],
      emailNotRequired: [
        (v: any) => new RegExp(/^[^\s@]+@[^\s@]+\.[^\s@]+$/).test(v) || "E-mail must be valid",
        (v: any) => !v || (v && v.length <= 254) || "Email must be less than 254 characters"
      ],
      phone: [
        (v: any) => !!v || "Phone Number is required",
        (v: any) => (v && v.replaceAll("-", "").length === 10) || "Phone Number must be 10 digits"
      ],
      phoneNotRequired: [
        (v: any) => !v || (v && v.replaceAll("-", "").length === 10) || "Phone Number must be 10 digits"
      ],
      validNumber: [
        (v: any) => !Number.isNaN(v) || "Invalid Number is Required"
      ],
      url: [
        // @ts-ignore
        (v: any) => isURL(v) || 'URL is not valid',
        // @ts-ignore
        (v: any) => !v || (v && v.length > 0 && v.includes('.') && v[v.length - 1] !== '.') || 'URL is not valid'
      ],
      percentage: [
        (v: any) => v <= 100 || 'You can not have a percentage higher than 100',
        (v: any) => v >= 0 || 'You can not have a percentage less than 0'
      ],
      zip: [
        (v: any) => new RegExp(/^[0-9]{5}(?:-[0-9]{4})?$/).test(v) || "ZIP code must be valid"
      ],
      nonWhitespaceString: [
        (v: any) => v && (v.trim().length > 0) || "Invalid value"
      ],
      password: [
        // @ts-ignore
        (v: any) => hasLowercase(v) || 'Password Must Contain a Lowercase Letter',
        (v: any) => hasUppercase(v) || 'Password Must Contain a Uppercase Letter',
        (v: any) => hasNumber(v) || 'Password Must Contain a Number',
        (v: any) => hasSymbol(v) || 'Password Must Contain a Symbol',
        (v: any) => hasNumberOfChars(v) || 'Password Must Contain at Least 8 Characters',
        (v: any) => hasSpace(v) || 'Password Must Not Contain Spaces'
      ],
      passwordOptional: [
        // @ts-ignore
        (v: any) => !v || hasLowercase(v) || 'Password Must Contain a Lowercase Letter',
        (v: any) => !v || hasUppercase(v) || 'Password Must Contain a Uppercase Letter',
        (v: any) => !v || hasNumber(v) || 'Password Must Contain a Number',
        (v: any) => !v || hasSymbol(v) || 'Password Must Contain a Symbol',
        (v: any) => !v || hasNumberOfChars(v) || 'Password Must Contain at Least 8 Characters',
        (v: any) => !v || hasSpace(v) || 'Password Must Not Contain Spaces'
      ],
      confirmPasswordRules,
      confirmPasswordRulesOptional,
      isURL,
      hasLowercase,
      hasUppercase,
      hasNumber,
      hasSymbol,
      hasNumberOfChars,
      hasSpace,
      inCharacterRange,
    }
  })

  return {
    rules
  }
}
