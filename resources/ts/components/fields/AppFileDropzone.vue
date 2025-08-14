<template>
  <div class="text-left">
    <div
      :class="label ? 'mb-2' : null"
      class="d-flex ga-4 align-center justify-space-between"
    >
      <div class="label">
        {{ label }}<span
          v-if="required"
          class="text-error"
        >*</span>
      </div>
    </div>
    <div class="dropzone">
      <div
        id="dropzoneWrapper"
        ref="dropzoneWrapper"
        :class="{
          'dropzone__wrapper--active': active,
          'dropzone__wrapper--disabled': disabled,
        }"
        class="dropzone__wrapper"
        @mouseleave="blurDrop"
        @mouseover="hover"
        @dragenter.prevent="toggleActive"
        @dragleave.prevent="toggleActive"
        @drop.prevent="drop"
        @dragover.prevent
      >
        <!-- Placeholder content -->
        <template v-if="!files.length">
          <svg
            class="transition-all decoration-neutral-150 ease-linear"
            data-v-bf5656ce=""
            fill="none"
            height="60"
            viewBox="0 0 24 24"
            width="60"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M22 7.81v6.09l-1.63-1.4c-.78-.67-2.04-.67-2.82 0l-4.16 3.57c-.78.67-2.04.67-2.82 0l-.34-.28c-.71-.62-1.84-.68-2.64-.14l-4.92 3.3-.11.08c-.37-.8-.56-1.75-.56-2.84V7.81C2 4.17 4.17 2 7.81 2h8.38C19.83 2 22 4.17 22 7.81Z"
              data-v-bf5656ce=""
              fill="#c3c3c3"
              opacity=".4"
            />
            <path
              d="M9.001 10.381a2.38 2.38 0 1 0 0-4.76 2.38 2.38 0 0 0 0 4.76ZM21.999 13.899v2.29c0 3.64-2.17 5.81-5.81 5.81h-8.38c-2.55 0-4.39-1.07-5.25-2.97l.11-.08 4.92-3.3c.8-.54 1.93-.48 2.64.14l.34.28c.78.67 2.04.67 2.82 0l4.16-3.57c.78-.67 2.04-.67 2.82 0l1.63 1.4Z"
              data-v-bf5656ce=""
              fill="#c3c3c3"
            />
          </svg>
          <div class="titles">
            <h1 class="m-0 text-primary">
              {{ multiple ? "Drop your files here or click to select" : "Drop your file here or click to select" }}
            </h1>
          </div>
        </template>
        <!-- File Preview -->
        <div
          v-else-if="!props.withoutPreview"
          class="preview-container"
        >
          <div
            v-for="file in files"
            :key="file.id"
            :class="{
              preview__multiple: multiple,
              preview__file: !file.type.includes('image/'),
            }"
            class="preview"
          >
            <v-img
              v-if="file && file.type && file.type.includes('image/')"
              :src="file.src ? file.src : $file.url(file.id)"
            />
            <svg
              v-if="file.name.split('.').pop() === 'xls'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-xls"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
              <path d="M4 15l4 6" />
              <path d="M4 21l4 -6" />
              <path
                d="M17 20.25c0 .414 .336 .75 .75 .75h1.25a1 1 0 0 0 1 -1v-1a1 1 0 0 0 -1 -1h-1a1 1 0 0 1 -1 -1v-1a1 1 0 0 1 1 -1h1.25a.75 .75 0 0 1 .75 .75"
              />
              <path d="M11 15v6h3" />
            </svg>
            <svg
              v-if="file.name.split('.').pop() === 'txt'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-txt"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M16.5 15h3" />
              <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
              <path d="M4.5 15h3" />
              <path d="M6 15v6" />
              <path d="M18 15v6" />
              <path d="M10 15l4 6" />
              <path d="M10 21l4 -6" />
            </svg>
            <svg
              v-if="file.name.split('.').pop() === 'doc' || file.name.split('.').pop() === 'docx'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-doc"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
              <path d="M5 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1z" />
              <path d="M20 16.5a1.5 1.5 0 0 0 -3 0v3a1.5 1.5 0 0 0 3 0" />
              <path d="M12.5 15a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1 -3 0v-3a1.5 1.5 0 0 1 1.5 -1.5z" />
            </svg>
            <svg
              v-if="file.name.split('.').pop() === 'pdf'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
              <path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" />
              <path d="M17 18h2" />
              <path d="M20 15h-3v6" />
              <path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1z" />
            </svg>
            <svg
              v-if="file.name.split('.').pop() === 'csv'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-csv"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M14 3v4a1 1 0 0 0 1 1h4" />
              <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
              <path d="M7 16.5a1.5 1.5 0 0 0 -3 0v3a1.5 1.5 0 0 0 3 0" />
              <path
                d="M10 20.25c0 .414 .336 .75 .75 .75h1.25a1 1 0 0 0 1 -1v-1a1 1 0 0 0 -1 -1h-1a1 1 0 0 1 -1 -1v-1a1 1 0 0 1 1 -1h1.25a.75 .75 0 0 1 .75 .75"
              />
              <path d="M16 15l2 6l2 -6" />
            </svg>
            <svg
              v-if="file.name.split('.').pop() === 'mp4' || file.name.split('.').pop() === 'mkv' || file.name.split('.').pop() === 'mpeg-4' || file.name.split('.').pop() === 'webm' || file.name.split('.').pop() === 'mov'"
              class="icon icon-tabler icons-tabler-outline icon-tabler-video"
              fill="none"
              height="40"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              viewBox="0 0 24 24"
              width="40"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M0 0h24v24H0z"
                fill="none"
                stroke="none"
              />
              <path d="M15 10l4.553 -2.276a1 1 0 0 1 1.447 .894v6.764a1 1 0 0 1 -1.447 .894l-4.553 -2.276v-4z" />
              <path d="M3 6m0 2a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2z" />
            </svg>
            <div
              v-if="!file.src"
              class="img-details"
              @click="handleFilePreviewClick(file)"
            >
              <v-btn
                class="img-remove"
                @click.prevent.stop="removeFile(file)"
              >
                <svg
                  class="icon icon-tabler icons-tabler-outline icon-tabler-x"
                  fill="none"
                  height="20"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                  width="20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M0 0h24v24H0z"
                    fill="none"
                    stroke="none"
                  />
                  <path d="M18 6l-12 12" />
                  <path d="M6 6l12 12" />
                </svg>
              </v-btn>
              <h2>{{ file.name }}</h2>
              <span>{{ formatSize(file.size) }}</span>
              <div
                v-if="file.id"
                class="file-actions"
              >
                <AppLightBoxImage
                  v-if="isImageFile(file)"
                  :data-file-id="file.id"
                  :file-id="file.id"
                />
                <v-btn
                  v-else
                  v-tooltip:bottom="'View File'"
                  :href="$file.url(file.id)"
                  class="preview-action"
                  color="primary"
                  icon="mdi-open-in-new"
                  size="small"
                  target="_blank"
                  variant="text"
                />
              </div>
            </div>
            <div
              v-if="file?.status === 'pending' || file?.status === 'uploading'"
              class="progress-bar-container"
            >
              <v-progress-linear
                :indeterminate="file.indeterminate"
                :model-value="file.progress || 1"
                bg-color="white"
                color="primary"
                height="10"
              />
            </div>
          </div>
        </div>

        <template v-if="!files.length || multiple">
          <v-btn-primary
            class="my-2"
            size="small"
            type="button"
            @click="open"
          >
            Select File
          </v-btn-primary>
          <p class="m-0 description">
            Files must be under {{ maxSize }}MB
            {{ accept ? `and in ${acceptText} formats` : "" }}
          </p>
        </template>
      </div>
      <div
        v-if="disabled"
        class="dropzone__wrapper__disabled"
        @click.prevent
        @drop.prevent
        @dragover.prevent
      />
      <v-messages
        v-if="displayErrorMessages.length > 0"
        :messages="displayErrorMessages"
        active
        class="mt-3"
        color="error"
      />

      <!-- Files previews outside -->
    </div>
  </div>
</template>

<script lang="ts" setup>
import {computed, getCurrentInstance, inject, ref, watchEffect} from "vue"
import {useFileDialog} from "@vueuse/core"
import {$error} from "@/plugins/errorHandler"
import MimeMatcher from 'mime-matcher'
import {VForm} from "vuetify/components"
import AppLightBoxImage from "@/components/AppLightBoxImage.vue"
import {fileUrl} from "@/plugins/file"
import {$http} from "@/plugins/axios"
import useFileUpload from "@/composables/useFileUpload"


const form = inject<typeof VForm | null>(Symbol.for('vuetify:form'), null)
if (form) {
  form?.register({
    id: 'app-file-dropzone-' + Math.random().toString(36).substring(7),
    vm: getCurrentInstance(),

    validate: () => {
      if (props.required && !files.value.length) {
        setErrors('This field is required')
        return false
      }
      if (props.rules && props.rules.length) {
        for (const rule of props.rules) {
          if (typeof rule === 'function') {
            const result = rule(files.value)
            if (typeof result === 'string') {
              setErrors(result)
              return false
            }
            if (result instanceof Promise) {
              result.then((result) => {
                if (typeof result === 'string') {
                  setErrors(result)
                  return false
                }
              })
            }
          }
        }
      }
      return true
    },
    reset: () => {
      model.value = props.multiple ? [] : null
      files.value = []
      clearErrors()
    },
    resetValidation: () => {
      clearErrors()
    }
  })
}


type ValidationResult = string | boolean;
type ValidationRule$1 =
  ValidationResult
  | PromiseLike<ValidationResult>
  | ((value: any) => ValidationResult)
  | ((value: any) => PromiseLike<ValidationResult>);


export interface Props {
  required?: boolean,
  label?: string,
  returnObject?: boolean,
  accept?: string[],
  maxSize?: number
  multiple?: boolean
  disabled?: boolean
  errorMessages?: string[]
  rules?: readonly ValidationRule$1[];
  withoutPreview?: boolean
}

const internalErrorMessages = ref<string[]>([])
const displayErrorMessages = computed(() => {
  return [...internalErrorMessages.value, ...props.errorMessages]
})

const isImageFile = (file: App.Models.File) => {
  return file.type && file.type.includes('image/')
}

const handleFilePreviewClick = (file: App.Models.File) => {
  if (isImageFile(file)) {

    const lightboxes = document.querySelectorAll(`[data-file-id="${file.id}"]`)
    if (lightboxes.length > 0) {
      const lightboxBtn = lightboxes[0].querySelector('i')
      if (lightboxBtn) lightboxBtn.click()
    }
  } else {
    window.open(fileUrl(file.id), '_blank')
  }
}

const props = withDefaults(defineProps<Props>(), {
  label: undefined,
  maxSize: 20,
  accept: () => ['image/*'],
  maxFiles: 5,
  multiple: false,
  errorMessages: () => [],
  rules: () => [],
  withoutPreview: false,
})

type Arrayable<T> = T | T[]

const model = defineModel<Arrayable<App.Models.File> | Arrayable<number> | null | undefined>()

const {open, onChange} = useFileDialog({
  multiple: props.multiple,
  accept: props.accept.join(', '),
  reset: true,
})

const files = ref<(App.Models.File | FakeFile)[]>([])
const active = ref(false)
const dropzoneWrapper = ref(null)


const acceptText = computed(() => {
  const acceptText: string[] = []

  for (const accept of props.accept) {
    if (accept === 'image/*') {
      acceptText.push('images')
    } else if (accept === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
      if (!['application/msword'].includes(accept)) {
        acceptText.push('word')
      }
    } else {
      acceptText.push(accept.split('/')[1])
    }
  }
  return acceptText.join(', ')

})

watchEffect(async () => {
  const newfiles = []
  if (!model.value) {
    files.value = newfiles
    return
  }

  let modelValues = model.value
  if (!Array.isArray(modelValues)) {
    modelValues = [modelValues]
  }

  for (const file of modelValues) {
    const foundFile = files.value.find(f => f.id === (typeof file === 'number' ? file : file.id))
    if (foundFile) {
      newfiles.push(foundFile)
      continue
    }
    if (typeof file === 'number') {
      const {data: {file: fileData, message, errors}, status} = await $http.get(`/files/${file}`).catch(e => e)
      if ($error(status, message, errors, false)) {
        setErrors(message)
        return
      }
      newfiles.push(fileData)
    } else {
      newfiles.push(file)
    }
  }

  files.value = newfiles
})

const generateFileId = () => {
  return Math.floor(Math.random() * Math.floor(Math.random() * Date.now()))
}

onChange((files) => {
  if (!files?.length) return
  // @ts-ignore
  inputFiles(Array.from(files))
})

// Manages input files
type FakeFile = {
  id: number,
  src: string,
  name: string,
  type: string,
  size: number,
  progress: number,
  indeterminate: boolean,
  status: "pending" | "uploading" | "success" | "error",
  message: string | null,
}
const inputFiles = (inputFiles: File[]) => {
  clearErrors()
  if (!props.multiple && inputFiles.length > 1) {
    setErrors('Only one file is allowed')
    return
  }
  const filesSizesAreValid: boolean = inputFiles.reduce((previousValue, item) => {
    if (!previousValue) return false
    const itemSize = (item.size / 1024 / 1024).toFixed(2)
    return Number(itemSize) <= props.maxSize
  }, true)

  const filesTypesAreValid: boolean = inputFiles.reduce((previousValue, item) => {
    if (!previousValue) return false
    return props.accept.some(accept => {
      return new MimeMatcher(accept).match(item.type)
    })
  }, true)

  if (!filesSizesAreValid) {
    const largeFiles = inputFiles.filter((item) => {
      const itemSize = (item.size / 1024 / 1024).toFixed(2)
      return Number(itemSize) > props.maxSize
    })
    const errors: string[] = []
    largeFiles.forEach((file) => {
      errors.push(`File ${file.name} size must be less than ${props.maxSize}mb`)
    })
    setErrors(errors)
    return
  }

  if (!filesTypesAreValid) {
    const wrongTypeFiles = inputFiles.filter((item) => !props.accept.includes(item.type))
    const errors: string[] = []
    wrongTypeFiles.forEach((file) => {
      errors.push(`File ${file.name} is an unsupported file format`)
    })
    setErrors(errors)
    return
  }

  inputFiles.forEach((file) => {
    const fakeFile: FakeFile = {
      id: generateFileId(),
      src: URL.createObjectURL(file),
      name: file.name,
      type: file.type,
      size: file.size,
      progress: 0,
      indeterminate: false,
      status: "pending",
      message: null,
    }

    if (props.multiple) {
      files.value.push(fakeFile)
      uploadFileToServer(file, files.value.length - 1)
    } else {
      files.value = [fakeFile]
      uploadFileToServer(file, 0)
    }

  })
}

// Upload file to server
const uploadFileToServer = async (file: File, index: number) => {
  const fileUploader = useFileUpload({
    onProgress: (progressEvent) => {
      files.value[index].progress = progressEvent.progress
      files.value[index].indeterminate = progressEvent.scanning_for_virus
    }
  })

  try {
    const fileData = await fileUploader(file)
    files.value.splice(index, 1, fileData)
    handleFileChange()
  } catch (error) {
    files.value.splice(index, 1)
    setErrors(error.message)
  }
}

// Toggles active state for dropping files(styles)
const toggleActive = () => {
  if (!props.disabled) {
    active.value = !active.value
  }
}

// Handles dropped files and input them
const drop = (e: any) => {
  if (props.disabled) {
    return
  }
  toggleActive()
  inputFiles([...e.dataTransfer.files])
}

// Removes file from files list
const removeFile = async (file: App.Models.File) => {
  files.value = files.value.filter((item) => item.id !== file.id)
  handleFileChange()
}

// Hover and blur manager
const hover = () => {
  if (!files.value.length) {
    active.value = true
  }
}
const blurDrop = () => {
  active.value = false
}

const handleFileChange = () => {
  if (props.returnObject && props.multiple) {
    model.value = files.value
  } else if (props.returnObject) {
    model.value = files.value[0]
  } else if (props.multiple) {
    model.value = files.value.map((file) => file.id)
  } else {
    model.value = files.value[0]?.id
  }
}

const formatSize = (size: number): string => {
  const i = Math.floor(Math.log(size) / Math.log(1024))
  const itemSize = size / Math.pow(1024, i)
  return itemSize.toFixed(2) + " " + ["B", "KB", "MB", "GB"][i]
}

const setErrors = function (errors: string[] | string) {
  if (!Array.isArray(errors)) {
    errors = [errors]
  }
  internalErrorMessages.value = errors
}

const clearErrors = function () {
  internalErrorMessages.value = []
}
</script>

<style lang="scss" scoped>
* {
  font-family: sans-serif;
}

.m-0 {
  margin: 0;
}

.mt-5 {
  margin-top: 3rem;
}

.dropzone {
  --v3-dropzone--primary: 94, 112, 210;
  --v3-dropzone--border: 214, 216, 220;
  --v3-dropzone--description: 190, 191, 195;
  --v3-dropzone--overlay: 40, 44, 53;
  --v3-dropzone--overlay-opacity: 0.3;
  --v3-dropzone--error: 255, 76, 81;
  --v3-dropzone--success: 36, 179, 100;
  position: relative;
  display: flex;
  flex-direction: column;

  &__wrapper {
    border: 2px dashed rgba(var(--v3-dropzone--border));
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: auto;
    height: 200px;
    transition: 0.3s all ease;
    justify-content: center;

    &--disabled {
      opacity: 0.5;
    }

    &--active {
      border-color: rgba(var(--v-theme-primary)) !important;
      background: rgba(var(--v-theme-primary), 0.1) !important;
    }

    &--error {
      border-color: rgba(var(--v3-dropzone--error)) !important;
    }

    &--success {
      border-color: rgba(var(--v3-dropzone--success)) !important;
    }

    &__disabled {
      position: absolute;
      top: -2px;
      inset-inline-start: -2px;
      width: calc(100% + 4px);
      height: calc(100% + 4px);
      border-radius: 12px;
      background: transparent;
      z-index: 2;
    }

  }
}

.hidden {
  display: none;
}

.select-file {
  background: rgba(var(--v-theme-primary));
  border-radius: 10px;
  font-weight: 600;
  font-size: 12px;
  border: none;
  padding: 10px 20px;
  color: #fff;
  cursor: pointer;
  margin-bottom: 10px;
  margin-top: 10px;
}

.description {
  font-size: 12px;
  color: rgba(var(--v3-dropzone--description));
}

.titles {
  text-align: center;
}

.titles h1 {

  font-size: 20px;
}

.titles h3 {
  margin-top: 30px;

}

// Preview
.preview-container {
  width: 100%;
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  flex-wrap: wrap;
  gap: 40px;
}

.preview {
  border-radius: 8px;
  flex-shrink: 0;
  position: relative;
  display: flex;
  align-items: center;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
  height: 100%;
  width: 100%;

  .file-actions {
    visibility: hidden;
  }

  .v-img {
    width: 100%;
    height: 100%;
  }

  &:hover {
    .img-details {
      opacity: 1 !important;
      visibility: visible !important;
    }
  }

  .img-details {
    opacity: 0;
    visibility: hidden;
    position: absolute;
    top: 0;
    inset-inline-start: 0;
    width: 100%;
    height: 100%;
    background: rgba(
        var(--v3-dropzone--overlay),
        var(--v3-dropzone--overlay-opacity)
    );
    border-radius: 8px;
    transition: all 0.2s linear;
    -webkit-backdrop-filter: blur(7px);
    backdrop-filter: blur(7px);
    filter: grayscale(1%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: pointer;

    h2 {
      font-size: 14px;

      text-align: center;
      color: #fff;
      max-width: 40%;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;

      @media (max-width: 400px) {
        max-width: 200px;
      }
    }

    span {
      font-size: 12px;
      font-weight: 600;
      text-align: center;
      color: #fff;
    }

    .img-remove {
      background: rgba(var(--v3-dropzone--error));
      border-radius: 10px;
      border: none;
      padding: 5px;
      color: #fff;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      position: absolute;
      top: 10px;
      right: 10px;
      transition: all 0.2s linear;

      &:active {
        transform: scale(0.9);
      }

      &:hover {
        background: rgba(var(--v3-dropzone--error), 0.8);
      }
    }
  }

  &__file {
    border: 1px dashed rgba(var(--v3-dropzone--primary));

    &--error {
      border-color: rgba(var(--v3-dropzone--error)) !important;
    }
  }
}

.progress-bar-container {
  position: absolute;
  bottom: 0;
  background-color: #666;
  border-radius: 5px;
  overflow: hidden;
  width: 100%;
  height: 10px;

  .progress-bar {
    height: 100%;
    background-color: rgba(var(--v3-dropzone--primary));
    text-align: center;
    font-size: 10px;
    line-height: 10px;
    color: #fff;
    width: 0;
    transition: width 0.5s ease-in-out;
  }
}


</style>
