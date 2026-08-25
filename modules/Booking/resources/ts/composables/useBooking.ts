import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface Slot {
  starts_at: string
  ends_at:   string
  remaining: number
}

export interface BookingResourceInfo {
  name:        string
  slug:        string
  timezone:    string
  slotMinutes: number
  capacity:    number
}

export interface BookingRecord {
  reference:   string
  name:        string
  email:       string
  notes?:      string | null
  status:      'pending' | 'confirmed' | 'cancelled'
  startsAt:    string
  endsAt:      string
  cancelledAt?: string | null
  resource?:   {name: string, slug: string, timezone: string}
}

/**
 * Availability for one resource, plus the booking call.
 *
 * Slots arrive as UTC instants and are displayed in the resource's timezone —
 * a resource in New York shows its own 9am to someone in London, because the
 * appointment is at the resource, not at the viewer.
 */
export default function useBooking(slug: string) {
  const {$http, $error} = useHttp()

  const resource = shallowRef<BookingResourceInfo | null>(null)
  const slots    = shallowRef<Slot[]>([])
  const loading  = ref(false)
  const booking  = ref(false)
  const confirmed = shallowRef<BookingRecord | null>(null)

  /** Slots grouped by local calendar day, for a day-by-day picker. */
  const byDay = computed(() => {
    const groups: Record<string, Slot[]> = {}

    for (const slot of slots.value) {
      const day = dayKey(slot.starts_at)
      ;(groups[day] ??= []).push(slot)
    }

    return groups
  })

  function tz(): string {
    return resource.value?.timezone || 'UTC'
  }

  function dayKey(iso: string): string {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: tz(), year: 'numeric', month: '2-digit', day: '2-digit',
    }).format(new Date(iso))
  }

  function timeLabel(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {
      timeZone: tz(), hour: 'numeric', minute: '2-digit',
    }).format(new Date(iso))
  }

  function dayLabel(key: string): string {
    // Noon avoids the date flipping either side of midnight in the target zone.
    return new Intl.DateTimeFormat(undefined, {
      timeZone: tz(), weekday: 'long', month: 'long', day: 'numeric',
    }).format(new Date(`${key}T12:00:00Z`))
  }

  async function fetchAvailability(from: Date, to: Date): Promise<void> {
    loading.value = true

    const response = await $http.get(`/booking/${slug}/availability`, {
      params: {from: from.toISOString(), to: to.toISOString()},
    }).catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    resource.value = response.data.resource
    slots.value = response.data.slots
  }

  async function book(slot: Slot, details: {name: string, email: string, notes?: string}): Promise<boolean> {
    booking.value = true

    // Only starts_at is sent. The end is the resource's business — a
    // client-supplied one would let someone book three hours on a
    // thirty-minute resource.
    const response = await $http.post(`/booking/${slug}`, {
      starts_at: slot.starts_at,
      ...details,
    }).catch((e: any) => e)

    booking.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) {
      // 409 means someone else took it in the meantime — refresh so the picker
      // stops offering a slot that is gone.
      if (response.status === 409 || response.status === 422) {
        await fetchAvailability(new Date(slot.starts_at), new Date(new Date(slot.starts_at).getTime() + 86_400_000))
      }

      return false
    }

    confirmed.value = response.data

    return true
  }

  return {resource, slots, byDay, loading, booking, confirmed, fetchAvailability, book, timeLabel, dayLabel, tz}
}
