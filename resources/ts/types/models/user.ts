export declare type User<AdditionalFields = {}> = AdditionalFields & {
  id: number
  email: string
  password: string
  first_name: string
  last_name: string
  active: boolean
  role: string
  company_id: number
  active_store_id: number
  file_id: number
  created_at: string
  updated_at: string
  device_token: string
}

export type AuthUser = User<{ all_permissions: { name: string }[] }>
