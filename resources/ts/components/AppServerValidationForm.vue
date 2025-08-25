<script generic="T" lang="ts" setup>
import {type MaybeRef, nextTick, ref, toValue} from "vue"
import {useLayout} from "vuetify"
import useHttp from "@/composables/useHttp"
import get from "lodash.get"
import {useMessageStore} from "@/stores/message.ts"

const props = withDefaults(defineProps<{
  endpoint: MaybeRef<string>,
  method?: MaybeRef<string>,
  data: MaybeRef<T>,
  hideSuccess?: boolean,
  successMessage?: MaybeRef<string>,
}>(), {
  method: 'post',
  hideSuccess: false,
  successMessage: 'Success',
})


const emits = defineEmits(['success', 'error'])
const form = ref()

const errorBag = ref<{ [key: string]: string }>({})
const loading = ref<boolean>(false)
const {$http} = useHttp()
const layout = useLayout()

const getErrors = (field: string) => {
  return get(errorBag.value, field, [])
}

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
    useMessageStore().addSuccess('Success')
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
