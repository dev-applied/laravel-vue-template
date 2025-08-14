<script generic="T" lang="ts" setup>
import {type MaybeRef, nextTick, ref, toValue} from "vue"
import {useLayout} from "vuetify"
import useHttp from "@/composables/useHttp"
import {get} from "lodash"
import {useAppStore} from "@/stores/app"

const props = withDefaults(defineProps<{
  endpoint: MaybeRef<string>,
  method?: MaybeRef<string>,
  data: MaybeRef<T>,
  hideSuccess?: boolean,
}>(), {
  method: 'post',
  hideSuccess: false,
})


const emits = defineEmits(['success', 'error'])
const form = ref()

const errorBag = ref<{ [key: string]: string }>({})
const loading = ref<boolean>(false)
const {axios} = useHttp()
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
  const {status, data} = await axios[method.toLowerCase()](toValue(endpoint), config).catch((e: any) => e)
  loading.value = false

  if (status > 204) {
    useAppStore().addError(data?.message ?? 'Unknown Error')
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
    useAppStore().addSuccess('Success')
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
