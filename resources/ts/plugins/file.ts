import {$http} from "@/plugins/axios"

export function fileUrl(id: number, size: string = "thumbnail"): string {
  return `${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}/files/download/${id}/${size}`
}

export function downloadFile(id: number, size: string = "thumbnail"): Promise<any> {
  return $http.download(fileUrl(id, size))
}
