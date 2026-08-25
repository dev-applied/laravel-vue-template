import useHttp from "@/composables/useHttp"
import {heicTo, isHeic} from "heic-to"

export type UploadProgress = {
  /** 0-100, so it can drive a v-progress-linear directly. */
  progress: number
  /**
   * True once the bytes have landed and the server is generating variants.
   * There is no meaningful percentage for that phase, so the caller shows an
   * indeterminate bar.
   */
  scanning_for_virus: boolean
}

export type FileUploadSettings = {
  heicQuality: number
  /**
   * Progress callback.
   *
   * This did not exist, and AppFileDropzone passed one anyway — TypeScript
   * would have said so, but nothing type-checked module frontends. Excess
   * properties on an object literal are dropped silently at runtime, so the
   * dropzone's progress bar simply never moved.
   */
  onProgress?: (event: UploadProgress) => void
  /**
   * Destination folder. Same story: AppFileUploadBtn passed `folderId` into a
   * settings object that had no such key, so every upload from the button
   * landed outside the folder the user was looking at.
   */
  folderId?: number | string | null
}

const defaultSettings: FileUploadSettings = {
  heicQuality: 0.8
}

/**
 * Upload a browser File and resolve with the stored file record.
 *
 * Two paths, matching the module's `storage` option:
 *  - local        — multipart POST to /files; variants are generated in-request.
 *  - s3-presigned — reserve a row + presigned PUT, upload straight to the bucket,
 *                   then wait for variant processing to finish.
 *
 * HEIC/HEIF is transcoded to JPEG first either way, since browsers can't render it.
 */
export default function useFileUpload(settings: Partial<FileUploadSettings> = {}) {
  const resolved: FileUploadSettings = {...defaultSettings, ...settings}

  return async (file: File, additionalData?: Record<string, any>) => {
    const {$http, $error} = useHttp()

    const payload = resolved.folderId != null
      ? {folder_id: resolved.folderId, ...additionalData}
      : additionalData

    if (await isHeic(file)) {
      file = new File(
        [await heicTo({blob: file, type: "image/jpeg", quality: resolved.heicQuality})],
        file.name.replace(/\.hei[cf]$/i, ".jpg"),
        {type: "image/jpeg"}
      )
    }

    if (import.meta.env.VITE_FILESYSTEM_DISK === 's3') {
      return await uploadViaPresignedUrl(file, payload, $http, $error, resolved.onProgress)
    }

    return await uploadDirect(file, payload, $http, $error, resolved.onProgress)
  }
}

async function uploadDirect(
  file: File,
  additionalData: Record<string, any> | undefined,
  $http: any,
  $error: any,
  onProgress?: (event: UploadProgress) => void
) {
  const formData = new FormData()
  formData.append('file', file)
  for (const key in additionalData ?? {}) {
    formData.append(key, additionalData![key])
  }

  const response = await $http.post('/files', formData, {
    onUploadProgress: (e: {progress?: number}) => onProgress?.({
      progress: Math.round((e.progress ?? 0) * 100),
      scanning_for_virus: false,
    }),
  }).catch((e: any) => e)

  // Bytes are in; the request is now waiting on the server to make variants.
  onProgress?.({progress: 100, scanning_for_virus: true})
  if ($error(response.status, response.data?.message, response.data?.errors, false)) {
    throw new Error(response.data?.message ?? 'Error uploading file')
  }

  return response.data.file
}

async function uploadViaPresignedUrl(
  file: File,
  additionalData: Record<string, any> | undefined,
  $http: any,
  $error: any,
  onProgress?: (event: UploadProgress) => void
) {
  // 1. Reserve the row and get a presigned PUT.
  let response = await $http.post('/files/generate-presigned-url', {
    file_name: file.name,
    file_type: file.type,
    // Required by the endpoint. It validates the size and signs it into the
    // presigned PUT as ContentLength, so an oversized or over-quota upload is
    // refused before any bytes leave the browser — and S3 rejects the PUT if
    // the actual body does not match what was declared here.
    file_size: file.size,
    ...additionalData
  }).catch((e: any) => e)
  if ($error(response.status, response.data?.message, response.data?.errors, false)) {
    throw new Error(response.data?.message ?? 'Could not start the upload')
  }

  const {fileId, url} = response.data

  // 2. Bytes go straight to the bucket — never through PHP.
  response = await $http.put(url, file, {
    headers: {'Content-Type': file.type},
    onUploadProgress: (e: {progress?: number}) => onProgress?.({
      progress: Math.round((e.progress ?? 0) * 100),
      scanning_for_virus: false,
    }),
  }).catch((e: any) => e)
  if (response.status !== 200) {
    throw new Error(response.data?.errors ?? 'Error uploading file')
  }

  // 3. Kick off processing. In production a bucket event calls this endpoint;
  //    locally there is no event, so the client triggers it itself.
  await $http.put(`/files/process/${fileId}`).catch((e: any) => e)

  // The poll below has no percentage to report — the bytes are already in the
  // bucket and this is the server generating variants.
  onProgress?.({progress: 100, scanning_for_virus: true})

  // 4. Poll the JSON metadata endpoint until variants exist. Note this is
  //    /files/meta/:id — /files/view/:id returns a redirect or raw bytes and
  //    can never be polled, which is why the kernel version never worked.
  for (let remaining = 90; remaining > 0; remaining--) {
    response = await $http.get(`/files/meta/${fileId}`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) {
      throw new Error(response.data?.message ?? 'Error reading file status')
    }
    if (response.data.file.processed) {
      return response.data.file
    }
    await new Promise(resolve => setTimeout(resolve, 1000))
  }

  throw new Error('Timeout waiting for file to be processed')
}
