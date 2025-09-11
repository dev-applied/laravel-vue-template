import {$http} from "@/plugins/axios"

export function fileUrl(id: number, size: string = "thumbnail"): string {
  const url = new URL(`${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}/files/download/${id}/${size}`)
  url.searchParams.set('token', localStorage.getItem('token') || '')

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
