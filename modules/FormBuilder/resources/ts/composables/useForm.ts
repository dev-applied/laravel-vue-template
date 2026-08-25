import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export type FieldType =
  'text' | 'textarea' | 'email' | 'number' | 'date' |
  'select' | 'multiselect' | 'radio' | 'checkbox'

export type FormAnswer = string | number | boolean | string[] | null

export interface FormFieldOption {
  value: string
  label: string
}

export interface FormField {
  key:          string
  label:        string
  type:         FieldType
  required?:    boolean
  help?:        string | null
  placeholder?: string | null
  options?:     FormFieldOption[] | null
  min?:         number | null
  max?:         number | null
}

export interface RenderedForm {
  name:            string
  slug:            string
  description?:    string | null
  schema:          FormField[]
  successMessage?: string | null
}

/**
 * Loads a form definition and submits it.
 *
 * The definition always comes from the server — the client never tells the
 * server what the form contained, because that would let anyone drop the
 * `required` off a field or invent one.
 */
export default function useForm(slug: string) {
  const {$http, $error} = useHttp()

  const form      = shallowRef<RenderedForm | null>(null)
  // Not `unknown`: it is assignable to nothing, so binding an answer into any
  // field component was a type error. This is what a rendered field can hold.
  const answers   = ref<Record<string, FormAnswer>>({})
  const loading   = ref(false)
  const submitting = ref(false)
  const submitted = ref(false)
  const message   = ref<string | null>(null)

  const fields = computed<FormField[]>(() => form.value?.schema ?? [])

  function blankFor(field: FormField): FormAnswer {
    if (field.type === 'checkbox') return false
    if (field.type === 'multiselect') return []
    return null
  }

  async function load(): Promise<void> {
    loading.value = true

    const response = await $http.get(`/forms/${slug}/render`).catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    form.value = response.data

    const blank: Record<string, FormAnswer> = {}
    for (const field of response.data.schema) blank[field.key] = blankFor(field)
    answers.value = blank
  }

  async function submit(): Promise<boolean> {
    submitting.value = true

    const response = await $http
      .post(`/forms/${slug}/submit`, {answers: answers.value})
      .catch((e: any) => e)

    submitting.value = false
    // AppServerValidationForm surfaces the per-field 422s; anything else goes
    // through the normal handler.
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    submitted.value = true
    message.value = response.data.message

    return true
  }

  return {form, fields, answers, loading, submitting, submitted, message, load, submit}
}
