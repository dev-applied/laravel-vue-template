<template>
  <div class="mb-3">
    <v-row no-gutters>
      <div class="font-weight-bold mb-2 mediumgray--text">
        {{ props.label }}
      </div>
    </v-row>
    <v-btn
      :loading="loading"
      v-bind="props.btnAttributes"
      color="primary"
      class="text-capitalize mb-2"
      @click="open"
    >
      <span v-if="!internalFile || !internalFile.name">Upload {{ props.buttonTitle }}</span>
      <span v-else>Change {{ props.buttonTitle }}</span>
    </v-btn>
    <span
      v-if="props.extraInfo"
      class="ml-3 align-center mt-2 red--text"
    >
      {{ props.extraInfo }}
    </span>
    <v-messages
      :active="errorMessages.length > 0"
      :messages="errorMessages"
      class="mx-auto mb-1"
      color="error"
    />
    <div
      v-if="internalFile && internalFile.name"
      class="align-center font-weight-bold d-flex mb-6"
    >
      <v-img
        v-if="fileId && isImage"
        :src="$file.url(fileId, 'thumbnail')"
        :style="`max-width: ${props.width}px;`"
        :width="props.width"
        class="mr-4"
        contain
        :rounded="props.previewCircle ? 'circle' : undefined"
      />
      <a
        v-if="!isImage"
        :href="$file.url(internalFile.id)"
        target="_blank"
        title="View File"
        class="primary--text text-decoration-none"
      >
        File: {{ internalFile.name }} ({{ size(internalFile) }})
      </a>
      <span v-else>
        File: {{ internalFile.name }}
      </span>

      <v-btn
        :title="`Remove ${props.buttonTitle}`"
        class="ml-3"
        variant="text"
        @click="removeFile"
      >
        <v-icon
          color="red"
          size="24"
        >
          mdi-close
        </v-icon>
      </v-btn>
    </div>
  </div>
</template>

<script lang='ts' setup>
import {useFileDialog} from "@vueuse/core"
import useErrorHandler from "@/composables/useErrorHandler"
import useAxios from "@/composables/useAxios"
import {computed, type PropType, ref, watch} from "vue"

const fileId = defineModel<number | undefined | null>()
const internalFile = ref<App.Models.File | undefined>()
const loading = ref(false)
const {axios} = useAxios()
const {errorHandler} = useErrorHandler()
const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: undefined
  },
  label: {
    type: String,
    default: 'File Upload'
  },
  buttonTitle: {
    type: String,
    default: 'Image'
  },
  extraInfo: {
    type: String,
    default: '(Select an image less than 10mb)'
  },
  accept: {
    type: String,
    default: 'image/png, image/jpeg, image/jpg'
  },
  width: {
    type: Number,
    default: 60
  },
  height: {
    type: Number,
    default: 60
  },
  maxSize: {
    type: Number,
    default: 10242880
  },
  btnAttributes: {
    type: Object,
    default: () => ({})
  },
  errorMessages: {
    type: Array as PropType<string[]>,
    default: () => []
  },
  previewCircle: {
    type: Boolean,
    default: false
  }
})
const internalErrorMessages = ref<string[]>([])
const isImage = computed(() => {
  return props.accept.includes('image')
})

const { open, onChange } = useFileDialog({
  multiple: false,
  accept: props.accept,
  reset: true
})

const removeFile = () => {
  internalFile.value = undefined
  fileId.value = null
}

const getFile = async (id: number) => {
  const { data: { file, message, errors }, status } = await axios.get(`/files/view/${id}`, {}).catch(e => e)
  if (errorHandler(status, message, errors)) {
    return
  }
  internalFile.value = file
}

const size = (file: App.Models.File) => {
  const size = file.size || 0
  if (size < 1000) {
    return `${Math.floor(size)} kb`
  } else {
    return `${Math.floor(size / 1000)} mb`
  }
}

watch(() => props.errorMessages, (messages: string[]) => {
  internalErrorMessages.value = messages
})

onChange(async (files) => {
  if (!files?.length) return
  const submittedFile = files[0]

  const formData = new FormData()
  internalErrorMessages.value = []
  if (submittedFile.size > props.maxSize) {
    const size = Math.floor(props.maxSize / 1000000)
    internalErrorMessages.value.push(`File size must be less than ${size}mb`)
    return
  }

  formData.append('file', submittedFile)


  loading.value = true
  const { data: { file, message, errors }, status } = await axios.post<{file: App.Models.File}>('/files/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  }).catch(e => e)
  loading.value = false
  if (errorHandler(status, message, errors)) {
    internalErrorMessages.value.push(message)
    return
  }
  internalFile.value = file


  fileId.value = file.id
})

watch(fileId, (id: number | undefined | null) => {
  if (id) {
    getFile(id)
  } else {
    internalFile.value = undefined
  }
}, { immediate: true })

</script>

<style lang='scss' scoped>
</style>
