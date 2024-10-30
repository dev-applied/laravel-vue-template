import axios from "axios"

export function fileUrl(id: number, size: string = "thumbnail"): string {
  return `${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}/files/download/${id}/${size}`
}

export function downloadFile(id: number, size: string = "thumbnail"): Promise<any> {
  return axios.download(fileUrl(id, size))
}
