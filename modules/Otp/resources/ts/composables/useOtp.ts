import {computed, ref} from "vue"
import useHttp from "@/composables/useHttp"

/**
 * Request-then-verify, for passwordless sign-in.
 *
 * `masked` is what the server says it sent to — never echo back what the
 * person typed, because the confirmation screen is shown to whoever typed the
 * address, who may not be its owner.
 */
export default function useOtp() {
  const {$http, $error} = useHttp()

  const identifier = ref("")
  const code       = ref("")
  const masked     = ref<string | null>(null)
  const sending    = ref(false)
  const verifying  = ref(false)
  const secondsLeft = ref(0)

  let ticker: ReturnType<typeof setInterval> | null = null

  const sent      = computed(() => masked.value !== null)
  const canResend = computed(() => sent.value && secondsLeft.value <= 0)

  function startCountdown(seconds: number): void {
    secondsLeft.value = seconds
    if (ticker) clearInterval(ticker)

    ticker = setInterval(() => {
      secondsLeft.value -= 1
      if (secondsLeft.value <= 0 && ticker) {
        clearInterval(ticker)
        ticker = null
      }
    }, 1000)
  }

  function stop(): void {
    if (ticker) clearInterval(ticker)
    ticker = null
  }

  async function request(path = '/otp/request'): Promise<boolean> {
    sending.value = true

    const body = path === '/otp/request' ? {identifier: identifier.value} : {}
    const response = await $http.post(path, body).catch((e: any) => e)

    sending.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    masked.value = response.data.masked
    startCountdown(response.data.expiresIn ?? 600)

    return true
  }

  async function verify(path = '/otp/verify'): Promise<any | null> {
    verifying.value = true

    const body = path === '/otp/verify'
      ? {identifier: identifier.value, code: code.value}
      : {code: code.value}

    const response = await $http.post(path, body).catch((e: any) => e)

    verifying.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) {
      // Wrong code: clear the field but keep the screen, so the next attempt
      // does not mean re-requesting a code.
      code.value = ""

      return null
    }

    stop()

    return response.data
  }

  function reset(): void {
    stop()
    masked.value = null
    code.value = ""
    secondsLeft.value = 0
  }

  return {identifier, code, masked, sent, sending, verifying, secondsLeft, canResend, request, verify, reset, stop}
}
