import {$http} from "@/plugins/axios"
import {getAuthToken} from "@/plugins/authToken"

/**
 * Build a direct download URL for a file at a given size. The token rides in the
 * query string because these URLs are handed to <img src> / <a href>, which
 * cannot carry an Authorization header.
 */
export function fileUrl(id: number, size: string = "thumbnail"): string {
  const url = new URL(
    `${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}/files/download/${id}/${size}`
  )
  url.searchParams.set('token', getAuthToken() ?? '')

  return url.toString()
}

export function downloadFile(id: number, size: string = "thumbnail"): Promise<any> {
  return $http.download(fileUrl(id, size))
}

export function formatFileSize(size: number): string {
  if (size == 0) {
    return "0 KB"
  }

  const i = Math.floor(Math.log(size) / Math.log(1024))
  const item = size / Math.pow(1024, i)

  return item.toFixed(2) + " " + ["B", "KB", "MB", "GB"][i]
}

export default {url: fileUrl, download: downloadFile, formatFileSize}
