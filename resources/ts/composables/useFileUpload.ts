import useHttp from "@/composables/useHttp"
import {heicTo, isHeic} from "heic-to"

export type FileUploadSettings = {
  heicQuality: number
}

const defaultSettings: FileUploadSettings = {
  heicQuality: 0.8
}

export default function useFileUpload(settings: Partial<FileUploadSettings>) {
  settings = {...defaultSettings, ...settings}
  return async (file: File, additionalData?: Record<string, any>) => {
    const {$http, $error} = useHttp()

    if (await isHeic(file)) {
      file = new File(
        [
          await heicTo({
            blob: file,
            type: "image/jpeg",
            quality: settings.heicQuality
          })
        ],
        file.name.replace(/\.heic$/i, ".jpg").replace(/\.heif$/i, ".jpg"),
        {type: "image/jpeg"}
      )
    }

    if (import.meta.env.VITE_FILESYSTEM_DISK === 's3') {
      const fileName = file.name
      const fileType = file.type

      // Get presigned URL
      let response = await $http.post('/files/generate-presigned-url', {
        file_name: fileName,
        file_type: fileType,
        ...additionalData
      }).catch(e => e)
      if ($error(response.status, response.data.message, response.data.errors, false)) {
        throw new Error(response.data.message)
      }

      const {fileId, url} = response.data

      // Upload file to S3
      response = await $http.put(url, file, {
        headers: {
          'Content-Type': fileType,
        }
      }).catch(e => e)
      if (response.status !== 200) {
        throw new Error(response.data.errors ?? 'Error uploading file')
      }

      if (import.meta.env.VITE_APP_ENV === 'local') {
        // Mocking the S3 event that will be sent in production
        $http.put(`/files/mock-s3-event/${fileId}`).catch(e => e)
      }

      let isProcessing = true
      let timeout = 90
      while (isProcessing) {
        response = await $http.get(`/files/view/${fileId}`).catch(e => e)
        if ($error(response.status, response.data.message, response.data.errors, false)) {
          throw new Error(response.data.message)
        }
        if (response.data.file.processed) {
          isProcessing = false
        } else {
          timeout = timeout - 1
          if (timeout < 0) {
            throw new Error('Timeout waiting for file to be processed')
          }
          // Wait for 1 second before checking again
          await new Promise(resolve => setTimeout(resolve, 1000))
        }
      }

      // Save file to database
      return response.data.file
    } else {
      // Handle uploading file for local storage
      const formData = new FormData()
      formData.append('file', file)
      if (additionalData) {
        for (const key in additionalData) {
          formData.append(key, additionalData[key])
        }
      }

      const response = await $http.post<{ file: App.Models.File }>('/files', formData).catch(e => e)
      if ($error(response.status, response.data.message, response.data.errors, false)) {
        throw new Error(response.data.message)
      }

      return response.data.file
    }
  }
}
