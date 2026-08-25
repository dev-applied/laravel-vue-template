import {ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"
import {useMessageStore} from "@/stores/message"

export type SettingType = 'string' | 'text' | 'boolean' | 'integer' | 'float' | 'select' | 'json'

/** What a setting can hold — mirrors AppSettingField's modelValue prop. */
export type SettingValue = string | number | boolean | object | null

export interface SettingField {
  key:      string
  label:    string
  type:     SettingType
  help?:    string | null
  choices?: Record<string, string> | null
  isPublic: boolean
  isSecret: boolean
  /** A secret reads back as the mask when set, null when not. */
  value:    SettingValue
}

export interface SettingGroup {
  group:    string
  settings: SettingField[]
}

/** What the API sends in place of a secret. Sending it back means "unchanged". */
export const SECRET_MASK = '********'

export default function useSettings() {
  const {$http, $error} = useHttp()
  const messages = useMessageStore()

  const groups  = shallowRef<SettingGroup[]>([])
  const loading = ref(false)
  const saving  = ref(false)
  const loaded  = ref(false)

  async function fetch(): Promise<void> {
    loading.value = true

    const response = await $http.get('/settings').catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    groups.value = response.data.groups
    loaded.value = true
  }

  async function save(values: Record<string, SettingValue>): Promise<boolean> {
    saving.value = true

    const response = await $http.put('/settings', {settings: values}).catch((e: any) => e)

    saving.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    // Take the server's echo rather than trusting the local form: it is what
    // re-masks a secret that was just replaced.
    groups.value = response.data.groups
    messages.addSuccess('Settings saved.')

    return true
  }

  return {groups, loading, saving, loaded, fetch, save}
}
