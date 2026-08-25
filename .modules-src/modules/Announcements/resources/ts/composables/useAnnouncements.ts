import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export type AnnouncementLevel = 'info' | 'success' | 'warning' | 'error'
export type AnnouncementPlacement = 'banner' | 'modal'

export interface Announcement {
  id:                       number
  title:                    string
  body:                     string
  level:                    AnnouncementLevel
  placement:                AnnouncementPlacement
  audience:                 string
  dismissible:              boolean
  requiresAcknowledgement:  boolean
  actionLabel?:             string | null
  actionUrl?:               string | null
  startsAt?:                string | null
  endsAt?:                  string | null
  publishedAt?:             string | null
  isLive:                   boolean
  createdAt?:               string | null
  dismissalCount?:          number
}

const LEVEL_ICONS: Record<AnnouncementLevel, string> = {
  info:    'info_outline',
  success: 'check_circle_outline',
  warning: 'warning',
  error:   'report_problem',
}

export function levelIcon(level: AnnouncementLevel): string {
  return LEVEL_ICONS[level] ?? LEVEL_ICONS.info
}

/**
 * What the current user should be shown right now.
 *
 * Dismissal is a server-side row rather than localStorage on purpose: a person
 * who dismisses a banner on their laptop should not meet it again on their
 * phone, and "it keeps coming back" is the complaint that makes people stop
 * reading announcements at all.
 */
export default function useAnnouncements() {
  const {$http, $error} = useHttp()

  const announcements = shallowRef<Announcement[]>([])
  const loading       = ref(false)

  const banners = computed(() => announcements.value.filter((a) => a.placement === 'banner'))
  const modals  = computed(() => announcements.value.filter((a) => a.placement === 'modal'))

  async function fetch(): Promise<void> {
    loading.value = true

    const response = await $http.get('/announcements/active').catch((e: any) => e)

    loading.value = false
    // Silent: this runs on app boot for every user, and a failure here should
    // never put an error toast in front of someone who did nothing.
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    announcements.value = response.data.announcements
  }

  async function dismiss(announcement: Announcement): Promise<void> {
    // Optimistic — the banner closes immediately. On failure the next fetch
    // brings it back, which is the correct direction to be wrong in.
    announcements.value = announcements.value.filter((a) => a.id !== announcement.id)

    const response = await $http.post(`/announcements/${announcement.id}/dismiss`).catch((e: any) => e)
    $error(response.status, response.data?.message, response.data?.errors, false)
  }

  return {announcements, banners, modals, loading, fetch, dismiss}
}
