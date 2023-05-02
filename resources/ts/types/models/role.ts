export type Role<AdditionalFields = {}> = AdditionalFields & {
  id: number
  name: string
  guard_name: string
  created_at: string
  updated_at: string
}
