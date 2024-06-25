export default function file(id: number, size: string = "thumbnail"): string {
  return `${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}/files/${id}/${size}`
}
