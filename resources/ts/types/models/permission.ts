export type Permission<AdditionalFields = {}> = AdditionalFields & {
  id: number
  name: string
  guard_name: string
  created_at: string
  updated_at: string
}
