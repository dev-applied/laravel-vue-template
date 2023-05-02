export type User<AdditionalFields = {}> = AdditionalFields & {
  id: number
  first_name: string
  last_name: string
  email: string
  email_verified_at: string
  password: string
  created_at: string
  updated_at: string
}

export type AuthUser = User<{ all_permissions: { name: string }[] }>
