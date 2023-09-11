// @ts-ignore
import { validateNumber } from "vuetify/lib/components/VCalendar/util/props"
import { defineComponent } from "vue"

export default defineComponent({
  computed: {
    rules() {
      return {
        required: [
          (v: any) => (v !== undefined && v !== null && !Array.isArray(v)) || v === 0 || (Array.isArray(v) && v.length > 0) || 'Field is required'
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
          (v: any) => validateNumber(v) || "Invalid Number is Required"
        ],
        url: [
          // @ts-ignore
          (v: any) => this.isURL(v) || 'URL is not valid',
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
        ]
      }
    }
  },
  methods: {
    isURL(str: any) {
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
    },
    confirmPasswordRules(val1: any, val2: any) {
      return [
        () => (val1 === val2) || "Passwords must match",
        (v: any) => !!v || "Confirmation Password is required"
      ]
    },
    hasLowercase(val: string | null) {
      if (typeof val !== 'string') return false
      return val.split('').some(c => this.inCharacterRange(c, 'a', 'z'))
    },
    hasUppercase(val: string | null) {
      if (typeof val !== 'string') return false
      return val.split('').some(c => this.inCharacterRange(c, 'A', 'Z'))
    },
    hasNumber(val: string | null) {
      if (typeof val !== 'string') return false
      return val.split('').some(c => this.inCharacterRange(c, '0', '9'))
    },
    hasSymbol(val: string | null) {
      if (typeof val !== 'string') return false
      return val.split('').some(c => [
        '~', '`', '!', '@', '#', '$',
        '%', '^', '&', '*', '(', ')',
        '_', '-', '+', '=', '{', '[',
        '}', ']', '|', '', ':', ';',
        '"', '\'', '<', ',', '>', '.',
        '?', '/'
      ].includes(c))
    },
    hasNumberOfChars(val: string | null) {
      if (typeof val !== 'string') return false
      return val.length >= 8
    },
    hasSpace(val: string | null) {
      if (typeof val !== 'string') return false
      return !!val.length && !val.includes(' ')
    },
    inCharacterRange(subject: string, beginning: string, end: string) {
      const subjectCode = subject.charCodeAt(0)
      const beginningCode = beginning.charCodeAt(0)
      const endCode = end.charCodeAt(0)
      return subjectCode >= beginningCode && subjectCode <= endCode
    },
  }
})
