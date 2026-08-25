import useHttp from "@/composables/useHttp"

export interface ManagedUser {
  id:             number
  firstName:      string
  lastName:       string
  name:           string
  email:          string
  emailVerified:  boolean
  isActive:       boolean
  deactivatedAt:  string | null
  lastLoginAt:    string | null
  createdAt:      string | null
  /** True on the viewer's own row — the UI hides destructive controls on it. */
  isSelf:         boolean
}

/**
 * Lifecycle actions for the management screen.
 *
 * Listing is NOT here: AppPaginationTable owns the endpoint, pagination,
 * sorting and debounced search, and re-implementing that in a composable is how
 * a management screen ends up silently showing only the first 25 accounts.
 * Create / update are likewise AppServerValidationForm's, which already owns
 * the 422 error bag and its per-field mapping.
 */
export default function useUsers() {
  const {$http, $error} = useHttp()

  /** Deactivate or reactivate. The server refuses self-deactivation and the last active account. */
  async function setActive(user: ManagedUser, active: boolean): Promise<boolean> {
    const action = active ? 'reactivate' : 'deactivate'

    const {status, data} = await $http.post(`manage/users/${user.id}/${action}`).catch((e: any) => e)

    return !$error(status, data?.message, data?.errors)
  }

  async function remove(user: ManagedUser): Promise<boolean> {
    const {status, data} = await $http.delete(`manage/users/${user.id}`).catch((e: any) => e)

    return !$error(status, data?.message, data?.errors)
  }

  return {setActive, remove}
}
