<template>
  <div>
    <v-btn-primary
      :loading="loading"
      class="text-capitalize mb-2 app-file-upload-btn"
      v-bind="$attrs"
      @click="open"
    >
      <slot />
    </v-btn-primary>
    <v-bottom-sheet
      :content-class="'app-file-upload-dialog ' + (minimized? 'app-file-upload-dialog--minimized' : '')"
      :model-value="!!files.length"
      :scrim="false"
      attach
      max-width="600"
      no-click-animation
      persistent
      scroll-strategy="none"
    >
      <v-card class="app-file-upload-dialog__card">
        <v-card-title class="d-flex align-center">
          Upload In Progress
          <v-spacer />
          <v-icon @click="minimized = !minimized">
            {{ minimized ? 'mdi-plus' : 'mdi-minus' }}
          </v-icon>
        </v-card-title>
        <v-card-text class="app-file-upload-dialog__card__body">
          <v-list>
            <v-list-item
              v-for="file in files"
              :key="file.id"
              lines="three"
            >
              <v-list-item-title>{{ file.name }}</v-list-item-title>
              <v-list-item-subtitle>{{ formatSize(file.size) }}</v-list-item-subtitle>
              <div class="d-flex align-center ga-3">
                <v-progress-linear
                  :color="progressColor(file)"
                  :indeterminate="file.indeterminate"
                  :model-value="file.progress"
                  height="10"
                  rounded="lg"
                />
                {{ file.progress }}%
                <v-icon
                  v-if="file.status === 'error'"
                  @click="removeFile(file)"
                >
                  mdi-close
                </v-icon>
              </div>
              <v-messages
                :active="!!file.message"
                :color="file.status === 'error' ? 'error' : 'black'"
                :messages="[file.message!]"
                class="mb-1"
              />
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </v-bottom-sheet>
  </div>
</template>

<script lang="ts" setup>
import {useFileDialog} from "@vueuse/core"
import {type PropType, ref} from "vue"
import MimeMatcher from "mime-matcher"
import useFileUpload from "@/composables/useFileUpload"

defineOptions({
  inheritAttrs: false
})

const loading = ref(false)
const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: undefined
  },
  accept: {
    type: Array as PropType<string[]>,
    default: () => []
  },
  maxSize: {
    type: Number,
    default: 10242880
  },
  multiple: {
    type: Boolean,
    default: false
  },
  folderId: {
    type: Number,
    default: undefined
  }
})
const minimized = ref(false)

const $emits = defineEmits<{
  (event: 'uploaded'): void
}>()

const {open, onChange} = useFileDialog({
  multiple: props.multiple,
  accept: props.accept.join(', '),
  reset: true,
})

type FakeFile = {
  id: string,
  name: string,
  file: File,
  size: number,
  progress: number,
  indeterminate: boolean,
  status: "pending" | "uploading" | "success" | "error",
  message: string | null,
}
const files = ref<FakeFile[]>([])

const removeFile = (file: FakeFile) => {
  const idx = files.value.findIndex(f => f.id === file.id)
  if (idx > -1) files.value.splice(idx, 1)
}

const updateFile = (file: FakeFile, updates: Partial<FakeFile>) => {
  const idx = files.value.findIndex(f => f.id === file.id)
  if (idx > -1) {
    files.value[idx] = Object.assign(files.value[idx], updates)
  }
}

const inputFiles = (inputFiles: File[]) => {
  inputFiles.forEach((file) => {
    const fakeFile: FakeFile = {
      id: Date.now().toString() + Math.random().toString(),
      name: file.name,
      size: file.size,
      file: file,
      progress: 0,
      indeterminate: false,
      status: "pending",
      message: null,
    }

    if (props.multiple) {
      files.value.push(fakeFile)
      const itemSize = (file.size / 1024 / 1024).toFixed(2)
      if (Number(itemSize) > props.maxSize) {
        fakeFile.status = "error"
        fakeFile.message = `File ${file.name} size must be less than ${props.maxSize}mb`
        return
      }

      if (props.accept.length && !new MimeMatcher(...props.accept).match(file.type)) {
        fakeFile.status = "error"
        fakeFile.message = `File ${file.name} is an unsupported file format`
        return
      }

      uploadFileToServer(fakeFile, files.value.length - 1)
    } else {
      files.value = [fakeFile]
      uploadFileToServer(fakeFile, 0)
    }

  })
}

// Upload file to server
const uploadFileToServer = async (file: FakeFile, index: number) => {
  const fileUploader = useFileUpload({
    folderId: props.folderId,
    onProgress: (progressEvent) => {
      updateFile(file, {
        progress: progressEvent.progress,
        indeterminate: progressEvent.scanning_for_virus,
        message: progressEvent.scanning_for_virus ? 'Scanning for viruses...' : null,
      })
    }
  })

  try {
    await fileUploader(file.file)
    updateFile(file, {
      status: "success",
      message: 'File uploaded successfully',
      indeterminate: false,
      progress: 100,
    })
    setTimeout(() => {
      removeFile(file)
    }, 5000)
    $emits('uploaded')
  } catch (error: any) {
    files.value.splice(index, 1)
    file.message = error.message
  }
}


const formatSize = (size: number): string => {
  const i = Math.floor(Math.log(size) / Math.log(1024))
  const itemSize = size / Math.pow(1024, i)
  return itemSize.toFixed(2) + " " + ["B", "KB", "MB", "GB"][i]
}

const progressColor = (file: FakeFile): string => {
  switch (file.status) {
    case "success":
      return "success"
    case "error":
      return "error"
    default:
      return "primary"
  }
}

onChange(async (files) => {
  if (!files?.length) return
  inputFiles(Array.from(files))
})
</script>

<style lang="scss" scoped>
@use "@/scss/settings";
@use "sass:map";

/* base “open” state */
:deep(.app-file-upload-dialog) {
  /* pin to bottom/right */
  position: fixed !important;
  bottom: 0;
  left: unset !important;

  box-shadow: unset !important;
  transition: transform 0.3s ease-in-out !important;
  transform: translateY(0) !important;

  @media #{map.get(settings.$display-breakpoints, 'md-and-up')} {
    right: 16px !important;
  }
}

:deep(.app-file-upload-dialog--minimized) {
  transform: translateY(calc(100% - 45px)) !important;
}

.app-file-upload-dialog__card {
  border-top-left-radius: 16px !important;
  border-top-right-radius: 16px !important;

  &__body {
    max-height: 400px;
    overflow-y: auto;
  }
}

</style>
