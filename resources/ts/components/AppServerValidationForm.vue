<script generic="T" lang="ts" setup>
import {type MaybeRef, nextTick, ref, toValue, watch} from "vue"
import {useLayout} from "vuetify"
import useHttp from "@/composables/useHttp"
import get from "lodash.get"
import cloneDeep from "lodash.clonedeep"
import {useMessageStore} from "@/stores/message.ts"

const props = withDefaults(defineProps<{
  endpoint: MaybeRef<string>,
  method?: MaybeRef<string>,
  data: MaybeRef<T>,
  hideSuccess?: boolean,
  hideValidationErrors?: boolean,
  successMessage?: MaybeRef<string>,
}>(), {
  method: 'post',
  hideSuccess: false,
  hideValidationErrors: false,
  successMessage: 'Success',
})


const emits = defineEmits(['success', 'error'])
const form = ref()

const errorBag = ref<{ [key: string]: string }>({})
const loading = ref<boolean>(false)
const {$http} = useHttp()
const layout = useLayout()
const previousData = ref<any>(null)

const getErrors = (field: string) => {
  return get(errorBag.value, field, [])
}

const clearError = (field: string) => {
  delete errorBag.value[field]
}

// Watch for changes in form data and clear errors for modified fields
watch(() => toValue(props.data), (newData) => {
  if (!previousData.value || Object.keys(errorBag.value).length === 0) {
    previousData.value = cloneDeep(newData)
    return
  }

  // Check each field that has an error
  Object.keys(errorBag.value).forEach((field) => {
    const newValue = get(newData, field)
    const oldValue = get(previousData.value, field)

    // If the field value has changed, clear its error
    if (newValue !== oldValue) {
      clearError(field)
    }
  })

  // Store a deep copy for next comparison
  previousData.value = cloneDeep(newData)
}, { deep: true })

const submit: () => Promise<void> = async () => {
  if (!((await toValue(form).validate()).valid)) {
    throw new Error('Form is invalid')
  }

  const endpoint = toValue(props.endpoint)
  const method = toValue(props.method) ?? 'post'
  let config: any = toValue(props.data)

  if (method === "GET") {
    config = {
      params: config
    }
  }

  loading.value = true
  const {status, data} = await $http[method.toLowerCase()](toValue(endpoint), config).catch((e: any) => e)
  loading.value = false

  if (status === 422 && props.hideValidationErrors) {
    errorBag.value = data?.errors ?? {}
    emits('error', errorBag.value)

    return
  }

  if (status > 204) {
    useMessageStore().addError(data?.message ?? 'Unknown Error')
    errorBag.value = data?.errors ?? {}
    emits('error', errorBag.value)
    if (Object.keys(errorBag.value).length !== 0) {
      await nextTick(() => {
        window.scrollTo({
          top: document.getElementsByClassName('v-input--error')[0]?.getBoundingClientRect().top - layout.mainRect.value.top,
          behavior: 'smooth',
        })
      })
    }

    return
  }

  if (!props.hideSuccess) {
    useMessageStore().addSuccess(toValue(props.successMessage))
  }
  emits('success', data)
}

defineExpose({
  submit,
})


</script>

<template>
  <v-form
    ref="form"
    :readonly="loading"
    v-bind="$attrs"
    validate-on="eager"
    @submit.prevent
  >
    <slot v-bind="{ submit, loading, getErrors, errorBag }" />
  </v-form>
</template>
